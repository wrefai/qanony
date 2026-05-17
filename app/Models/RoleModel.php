<?php

namespace App\Models;

use CodeIgniter\Model;

class RoleModel extends Model
{
    protected $table         = 'roles';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = ['name', 'description', 'is_system'];
    protected $returnType    = 'array';

    protected $validationRules = [
        'name' => 'required|min_length[2]|max_length[50]|is_unique[roles.name,id,{id}]',
    ];

    /**
     * Get role with its permissions.
     */
    public function getWithPermissions(int $roleId): ?array
    {
        $role = $this->find($roleId);
        if (! $role) {
            return null;
        }

        $role['permissions'] = $this->getPermissions($roleId);

        return $role;
    }

    /**
     * Get permissions for a role.
     */
    public function getPermissions(int $roleId): array
    {
        return $this->db->table('role_permissions')
            ->select('permissions.*')
            ->join('permissions', 'permissions.id = role_permissions.permission_id')
            ->where('role_permissions.role_id', $roleId)
            ->get()
            ->getResultArray();
    }

    /**
     * Sync permissions for a role.
     */
    public function syncPermissions(int $roleId, array $permissionIds): void
    {
        $this->db->table('role_permissions')->where('role_id', $roleId)->delete();

        if (! empty($permissionIds)) {
            $batch = [];
            foreach ($permissionIds as $pid) {
                $batch[] = ['role_id' => $roleId, 'permission_id' => (int) $pid];
            }
            $this->db->table('role_permissions')->insertBatch($batch);
        }
    }

    /**
     * Count users in a role.
     */
    public function countUsers(int $roleId): int
    {
        return $this->db->table('users')->where('role_id', $roleId)->countAllResults();
    }
}
