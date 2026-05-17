<?php

namespace App\Models;

use CodeIgniter\Model;

class DefenseModel extends Model
{
    protected $table         = 'defenses';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = ['principle_id', 'title', 'description', 'legal_basis'];
    protected $returnType    = 'array';

    /**
     * Get defenses by principle.
     */
    public function getByPrinciple(int $principleId): array
    {
        return $this->where('principle_id', $principleId)->findAll();
    }
}
