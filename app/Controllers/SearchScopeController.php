<?php

namespace App\Controllers;

use App\Models\SearchScopeModel;
use App\Models\UserModel;
use App\Models\RoleModel;
use App\Models\AuditLogModel;
use App\Services\DocumentConversionService;
use App\Services\DocumentPreviewService;

class SearchScopeController extends BaseController
{
    protected SearchScopeModel $scopeModel;
    protected AuditLogModel $auditModel;

    public function __construct()
    {
        $this->scopeModel = new SearchScopeModel();
        $this->auditModel = new AuditLogModel();
    }

    /**
     * Get scope tree as JSON (for sidebar rendering).
     * Admins with `scopes.manage` permission see all; others see only allowed scopes.
     */
    public function tree()
    {
        $isAdmin = $this->can('scopes.manage');
        $userId  = (int) session()->get('user_id');
        $roleId  = (int) ($this->currentUser['role_id'] ?? 0);

        $tree = $this->scopeModel->getTreeForUser($userId, $roleId, $isAdmin);
        return $this->jsonResponse(['tree' => $tree]);
    }

    /**
     * Get flat list for dropdown selectors.
     * Respects the same access filter as tree().
     */
    public function dropdown()
    {
        $isAdmin = $this->can('scopes.manage');
        $userId  = (int) session()->get('user_id');
        $roleId  = (int) ($this->currentUser['role_id'] ?? 0);

        // Re-use getTreeForUser then flatten, so the same access rules apply
        $tree  = $this->scopeModel->getTreeForUser($userId, $roleId, $isAdmin);
        $list  = [];
        $this->flattenForDropdown($tree, $list, 0);

        $items = [];
        foreach ($list as $id => $name) {
            $items[] = ['id' => $id, 'name' => $name];
        }
        return $this->jsonResponse(['items' => $items]);
    }

    /** Flatten tree into id→name pairs with indentation. */
    private function flattenForDropdown(array $nodes, array &$list, int $depth): void
    {
        foreach ($nodes as $node) {
            $list[$node['id']] = str_repeat('— ', $depth) . $node['name'];
            if (!empty($node['children'])) {
                $this->flattenForDropdown($node['children'], $list, $depth + 1);
            }
        }
    }

    /**
     * Create a new scope (AJAX POST).
     */
    public function create()
    {
        if (!$this->request->isAJAX()) {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Invalid request'], 400);
        }

        $name = trim($this->request->getPost('name') ?? '');
        if (empty($name)) {
            return $this->jsonResponse(['status' => 'error', 'message' => lang('App.error_occurred')], 422);
        }

        $parentId = $this->request->getPost('parent_id');
        $parentId = ($parentId !== null && $parentId !== '' && $parentId !== '0') ? (int) $parentId : null;

        if ($parentId !== null) {
            $parent = $this->scopeModel->find($parentId);
            if (!$parent) {
                return $this->jsonResponse(['status' => 'error', 'message' => lang('App.not_found')], 404);
            }
        }

        $scopeId = $this->scopeModel->createScope([
            'name'        => $name,
            'parent_id'   => $parentId,
            'description' => trim($this->request->getPost('description') ?? '') ?: null,
            'created_by'  => session()->get('user_id'),
        ]);

        if (!$scopeId) {
            return $this->jsonResponse(['status' => 'error', 'message' => lang('App.error_occurred')], 500);
        }

        $this->auditModel->log('scope_created', "Created scope: {$name}", 'search_scope', $scopeId);

        return $this->jsonResponse([
            'status'  => 'success',
            'message' => lang('App.scope_created'),
            'scope'   => $this->scopeModel->find($scopeId),
        ]);
    }

    /**
     * Rename a scope (AJAX POST).
     */
    public function update(int $id)
    {
        if (!$this->request->isAJAX()) {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Invalid request'], 400);
        }

        $scope = $this->scopeModel->find($id);
        if (!$scope) {
            return $this->jsonResponse(['status' => 'error', 'message' => lang('App.not_found')], 404);
        }

        $name = trim($this->request->getPost('name') ?? '');
        if (empty($name)) {
            return $this->jsonResponse(['status' => 'error', 'message' => lang('App.error_occurred')], 422);
        }

        $this->scopeModel->update($id, [
            'name'        => $name,
            'description' => trim($this->request->getPost('description') ?? '') ?: null,
        ]);

        $this->auditModel->log('scope_updated', "Updated scope: {$name}", 'search_scope', $id);

        return $this->jsonResponse([
            'status'  => 'success',
            'message' => lang('App.saved_success'),
        ]);
    }

