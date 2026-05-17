<?php

namespace App\Controllers;

use App\Models\RoleModel;
use App\Models\PermissionModel;
use App\Models\AuditLogModel;
use App\Models\SearchScopeModel;

class RoleController extends BaseController
{
    protected RoleModel $roleModel;
    protected PermissionModel $permModel;
    protected AuditLogModel $auditModel;
    protected SearchScopeModel $scopeModel;

    public function __construct()
    {
        $this->roleModel  = new RoleModel();
        $this->permModel  = new PermissionModel();
        $this->auditModel = new AuditLogModel();
        $this->scopeModel = new SearchScopeModel();
    }

    public function index()
    {
        $roles = $this->roleModel->findAll();
        foreach ($roles as &$role) {
            $role['user_count'] = $this->roleModel->countUsers($role['id']);
            $role['permissions'] = $this->roleModel->getPermissions($role['id']);
        }

        return view('roles/index', $this->viewData([
            'title' => lang('App.role_management'),
            'roles' => $roles,
        ]));
    }

    public function create()
    {
        return view('roles/form', $this->viewData([
            'title'              => lang('App.add_role'),
            'role'               => null,
            'groupedPermissions' => $this->permModel->getGrouped(),
            'rolePermissions'    => [],
            'restrictedScopes'   => $this->scopeModel->getRestrictedScopes(),
            'roleScopeIds'       => [],
        ]));
    }

    public function store()
    {
        $rules = [
            'name'        => 'required|min_length[2]|max_length[50]|is_unique[roles.name]',
            'description' => 'permit_empty|max_length[255]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $roleId = $this->roleModel->insert([
            'name'        => $this->request->getPost('name'),
            'description' => $this->request->getPost('description'),
            'is_system'   => 0,
        ]);

        $permissions = $this->request->getPost('permissions') ?? [];
        $this->roleModel->syncPermissions($roleId, $permissions);

        $selectedScopes = $this->request->getPost('scope_access') ?? [];
        $this->scopeModel->syncRoleScopeAccess(
            $roleId,
            is_array($selectedScopes) ? $selectedScopes : [],
            (int) session()->get('user_id')
        );

        $this->auditModel->log('role_created', "Created role: {$this->request->getPost('name')}", 'role', $roleId);

        return redirect()->to(site_url('roles'))->with('success', lang('App.role_created'));
    }

    public function edit(int $id)
    {
        $role = $this->roleModel->find($id);
        if (! $role) {
            return redirect()->to(site_url('roles'))->with('error', lang('App.not_found'));
        }

        $rolePermissions = array_column($this->roleModel->getPermissions($id), 'id');

        return view('roles/form', $this->viewData([
            'title'              => lang('App.edit_role'),
            'role'               => $role,
            'groupedPermissions' => $this->permModel->getGrouped(),
            'rolePermissions'    => $rolePermissions,
            'restrictedScopes'   => $this->scopeModel->getRestrictedScopes(),
            'roleScopeIds'       => $this->scopeModel->getRoleGrantedScopeIds($id),
        ]));
    }

    public function update(int $id)
    {
        $role = $this->roleModel->find($id);
        if (! $role) {
            return redirect()->to(site_url('roles'))->with('error', lang('App.not_found'));
        }

        $rules = [
            'name'        => "required|min_length[2]|max_length[50]|is_unique[roles.name,id,{$id}]",
            'description' => 'permit_empty|max_length[255]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $this->roleModel->update($id, [
            'name'        => $this->request->getPost('name'),
            'description' => $this->request->getPost('description'),
        ]);

        $permissions = $this->request->getPost('permissions') ?? [];
        $this->roleModel->syncPermissions($id, $permissions);

        $selectedScopes = $this->request->getPost('scope_access') ?? [];
        $this->scopeModel->syncRoleScopeAccess(
            $id,
            is_array($selectedScopes) ? $selectedScopes : [],
            (int) session()->get('user_id')
        );

        $this->auditModel->log('role_updated', "Updated role: {$role['name']}", 'role', $id);

        return redirect()->to(site_url('roles'))->with('success', lang('App.role_updated'));
    }

    public function delete(int $id)
    {
        $role = $this->roleModel->find($id);
        if (! $role) {
            return redirect()->to(site_url('roles'))->with('error', lang('App.not_found'));
        }

        if ($role['is_system']) {
            return redirect()->to(site_url('roles'))->with('error', lang('App.cannot_delete_system_role'));
        }

        if ($this->roleModel->countUsers($id) > 0) {
            return redirect()->to(site_url('roles'))->with('error', lang('App.role_has_users'));
        }

        $this->roleModel->delete($id);
        $this->auditModel->log('role_deleted', "Deleted role: {$role['name']}", 'role', $id);

        return redirect()->to(site_url('roles'))->with('success', lang('App.role_deleted'));
    }
}
