<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUsersTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'username' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'unique'     => true,
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'unique'     => true,
            ],
            'password_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'full_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'role_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'is_active' => [
                'type'    => 'TINYINT',
                'default' => 1,
            ],
            'force_password_change' => [
                'type'    => 'TINYINT',
                'default' => 0,
                'comment' => 'Require password change on next login',
            ],
            'failed_login_attempts' => [
                'type'    => 'INT',
                'default' => 0,
            ],
            'locked_until' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'last_login_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'last_login_ip' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('role_id', 'roles', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('users');
    }

    public function down()
    {
        $this->forge->dropTable('users');
    }
}