    /**
     * Delete a scope only — documents are SET NULL (FK ON DELETE SET NULL).
     * Child scopes are CASCADE deleted (FK ON DELETE CASCADE).
     */
    public function delete(int $id)
    {
        if (!$this->request->isAJAX()) {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Invalid request'], 400);
        }

        $scope = $this->scopeModel->find($id);
        if (!$scope) {
            return $this->jsonResponse(['status' => 'error', 'message' => lang('App.not_found')], 404);
        }

        $this->scopeModel->deleteScope($id);
        $this->auditModel->log('scope_deleted', "Deleted scope: {$scope['name']}", 'search_scope', $id);

        return $this->jsonResponse([
            'status'  => 'success',
            'message' => lang('App.scope_deleted'),
        ]);
    }

    /**
     * Delete a scope AND all its documents (files + DB records).
     * Recursively handles child scopes and their documents too.
     *
     * Uses chunked SQL deletes to handle very large scopes (100k+ docs)
     * without exhausting PHP memory or hitting execution time limits.
     * legal_principles rows are removed automatically via ON DELETE CASCADE FK.
     */
    public function deleteWithDocuments(int $id)
    {
        if (!$this->request->isAJAX()) {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Invalid request'], 400);
        }

        $scope = $this->scopeModel->find($id);
        if (!$scope) {
            return $this->jsonResponse(['status' => 'error', 'message' => lang('App.not_found')], 404);
        }

        // Allow long-running deletes for very large scopes
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        // Collect this scope + all descendant scope IDs
        $allScopeIds = array_merge([$id], $this->scopeModel->getDescendantIds($id));

        $db      = \Config\Database::connect();
        $deleted = 0;
        $CHUNK   = 500;

        foreach ($allScopeIds as $scopeId) {
            // Chunk through documents without loading LONGTEXT columns (full_text / normalized_text)
            // to avoid memory exhaustion on large scopes.
            while (true) {
                $docs = $db->table('legal_documents')
                    ->select('id, file_path, content_hash')
                    ->where('scope_id', $scopeId)
                    ->limit($CHUNK)
                    ->get()
                    ->getResultArray();

                if (empty($docs)) {
                    break;
                }

                foreach ($docs as $doc) {
                    // Delete physical file
                    $filePath = WRITEPATH . $doc['file_path'];
                    if (file_exists($filePath)) {
                        @unlink($filePath);
                    }
                    $altPath = WRITEPATH . 'uploads/documents/original/' . basename($doc['file_path']);
                    if (file_exists($altPath)) {
                        @unlink($altPath);
                    }

                    // Clear conversion / preview caches
                    $contentHash = $doc['content_hash'] ?? '';
                    if ($contentHash) {
                        DocumentConversionService::clearCache($contentHash);
                        DocumentPreviewService::clearCache($contentHash);
                    }
                }

                // Bulk-delete the chunk from DB.
                // legal_principles rows are auto-removed via ON DELETE CASCADE FK.
                $docIds = array_column($docs, 'id');
                $db->table('legal_documents')->whereIn('id', $docIds)->delete();
                $deleted += count($docs);
            }
        }

        // Delete the scope itself (CASCADE removes child scopes via FK)
        $this->scopeModel->deleteScope($id);
        $this->auditModel->log('scope_deleted', "Deleted scope with {$deleted} docs: {$scope['name']}", 'search_scope', $id);

        return $this->jsonResponse([
            'status'  => 'success',
            'message' => lang('App.scope_deleted_with_docs', [$deleted]),
            'deleted' => $deleted,
        ]);
    }

