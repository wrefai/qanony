<?php

namespace App\Models;

use CodeIgniter\Model;

class PermissionModel extends Model
{
    protected $table         = 'permissions';
    protected $primaryKey    = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = ['name', 'group_name', 'description'];
    protected $returnType    = 'array';

    /**
     * Get permissions grouped by group_name.
     */
    public function getGrouped(): array
    {
        $perms = $this->orderBy('group_name')->orderBy('name')->findAll();
        $grouped = [];
        foreach ($perms as $p) {
            $grouped[$p['group_name']][] = $p;
        }

        return $grouped;
    }
}
