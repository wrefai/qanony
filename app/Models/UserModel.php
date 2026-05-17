<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table         = 'users';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'username', 'email', 'password_hash', 'full_name', 'role_id',
        'is_active', 'force_password_change', 'failed_login_attempts',
        'locked_until', 'last_login_at', 'last_login_ip',
    ];
    protected $returnType = 'array';

    protected $validationRules = [
        'username'  => 'required|min_length[3]|max_length[50]|alpha_numeric_punct|is_unique[users.username,id,{id}]',
        'email'     => 'required|valid_email|max_length[255]|is_unique[users.email,id,{id}]',
        'full_name' => 'required|min_length[2]|max_length[150]',
        'role_id'   => 'required|integer|is_not_unique[roles.id]',
    ];

    /**
     * Find user by username or email for login.
     */
    public function findByLogin(string $login): ?array
    {
        return $this->where('username', $login)
            ->orWhere('email', $login)
            ->first();
    }

    /**
     * Check if account is locked.
     */
    public function isLocked(array $user): bool
    {
        if (empty($user['locked_until'])) {
            return false;
        }

        return strtotime($user['locked_until']) > time();
    }

    /**
     * Record failed login attempt.
     */
    public function recordFailedLogin(int $userId): void
    {
        $user = $this->find($userId);
        $attempts = ($user['failed_login_attempts'] ?? 0) + 1;
        $maxAttempts = (int) env('auth.maxLoginAttempts', 5);
        $lockoutDuration = (int) env('auth.lockoutDuration', 900);

        $data = ['failed_login_attempts' => $attempts];

        if ($attempts >= $maxAttempts) {
            $data['locked_until'] = date('Y-m-d H:i:s', time() + $lockoutDuration);
        }

        $this->update($userId, $data);
    }

    /**
     * Reset failed login attempts.
     */
    public function resetFailedLogins(int $userId): void
    {
        $this->update($userId, [
            'failed_login_attempts' => 0,
            'locked_until'          => null,
            'last_login_at'         => date('Y-m-d H:i:s'),
            'last_login_ip'         => service('request')->getIPAddress(),
        ]);
    }

    /**
     * Get all permissions for this user (role + user-level overrides).
     */
    public function getPermissions(int $userId): array
    {
        $user = $this->find($userId);
        if (! $user) {
            return [];
        }

        // Get role permissions
        $rolePerms = $this->db->table('role_permissions')
            ->select('permissions.name')
            ->join('permissions', 'permissions.id = role_permissions.permission_id')
            ->where('role_permissions.role_id', $user['role_id'])
            ->get()
            ->getResultArray();

        $permissions = array_column($rolePerms, 'name');

        // Apply user-level overrides
        $userPerms = $this->db->table('user_permissions')
            ->select('permissions.name, user_permissions.granted')
            ->join('permissions', 'permissions.id = user_permissions.permission_id')
            ->where('user_permissions.user_id', $userId)
            ->get()
            ->getResultArray();

        foreach ($userPerms as $up) {
            if ($up['granted']) {
                $permissions[] = $up['name'];
            } else {
                $permissions = array_filter($permissions, fn ($p) => $p !== $up['name']);
            }
        }

        return array_unique(array_values($permissions));
    }

    /**
     * Check if user has specific permission.
     */
    public function hasPermission(int $userId, string $permission): bool
    {
        return in_array($permission, $this->getPermissions($userId), true);
    }

    /**
     * Get user with role info.
     */
    public function getWithRole(int $userId): ?array
    {
        return $this->select('users.*, roles.name as role_name')
            ->join('roles', 'roles.id = users.role_id')
            ->find($userId);
    }

    /**
     * Count admin users (to prevent deleting last admin).
     */
    public function countAdmins(): int
    {
        return $this->where('role_id', 1)->where('is_active', 1)->countAllResults();
    }
}