    /**
     * Move a scope to a new parent (AJAX POST).
     */
    public function move(int $id)
    {
        if (!$this->request->isAJAX()) {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Invalid request'], 400);
        }

        $scope = $this->scopeModel->find($id);
        if (!$scope) {
            return $this->jsonResponse(['status' => 'error', 'message' => lang('App.not_found')], 404);
        }

        $newParentId = $this->request->getPost('parent_id');
        $newParentId = ($newParentId !== null && $newParentId !== '' && $newParentId !== '0') ? (int) $newParentId : null;

        $result = $this->scopeModel->moveScope($id, $newParentId);
        if (!$result) {
            return $this->jsonResponse(['status' => 'error', 'message' => lang('App.error_occurred')], 422);
        }

        $this->auditModel->log('scope_moved', "Moved scope: {$scope['name']}", 'search_scope', $id);

        return $this->jsonResponse([
            'status'  => 'success',
            'message' => lang('App.saved_success'),
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // ── SCOPE ACCESS MANAGEMENT ──────────────────────────────────
    // ══════════════════════════════════════════════════════════════

    /**
     * Toggle is_restricted on/off (AJAX POST).
     * Requires scopes.manage permission.
     */
    public function setRestricted(int $id)
    {
        if (!$this->request->isAJAX()) {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Invalid request'], 400);
        }
        if (!$this->can('scopes.manage')) {
            return $this->jsonResponse(['status' => 'error', 'message' => lang('App.forbidden')], 403);
        }

        $scope = $this->scopeModel->find($id);
        if (!$scope) {
            return $this->jsonResponse(['status' => 'error', 'message' => lang('App.not_found')], 404);
        }

        $restricted = (bool) $this->request->getPost('is_restricted');
        $this->scopeModel->setRestricted($id, $restricted);

        $this->auditModel->log(
            'scope_access_changed',
            "Scope '{$scope['name']}' restricted=" . ($restricted ? '1' : '0'),
            'search_scope', $id
        );

        return $this->jsonResponse([
            'status'        => 'success',
            'is_restricted' => $restricted,
            'message'       => $restricted ? lang('App.scope_now_restricted') : lang('App.scope_now_open'),
        ]);
    }

    /**
     * Get the current access list for a scope (AJAX GET).
     * Returns users + roles that have been explicitly granted access.
     */
    public function accessList(int $id)
    {
        if (!$this->can('scopes.manage')) {
            return $this->jsonResponse(['status' => 'error', 'message' => lang('App.forbidden')], 403);
        }

        $scope = $this->scopeModel->find($id);
        if (!$scope) {
            return $this->jsonResponse(['status' => 'error', 'message' => lang('App.not_found')], 404);
        }

        $entries = $this->scopeModel->getScopeAccessList($id);

        // Also return full user + role lists for the "add" dropdowns
        $userModel = new UserModel();
        $roleModel = new RoleModel();

        $users = $userModel->select('id, username, full_name')->where('is_active', 1)->findAll();
        $roles = $roleModel->select('id, name')->findAll();

        return $this->jsonResponse([
            'scope'   => ['id' => $scope['id'], 'name' => $scope['name'], 'is_restricted' => (bool) $scope['is_restricted']],
            'entries' => $entries,
            'users'   => $users,
            'roles'   => $roles,
        ]);
    }

    /**
     * Grant a user or role access to a scope (AJAX POST).
     */
    public function grantAccess(int $id)
    {
        if (!$this->request->isAJAX()) {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Invalid request'], 400);
        }
        if (!$this->can('scopes.manage')) {
            return $this->jsonResponse(['status' => 'error', 'message' => lang('App.forbidden')], 403);
        }

        $scope = $this->scopeModel->find($id);
        if (!$scope) {
            return $this->jsonResponse(['status' => 'error', 'message' => lang('App.not_found')], 404);
        }

        $userId = $this->request->getPost('user_id');
        $roleId = $this->request->getPost('role_id');

        $userId = ($userId !== null && $userId !== '') ? (int) $userId : null;
        $roleId = ($roleId !== null && $roleId !== '') ? (int) $roleId : null;

        // Must have exactly one of user_id or role_id
        if (($userId === null) === ($roleId === null)) {
            return $this->jsonResponse(['status' => 'error', 'message' => lang('App.error_occurred')], 422);
        }

        $grantedBy = (int) session()->get('user_id');
        $result    = $this->scopeModel->grantAccess($id, $userId, $roleId, $grantedBy);

        if ($result === false) {
            return $this->jsonResponse(['status' => 'error', 'message' => lang('App.access_already_granted')], 409);
        }

        $who = $userId ? "user #{$userId}" : "role #{$roleId}";
        $this->auditModel->log('scope_access_granted', "Granted {$who} to scope '{$scope['name']}'", 'search_scope', $id);

        return $this->jsonResponse([
            'status'  => 'success',
            'message' => lang('App.access_granted'),
        ]);
    }

    /**
     * Revoke a specific access entry (AJAX POST).
     */
    public function revokeAccess(int $id, int $accessId)
    {
        if (!$this->request->isAJAX()) {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Invalid request'], 400);
        }
        if (!$this->can('scopes.manage')) {
            return $this->jsonResponse(['status' => 'error', 'message' => lang('App.forbidden')], 403);
        }

        $this->scopeModel->revokeAccess($accessId);
        $this->auditModel->log('scope_access_revoked', "Revoked access entry #{$accessId} for scope #{$id}", 'search_scope', $id);

        return $this->jsonResponse([
            'status'  => 'success',
            'message' => lang('App.access_revoked'),
        ]);
    }
}
