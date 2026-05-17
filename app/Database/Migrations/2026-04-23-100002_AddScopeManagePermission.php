<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds the `scopes.manage` permission and assigns it to the admin role.
 * Also assigns it to the manager role (they can manage scope access).
 */
class AddScopeManagePermission extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        // Insert permission if it doesn't already exist
        $exists = $db->table('permissions')->where('name', 'scopes.manage')->countAllResults();
        if (!$exists) {
            $db->table('permissions')->insert([
                'name'        => 'scopes.manage',
                'group_name'  => 'scopes',
                'description' => 'Manage scope visibility and access control',
            ]);
        }

        // Get the permission ID
        $perm = $db->table('permissions')->where('name', 'scopes.manage')->get()->getRowArray();
        if (!$perm) {
            return;
        }
        $permId = (int) $perm['id'];

        // Assign to admin (role 1) and manager (role 2) only if those roles exist
        foreach ([1, 2] as $roleId) {
            // Guard: skip if the role row doesn't exist (avoids FK constraint error
            // when this migration runs before the seeder populates the roles table)
            $roleExists = $db->table('roles')->where('id', $roleId)->countAllResults();
            if (! $roleExists) {
                continue;
            }

            $already = $db->table('role_permissions')
                ->where('role_id', $roleId)
                ->where('permission_id', $permId)
                ->countAllResults();
            if (!$already) {
                $db->table('role_permissions')->insert([
                    'role_id'       => $roleId,
                    'permission_id' => $permId,
                ]);
            }
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();

        $perm = $db->table('permissions')->where('name', 'scopes.manage')->get()->getRowArray();
        if (!$perm) {
            return;
        }

        $db->table('role_permissions')->where('permission_id', $perm['id'])->delete();
        $db->table('permissions')->where('id', $perm['id'])->delete();
    }
}
