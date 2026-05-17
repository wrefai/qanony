<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class InitialSeeder extends Seeder
{
    public function run()
    {
        $this->seedRoles();
        $this->seedPermissions();
        $this->seedRolePermissions();
        $this->seedAdminUser();
    }

    private function seedRoles(): void
    {
        // Skip if roles already exist
        if ($this->db->table('roles')->countAllResults() > 0) {
            return;
        }

        $roles = [
            [
                'id'          => 1,
                'name'        => 'admin',
                'description' => 'Full system access',
                'is_system'   => 1,
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ],
            [
                'id'          => 2,
                'name'        => 'manager',
                'description' => 'Management access with limited admin features',
                'is_system'   => 1,
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ],
            [
                'id'          => 3,
                'name'        => 'user',
                'description' => 'Standard user access',
                'is_system'   => 1,
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ],
        ];

        $this->db->table('roles')->insertBatch($roles);
    }

    private function seedPermissions(): void
    {
        // Skip only if the core document permissions already exist.
        // Do NOT skip based on count alone — the AddScopeManagePermission migration
        // may have pre-inserted 'scopes.manage', which would cause count > 0
        // and prevent the full permission set from being seeded.
        if ($this->db->table('permissions')->where('name', 'documents.read')->countAllResults() > 0) {
            return;
        }

        $permissions = [
            // Users
            ['name' => 'users.read',    'group_name' => 'users',     'description' => 'View users list'],
            ['name' => 'users.create',  'group_name' => 'users',     'description' => 'Create new users'],
            ['name' => 'users.update',  'group_name' => 'users',     'description' => 'Edit users'],
            ['name' => 'users.delete',  'group_name' => 'users',     'description' => 'Delete users'],
            // Roles
            ['name' => 'roles.read',    'group_name' => 'roles',     'description' => 'View roles'],
            ['name' => 'roles.create',  'group_name' => 'roles',     'description' => 'Create roles'],
            ['name' => 'roles.update',  'group_name' => 'roles',     'description' => 'Edit roles'],
            ['name' => 'roles.delete',  'group_name' => 'roles',     'description' => 'Delete roles'],
            // Documents
            ['name' => 'documents.read',   'group_name' => 'documents', 'description' => 'View documents'],
            ['name' => 'documents.create', 'group_name' => 'documents', 'description' => 'Index/upload documents'],
            ['name' => 'documents.update', 'group_name' => 'documents', 'description' => 'Edit document metadata'],
            ['name' => 'documents.delete', 'group_name' => 'documents', 'description' => 'Delete documents'],
            ['name' => 'documents.export', 'group_name' => 'documents', 'description' => 'Export documents'],
            // Search
            ['name' => 'search.use',       'group_name' => 'search',    'description' => 'Use search functionality'],
            ['name' => 'search.advanced',  'group_name' => 'search',    'description' => 'Use advanced search filters'],
            // Principles
            ['name' => 'principles.read',   'group_name' => 'principles', 'description' => 'View legal principles'],
            ['name' => 'principles.create', 'group_name' => 'principles', 'description' => 'Add legal principles'],
            ['name' => 'principles.update', 'group_name' => 'principles', 'description' => 'Edit legal principles'],
            ['name' => 'principles.delete', 'group_name' => 'principles', 'description' => 'Delete legal principles'],
            // Audit
            ['name' => 'audit.read',       'group_name' => 'audit',     'description' => 'View audit logs'],
            // Settings
            ['name' => 'settings.read',    'group_name' => 'settings',  'description' => 'View settings'],
            ['name' => 'settings.update',  'group_name' => 'settings',  'description' => 'Modify settings'],
        ];

        $this->db->table('permissions')->insertBatch($permissions);
    }

    private function seedRolePermissions(): void
    {
        // Skip only if admin already has documents.read assigned.
        // Do NOT skip based on total count — the migration may have pre-inserted
        // a couple of rows for scopes.manage, triggering a false "already seeded".
        $adminHasDocs = $this->db->table('role_permissions rp')
            ->join('permissions p', 'p.id = rp.permission_id')
            ->where('rp.role_id', 1)
            ->where('p.name', 'documents.read')
            ->countAllResults() > 0;

        if ($adminHasDocs) {
            return;
        }

        // Get all permission IDs
        $allPerms = $this->db->table('permissions')->get()->getResultArray();
        $permMap = [];
        foreach ($allPerms as $p) {
            $permMap[$p['name']] = (int) $p['id'];
        }

        // Admin: ALL permissions
        $adminPerms = [];
        foreach ($permMap as $permId) {
            $adminPerms[] = ['role_id' => 1, 'permission_id' => $permId];
        }
        $this->db->table('role_permissions')->insertBatch($adminPerms);

        // Manager: everything except users.delete, roles.create/update/delete, settings.update
        $managerExclude = ['users.delete', 'roles.create', 'roles.update', 'roles.delete', 'settings.update'];
        $managerPerms = [];
        foreach ($permMap as $name => $permId) {
            if (! in_array($name, $managerExclude, true)) {
                $managerPerms[] = ['role_id' => 2, 'permission_id' => $permId];
            }
        }
        $this->db->table('role_permissions')->insertBatch($managerPerms);

        // User: read-only + search + documents.read + principles.read
        $userAllow = [
            'documents.read', 'search.use', 'search.advanced',
            'principles.read', 'settings.read',
        ];
        $userPerms = [];
        foreach ($userAllow as $name) {
            if (isset($permMap[$name])) {
                $userPerms[] = ['role_id' => 3, 'permission_id' => $permMap[$name]];
            }
        }
        $this->db->table('role_permissions')->insertBatch($userPerms);
    }

    private function seedAdminUser(): void
    {
        // Skip if admin user already exists
        if ($this->db->table('users')->where('username', 'admin')->countAllResults() > 0) {
            return;
        }

        $this->db->table('users')->insert([
            'username'              => 'admin',
            'email'                 => 'admin@qanony.local',
            'password_hash'         => password_hash('Admin@123', PASSWORD_BCRYPT, ['cost' => 12]),
            'full_name'             => 'مدير النظام',
            'role_id'               => 1,
            'is_active'             => 1,
            'force_password_change' => 1,
            'created_at'            => date('Y-m-d H:i:s'),
            'updated_at'            => date('Y-m-d H:i:s'),
        ]);
    }
}
