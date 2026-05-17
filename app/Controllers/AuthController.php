<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\AuditLogModel;

class AuthController extends BaseController
{
    protected UserModel $userModel;
    protected AuditLogModel $auditModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->auditModel = new AuditLogModel();
    }

    public function login()
    {
        if (session()->get('logged_in')) {
            return redirect()->to(site_url('dashboard'));
        }

        return view('auth/login', $this->viewData());
    }

    public function attemptLogin()
    {
        $rules = [
            'login'    => 'required|min_length[3]',
            'password' => 'required',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $login    = $this->request->getPost('login');
        $password = $this->request->getPost('password');

        // Sanitised version for audit log: strip tags, control chars, limit length.
        $loginSafe = mb_substr(strip_tags((string) $login), 0, 100);

        $user = $this->userModel->findByLogin($login);

        if (! $user) {
            try { $this->auditModel->log('login_failed', "Failed login for: {$loginSafe}"); } catch (\Throwable $e) { log_message('warning', 'AuditLog error: ' . $e->getMessage()); }

            return redirect()->back()->withInput()->with('error', lang('App.login_failed'));
        }

        // Check if account is locked
        if ($this->userModel->isLocked($user)) {
            $remaining = (int) ceil((strtotime($user['locked_until']) - time()) / 60);
            try { $this->auditModel->log('login_failed', "Locked account login attempt: {$loginSafe}", 'user', $user['id']); } catch (\Throwable $e) { log_message('warning', 'AuditLog error: ' . $e->getMessage()); }

            return redirect()->back()->withInput()->with('error', lang('App.account_locked', [$remaining]));
        }

        // Check if account is active
        if (! $user['is_active']) {
            return redirect()->back()->withInput()->with('error', lang('App.account_disabled'));
        }

        // Verify password
        if (! password_verify($password, $user['password_hash'])) {
            $this->userModel->recordFailedLogin($user['id']);
            try { $this->auditModel->log('login_failed', "Wrong password for: {$loginSafe}", 'user', $user['id']); } catch (\Throwable $e) { log_message('warning', 'AuditLog error: ' . $e->getMessage()); }

            return redirect()->back()->withInput()->with('error', lang('App.login_failed'));
        }

        // Successful login
        $this->userModel->resetFailedLogins($user['id']);

        // Load permissions
        $permissions = $this->userModel->getPermissions($user['id']);

        // Get role name
        $role = (new \App\Models\RoleModel())->find($user['role_id']);

        // Set session
        $sessionData = [
            'user_id'               => $user['id'],
            'username'              => $user['username'],
            'full_name'             => $user['full_name'],
            'email'                 => $user['email'],
            'role_id'               => $user['role_id'],
            'role_name'             => $role['name'] ?? 'user',
            'permissions'           => $permissions,
            'logged_in'             => true,
            'force_password_change' => (bool) $user['force_password_change'],
        ];
        session()->regenerate();
        session()->set($sessionData);

        try { $this->auditModel->log('login_success', "User {$user['username']} logged in", 'user', $user['id']); } catch (\Throwable $e) { log_message('warning', 'AuditLog error: ' . $e->getMessage()); }

        if ($user['force_password_change']) {
            return redirect()->to(site_url('auth/change-password'));
        }

        return redirect()->to(site_url('dashboard'))->with('success', lang('App.login_success'));
    }

    public function logout()
    {
        $userId = session()->get('user_id');
        $username = session()->get('username');

        if ($userId) {
            try { $this->auditModel->log('logout', "User {$username} logged out", 'user', $userId); } catch (\Throwable $e) { log_message('warning', 'AuditLog error: ' . $e->getMessage()); }
        }

        session()->destroy();

        return redirect()->to(site_url('auth/login'));
    }

    public function changePassword()
    {
        return view('auth/change_password', $this->viewData());
    }

    public function attemptChangePassword()
    {
        $minLength = (int) env('auth.minPasswordLength', 8);

        $rules = [
            'current_password' => 'required',
            'new_password'     => "required|min_length[{$minLength}]",
            'confirm_password' => 'required|matches[new_password]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $user = $this->userModel->find(session()->get('user_id'));
        $currentPassword = $this->request->getPost('current_password');
        $newPassword = $this->request->getPost('new_password');

        // Verify current password
        if (! password_verify($currentPassword, $user['password_hash'])) {
            return redirect()->back()->with('error', lang('App.password_wrong_current'));
        }

        // Validate complexity
        if (! $this->validatePasswordComplexity($newPassword)) {
            return redirect()->back()->with('error', lang('App.password_requirements'));
        }

        // Update password
        $this->userModel->update($user['id'], [
            'password_hash'         => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]),
            'force_password_change' => 0,
        ]);

        session()->set('force_password_change', false);

        try { $this->auditModel->log('password_changed', 'User changed their password', 'user', $user['id']); } catch (\Throwable $e) { log_message('warning', 'AuditLog error: ' . $e->getMessage()); }

        return redirect()->to(site_url('dashboard'))->with('success', lang('App.password_changed'));
    }

    /**
     * Validate password complexity: uppercase, lowercase, number, special char.
     */
    private function validatePasswordComplexity(string $password): bool
    {
        return preg_match('/[A-Z]/', $password)
            && preg_match('/[a-z]/', $password)
            && preg_match('/[0-9]/', $password)
            && preg_match('/[^A-Za-z0-9]/', $password);
    }
}
