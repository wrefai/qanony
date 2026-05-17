<?php

namespace App\Models;

use CodeIgniter\Model;

class SearchScopeModel extends Model
{
    protected $table         = 'search_scopes';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'parent_id', 'name', 'description', 'sort_order',
        'is_active', 'is_restricted', 'created_by',
    ];
    protected $returnType = 'array';

    /**
     * Get the full scope tree with document counts.
     *
     * Returns a flat array with each scope having a `children` array
     * and `document_count` field. Nested up to 3 levels.
     *
     * @param bool $activeOnly Only include active scopes
     * @return array Nested tree structure
     */
    public function getTree(bool $activeOnly = true): array
    {
        $builder = $this->builder();
        $builder->select('search_scopes.*, COUNT(ld.id) AS document_count');
        $builder->join('legal_documents ld', 'ld.scope_id = search_scopes.id', 'left');

        if ($activeOnly) {
            $builder->where('search_scopes.is_active', 1);
        }

        $builder->groupBy('search_scopes.id');
        $builder->orderBy('search_scopes.sort_order', 'ASC');
        $builder->orderBy('search_scopes.name', 'ASC');

        $flat = $builder->get()->getResultArray();

        return $this->buildTree($flat);
    }

    /**
     * Build a nested tree from a flat array of scopes.
     *
     * @param array $items Flat array with parent_id references
     * @param int|null $parentId Parent to build from
     * @return array Nested tree
     */
    private function buildTree(array $items, ?int $parentId = null): array
    {
        $tree = [];
        foreach ($items as $item) {
            $itemParent = $item['parent_id'] !== null ? (int) $item['parent_id'] : null;
            if ($itemParent === $parentId) {
                $item['children'] = $this->buildTree($items, (int) $item['id']);
                $tree[] = $item;
            }
        }
        return $tree;
    }

    /**
     * Get all scopes as a flat list with document counts.
     *
     * @return array
     */
    public function getWithDocumentCounts(): array
    {
        return $this->builder()
            ->select('search_scopes.*, COUNT(ld.id) AS document_count')
            ->join('legal_documents ld', 'ld.scope_id = search_scopes.id', 'left')
            ->groupBy('search_scopes.id')
            ->orderBy('search_scopes.sort_order', 'ASC')
            ->orderBy('search_scopes.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Create a new scope.
     *
     * @param array $data Scope data (name, parent_id, description, created_by)
     * @return int|false Insert ID or false on failure
     */
    public function createScope(array $data)
    {
        // Auto-set sort_order to max+1 within same parent
        if (!isset($data['sort_order'])) {
            $maxSort = $this->builder()
                ->selectMax('sort_order')
                ->where('parent_id', $data['parent_id'] ?? null)
                ->get()
                ->getRow();
            $data['sort_order'] = ($maxSort && $maxSort->sort_order !== null) ? (int) $maxSort->sort_order + 1 : 0;
        }

        return $this->insert($data);
    }

    /**
     * Move a scope to a new parent.
     *
     * @param int $scopeId Scope to move
     * @param int|null $newParentId New parent (null = root)
     * @return bool
     */
    public function moveScope(int $scopeId, ?int $newParentId): bool
    {
        // Prevent circular reference: cannot move a scope under itself or its descendants
        if ($newParentId !== null) {
            $descendantIds = $this->getDescendantIds($scopeId);
            if (in_array($newParentId, $descendantIds, true) || $newParentId === $scopeId) {
                return false;
            }
        }

        return $this->update($scopeId, ['parent_id' => $newParentId]);
    }

    /**
     * Delete a scope. Documents in this scope will have scope_id set to NULL
     * (handled by FK ON DELETE SET NULL in migration 2).
     * Child scopes are CASCADE deleted (handled by FK in migration 1).
     *
     * @param int|null $id Scope ID
     * @param bool $purge Hard delete
     * @return bool
     */
    public function deleteScope(int $id): bool
    {
        return $this->delete($id) !== false;
    }

    /**
     * Get all descendant scope IDs (recursive).
     *
     * @param int $scopeId Root scope
     * @return array Array of descendant IDs
     */
    public function getDescendantIds(int $scopeId): array
    {
        $children = $this->where('parent_id', $scopeId)->findAll();
        $ids = [];
        foreach ($children as $child) {
            $childId = (int) $child['id'];
            $ids[] = $childId;
            $ids = array_merge($ids, $this->getDescendantIds($childId));
        }
        return $ids;
    }

    /**
     * Get all scope IDs including self and all descendants.
     * Useful for search filtering (when user checks a parent scope,
     * we also search documents in child scopes).
     *
     * @param array $scopeIds Array of selected scope IDs
     * @return array Expanded array including all descendant IDs
     */
    public function expandScopeIds(array $scopeIds): array
    {
        $allIds = [];
        foreach ($scopeIds as $id) {
            $id = (int) $id;
            $allIds[] = $id;
            $allIds = array_merge($allIds, $this->getDescendantIds($id));
        }
        return array_unique($allIds);
    }

    /**
     * Get flat list of scopes for dropdown/select.
     * Returns indented names for nested scopes.
     *
     * @return array [id => indented_name, ...]
     */
    public function getDropdownList(): array
    {
        $tree = $this->getTree(true);
        $list = [];
        $this->flattenTreeForDropdown($tree, $list, 0);
        return $list;
    }

    /**
     * Recursively flatten tree into dropdown format.
     */
    private function flattenTreeForDropdown(array $nodes, array &$list, int $depth): void
    {
        foreach ($nodes as $node) {
            $prefix = str_repeat('— ', $depth);
            $list[$node['id']] = $prefix . $node['name'];
            if (!empty($node['children'])) {
                $this->flattenTreeForDropdown($node['children'], $list, $depth + 1);
            }
        }
    }

    // ══════════════════════════════════════════════════════════════
    // ── ACCESS CONTROL ───────────────────────────────────────────
    // ══════════════════════════════════════════════════════════════

    /**
     * Get the full scope tree, filtered to only the scopes the given
     * user/role may access.
     *
     * Admins with `scopes.manage` permission bypass the filter entirely.
     *
     * @param int  $userId  Current user ID
     * @param int  $roleId  Current user's role ID
     * @param bool $isAdmin When true, all scopes are visible
     * @return array Nested tree (same shape as getTree())
     */
    public function getTreeForUser(int $userId, int $roleId, bool $isAdmin = false): array
    {
        $builder = $this->builder();
        $builder->select('search_scopes.*, COUNT(ld.id) AS document_count');
        $builder->join('legal_documents ld', 'ld.scope_id = search_scopes.id', 'left');
        $builder->where('search_scopes.is_active', 1);
        $builder->groupBy('search_scopes.id');
        $builder->orderBy('search_scopes.sort_order', 'ASC');
        $builder->orderBy('search_scopes.name', 'ASC');

        if (!$isAdmin) {
            // Restricted scopes are visible only if the user or their role
            // has an explicit entry in scope_user_access.
            $db = \Config\Database::connect();

            // Collect IDs of restricted scopes this user can access
            $accessQuery = $db->table('scope_user_access')
                ->select('scope_id')
                ->groupStart()
                    ->where('user_id', $userId)
                    ->orWhere('role_id', $roleId)
                ->groupEnd()
                ->get()
                ->getResultArray();

            $allowedRestrictedIds = array_column($accessQuery, 'scope_id');

            if (empty($allowedRestrictedIds)) {
                // No access entries at all → only show unrestricted scopes
                $builder->where('search_scopes.is_restricted', 0);
            } else {
                // Show unrestricted OR scopes the user has been explicitly granted
                $builder->groupStart()
                    ->where('search_scopes.is_restricted', 0)
                    ->orWhereIn('search_scopes.id', $allowedRestrictedIds)
                ->groupEnd();
            }
        }

        $flat = $builder->get()->getResultArray();
        return $this->buildTree($flat);
    }

    /**
     * Check whether a specific user may access a specific scope.
     *
     * @param int  $scopeId
     * @param int  $userId
     * @param int  $roleId
     * @param bool $isAdmin
     * @return bool
     */
    public function userCanAccessScope(int $scopeId, int $userId, int $roleId, bool $isAdmin = false): bool
    {
        if ($isAdmin) {
            return true;
        }

        $scope = $this->find($scopeId);
        if (!$scope) {
            return false;
        }

        // Unrestricted scope — everyone can access
        if (empty($scope['is_restricted'])) {
            return true;
        }

        $db = \Config\Database::connect();
        $count = $db->table('scope_user_access')
            ->where('scope_id', $scopeId)
            ->groupStart()
                ->where('user_id', $userId)
                ->orWhere('role_id', $roleId)
            ->groupEnd()
            ->countAllResults();

        return $count > 0;
    }

    /**
     * Get all access entries for a scope (users + roles).
     *
     * @param int $scopeId
     * @return array
     */
    public function getScopeAccessList(int $scopeId): array
    {
        $db = \Config\Database::connect();
        return $db->table('scope_user_access sua')
            ->select('sua.id, sua.scope_id, sua.user_id, sua.role_id,
                      u.username, u.full_name,
                      r.name AS role_name')
            ->join('users u',  'u.id  = sua.user_id', 'left')
            ->join('roles r',  'r.id  = sua.role_id', 'left')
            ->where('sua.scope_id', $scopeId)
            ->orderBy('sua.id', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Grant a user or role access to a restricted scope.
     *
     * @param int      $scopeId
     * @param int|null $userId
     * @param int|null $roleId
     * @param int      $grantedBy
     * @return int|false Insert ID or false on duplicate / error
     */
    public function grantAccess(int $scopeId, ?int $userId, ?int $roleId, int $grantedBy)
    {
        $db = \Config\Database::connect();

        // Prevent duplicates
        $existing = $db->table('scope_user_access')
            ->where('scope_id', $scopeId)
            ->where('user_id',  $userId)
            ->where('role_id',  $roleId)
            ->countAllResults();

        if ($existing > 0) {
            return false;
        }

        $db->table('scope_user_access')->insert([
            'scope_id'   => $scopeId,
            'user_id'    => $userId,
            'role_id'    => $roleId,
            'granted_by' => $grantedBy,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $db->insertID();
    }

    /**
     * Revoke an access entry by its ID.
     *
     * @param int $accessId  Row ID in scope_user_access
     * @return bool
     */
    public function revokeAccess(int $accessId): bool
    {
        $db = \Config\Database::connect();
        return $db->table('scope_user_access')->delete(['id' => $accessId]) !== false;
    }

    /**
     * Set is_restricted flag on a scope.
     *
     * @param int  $scopeId
     * @param bool $restricted
     * @return bool
     */
    public function setRestricted(int $scopeId, bool $restricted): bool
    {
        return $this->update($scopeId, ['is_restricted' => $restricted ? 1 : 0]) !== false;
    }

    /**
     * Get all restricted scope IDs a specific user has been individually granted access to.
     * Returns only user-level grants (not role-level) so we can show the checkboxes
     * in the user-edit form.
     *
     * @param int $userId
     * @return array Array of scope IDs
     */
    public function getUserGrantedScopeIds(int $userId): array
    {
        $db = \Config\Database::connect();
        $rows = $db->table('scope_user_access')
            ->select('scope_id')
            ->where('user_id', $userId)
            ->where('role_id IS NULL', null, false)
            ->get()
            ->getResultArray();

        return array_column($rows, 'scope_id');
    }

    /**
     * Get all restricted scope IDs a specific role has been granted access to.
     *
     * @param int $roleId
     * @return array Array of scope IDs
     */
    public function getRoleGrantedScopeIds(int $roleId): array
    {
        $db = \Config\Database::connect();
        $rows = $db->table('scope_user_access')
            ->select('scope_id')
            ->where('role_id', $roleId)
            ->where('user_id IS NULL', null, false)
            ->get()
            ->getResultArray();

        return array_column($rows, 'scope_id');
    }

    /**
     * Get all restricted scopes (flat list) — used to build the checkbox list
     * in the user-edit form.
     *
     * @return array
     */
    public function getRestrictedScopes(): array
    {
        return $this->where('is_restricted', 1)
            ->where('is_active', 1)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    /**
     * Sync user-level scope access: replace the user's individual grants with
     * the supplied scope IDs.  Role-level grants are left untouched.
     *
     * @param int   $userId
     * @param array $scopeIds Scope IDs to grant (empty = revoke all individual grants)
     * @param int   $grantedBy
     * @return void
     */
    public function syncUserScopeAccess(int $userId, array $scopeIds, int $grantedBy): void
    {
        $db = \Config\Database::connect();

        // Remove all existing individual (user-level) grants for this user
        $db->table('scope_user_access')
            ->where('user_id', $userId)
            ->where('role_id IS NULL', null, false)
            ->delete();

        // Re-insert selected scopes
        foreach ($scopeIds as $scopeId) {
            $scopeId = (int) $scopeId;
            if ($scopeId > 0) {
                $db->table('scope_user_access')->insert([
                    'scope_id'   => $scopeId,
                    'user_id'    => $userId,
                    'role_id'    => null,
                    'granted_by' => $grantedBy,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    /**
     * Sync role-level scope access: replace the role's grants with
     * the supplied scope IDs.  User-level grants are left untouched.
     *
     * @param int   $roleId
     * @param array $scopeIds Scope IDs to grant (empty = revoke all role grants)
     * @param int   $grantedBy
     * @return void
     */
    public function syncRoleScopeAccess(int $roleId, array $scopeIds, int $grantedBy): void
    {
        $db = \Config\Database::connect();

        // Remove all existing role-level grants for this role
        $db->table('scope_user_access')
            ->where('role_id', $roleId)
            ->where('user_id IS NULL', null, false)
            ->delete();

        // Re-insert selected scopes
        foreach ($scopeIds as $scopeId) {
            $scopeId = (int) $scopeId;
            if ($scopeId > 0) {
                $db->table('scope_user_access')->insert([
                    'scope_id'   => $scopeId,
                    'user_id'    => null,
                    'role_id'    => $roleId,
                    'granted_by' => $grantedBy,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }
}
