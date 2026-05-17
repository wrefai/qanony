<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds scope-level access control:
 *
 * 1. `search_scopes.is_restricted` — when 1, only allowed users/roles can see this scope.
 * 2. `scope_user_access`           — explicit allow-list: which users or roles may access
 *                                    a restricted scope.
 */
class AddScopeAccessControl extends Migration
{
    public function up()
    {
        // ── 1. Add is_restricted flag to search_scopes ─────────────────
        $this->forge->addColumn('search_scopes', [
            'is_restricted' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'null'       => false,
                'comment'    => '1 = only users listed in scope_user_access may see this scope',
                'after'      => 'is_active',
            ],
        ]);

        // ── 2. Create scope_user_access ──────────────────────────────────
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'scope_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            // Either user_id OR role_id must be set (not both, not neither)
            'user_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'role_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'granted_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
                'comment'  => 'Admin who granted this access',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['scope_id', 'user_id']);
        $this->forge->addKey(['scope_id', 'role_id']);
        $this->forge->addForeignKey('scope_id',   'search_scopes', 'id', 'CASCADE',  'CASCADE');
        $this->forge->addForeignKey('user_id',    'users',         'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('role_id',    'roles',         'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('granted_by', 'users',         'id', 'SET NULL', 'SET NULL');
        $this->forge->createTable('scope_user_access');
    }

    public function down()
    {
        $this->forge->dropTable('scope_user_access');
        $this->forge->dropColumn('search_scopes', 'is_restricted');
    }
}
