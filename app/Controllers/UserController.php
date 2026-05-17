<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\RoleModel;
use App\Models\AuditLogModel;
use App\Models\SearchScopeModel;

class UserController extends BaseController
{
    protected UserModel $userModel;
    protected RoleModel $roleModel;
    protected AuditLogModel $auditModel;
    protected SearchScopeModel $scopeModel;

    public function __construct()
    {
        $this->userModel  = new UserModel();
        $this->roleModel  = new RoleModel();
        $this->auditModel = new AuditLogModel();
        $this->scopeModel = new SearchScopeModel();
    }

    public function index()
    {
        return view('users/index', $this->viewData([
            'title' => lang('App.user_management'),
            'roles' => $this->roleModel->findAll(),
        ]));
    }

    /**
     * AJAX data endpoint for DataTables-like table.
     */
    public function data()
    {
        $page    = (int) ($this->request->getGet('page') ?? 1);
        $perPage = (int) ($this->request->getGet('per_page') ?? 15);
        $search  = $this->request->getGet('search');
        $roleId  = $this->request->getGet('role_id');
        $status  = $this->request->getGet('status');

        $builder = $this->userModel->builder();
        $builder->select('users.*, roles.name as role_name');
        $builder->join('roles', 'roles.id = users.role_id');

        if ($search) {
            $builder->groupStart()
                ->like('users.username', $search)
                ->orLike('users.full_name', $search)
                ->orLike('users.email', $search)
                ->groupEnd();
        }
        if ($roleId) {
            $builder->where('users.role_id', $roleId);
        }
        if ($status !== null && $status !== '') {
            $builder->where('users.is_active', (int) $status);
        }

        $total = $builder->countAllResults(false);
        $users = $builder->orderBy('users.created_at', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        return $this->jsonResponse([
            'items'       => $users,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ]);
    }

    public function create()
    {
        return view('users/form', $this->viewData([
            'title'            => lang('App.add_user'),
            'roles'            => $this->roleModel->findAll(),
            'user'             => null,
            'restrictedScopes' => [],
            'userScopeIds'     => [],
        ]));
    }

    public function store()
    {
        $minLength = (int) env('auth.minPasswordLength', 8);

        $rules = [
            'username'  => 'required|min_length[3]|max_length[50]|alpha_numeric_punct|is_unique[users.username]',
            'email'     => 'required|valid_email|is_unique[users.email]',
            'full_name' => 'required|min_length[2]|max_length[150]',
            'password'  => "required|min_length[{$minLength}]",
            'role_id'   => 'required|integer',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $userId = $this->userModel->insert([
            'username'              => $this->request->getPost('username'),
            'email'                 => $this->request->getPost('email'),
            'full_name'             => $this->request->getPost('full_name'),
            'password_hash'         => password_hash($this->request->getPost('password'), PASSWORD_BCRYPT, ['cost' => 12]),
            'role_id'               => $this->request->getPost('role_id'),
            'is_active'             => $this->request->getPost('is_active') ? 1 : 0,
            'force_password_change' => 1,
        ]);

        $this->auditModel->log('user_created', "Created user: {$this->request->getPost('username')}", 'user', $userId);

        return redirect()->to(site_url('users'))->with('success', lang('App.user_created'));
    }

    public function edit(int $id)
    {
        $user = $this->userModel->getWithRole($id);
        if (! $user) {
            return redirect()->to(site_url('users'))->with('error', lang('App.not_found'));
        }

        return view('users/form', $this->viewData([
            'title'            => lang('App.edit_user'),
            'roles'            => $this->roleModel->findAll(),
            'user'             => $user,
            'restrictedScopes' => $this->scopeModel->getRestrictedScopes(),
            'userScopeIds'     => $this->scopeModel->getUserGrantedScopeIds($id),
        ]));
    }

    public function update(int $id)
    {
        $user = $this->userModel->find($id);
        if (! $user) {
            return redirect()->to(site_url('users'))->with('error', lang('App.not_found'));
        }

        $rules = [
            'username'  => "required|min_length[3]|max_length[50]|is_unique[users.username,id,{$id}]",
            'email'     => "required|valid_email|is_unique[users.email,id,{$id}]",
            'full_name' => 'required|min_length[2]|max_length[150]',
            'role_id'   => 'required|integer',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $oldValues = [
            'username'  => $user['username'],
            'email'     => $user['email'],
            'full_name' => $user['full_name'],
            'role_id'   => $user['role_id'],
            'is_active' => $user['is_active'],
        ];

        $this->userModel->update($id, [
            'username'  => $this->request->getPost('username'),
            'email'     => $this->request->getPost('email'),
            'full_name' => $this->request->getPost('full_name'),
            'role_id'   => $this->request->getPost('role_id'),
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
        ]);

        // Sync individual scope access grants
        $selectedScopes = $this->request->getPost('scope_access') ?? [];
        $this->scopeModel->syncUserScopeAccess(
            $id,
            is_array($selectedScopes) ? $selectedScopes : [],
            (int) session()->get('user_id')
        );

        $this->auditModel->log(
            'user_updated',
            "Updated user: {$user['username']}",
            'user',
            $id,
            $oldValues,
            $this->request->getPost()
        );

        return redirect()->to(site_url('users'))->with('success', lang('App.user_updated'));
    }

    public function delete(int $id)
    {
        $user = $this->userModel->find($id);
        if (! $user) {
            return redirect()->to(site_url('users'))->with('error', lang('App.not_found'));
        }

        // Cannot delete self
        if ($id === (int) session()->get('user_id')) {
            return redirect()->to(site_url('users'))->with('error', lang('App.cannot_delete_self'));
        }

        // Cannot delete last admin
        if ($user['role_id'] == 1 && $this->userModel->countAdmins() <= 1) {
            return redirect()->to(site_url('users'))->with('error', lang('App.cannot_delete_last_admin'));
        }

        $this->userModel->delete($id);
        $this->auditModel->log('user_deleted', "Deleted user: {$user['username']}", 'user', $id);

        return redirect()->to(site_url('users'))->with('success', lang('App.user_deleted'));
    }

    public function toggleStatus(int $id)
    {
        $user = $this->userModel->find($id);
        if (! $user) {
            return $this->jsonResponse(['error' => lang('App.not_found')], 404);
        }

        if ($id === (int) session()->get('user_id')) {
            return $this->jsonResponse(['error' => lang('App.cannot_delete_self')], 403);
        }

        $newStatus = $user['is_active'] ? 0 : 1;
        $this->userModel->update($id, ['is_active' => $newStatus]);

        $action = $newStatus ? 'activated' : 'deactivated';
        $this->auditModel->log('user_updated', "User {$user['username']} {$action}", 'user', $id);

        return $this->jsonResponse(['success' => true, 'is_active' => $newStatus]);
    }

    public function resetPassword(int $id)
    {
        $user = $this->userModel->find($id);
        if (! $user) {
            return $this->jsonResponse(['error' => lang('App.not_found')], 404);
        }

        $tempPassword = bin2hex(random_bytes(6)); // 12 char temp password
        $this->userModel->update($id, [
            'password_hash'         => password_hash($tempPassword, PASSWORD_BCRYPT, ['cost' => 12]),
            'force_password_change' => 1,
            'failed_login_attempts' => 0,
            'locked_until'          => null,
        ]);

        $this->auditModel->log('password_reset', "Password reset for: {$user['username']}", 'user', $id);

        return $this->jsonResponse([
            'success'       => true,
            'temp_password' => $tempPassword,
            'message'       => lang('App.password_reset_done'),
        ]);
    }
}
