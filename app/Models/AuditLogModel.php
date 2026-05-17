<?php

namespace App\Models;

use CodeIgniter\Model;

class AuditLogModel extends Model
{
    protected $table         = 'audit_logs';
    protected $primaryKey    = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'user_id', 'action', 'entity_type', 'entity_id',
        'description', 'old_values', 'new_values',
        'ip_address', 'user_agent', 'created_at',
    ];
    protected $returnType = 'array';

    /**
     * Log an action.
     */
    public function log(
        string $action,
        ?string $description = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        $request = service('request');
        $session = session();

        $this->insert([
            'user_id'     => $session->get('user_id'),
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'description' => $description,
            'old_values'  => $oldValues ? json_encode($oldValues) : null,
            'new_values'  => $newValues ? json_encode($newValues) : null,
            'ip_address'  => $request->getIPAddress(),
            'user_agent'  => method_exists($request, 'getUserAgent') ? $request->getUserAgent()->getAgentString() : 'CLI',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get filtered audit logs with pagination.
     */
    public function getFiltered(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $builder = $this->builder();
        $builder->select('audit_logs.*, users.username, users.full_name');
        $builder->join('users', 'users.id = audit_logs.user_id', 'left');

        if (! empty($filters['action'])) {
            $builder->where('audit_logs.action', $filters['action']);
        }
        if (! empty($filters['user_id'])) {
            $builder->where('audit_logs.user_id', $filters['user_id']);
        }
        if (! empty($filters['entity_type'])) {
            $builder->where('audit_logs.entity_type', $filters['entity_type']);
        }
        if (! empty($filters['date_from'])) {
            $builder->where('audit_logs.created_at >=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $builder->where('audit_logs.created_at <=', $filters['date_to'] . ' 23:59:59');
        }
        if (! empty($filters['search'])) {
            $builder->like('audit_logs.description', $filters['search']);
        }

        $builder->orderBy('audit_logs.created_at', 'DESC');

        $total = $builder->countAllResults(false);
        $results = $builder->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();

        return [
            'items'       => $results,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Get distinct actions for filter dropdown.
     */
    public function getDistinctActions(): array
    {
        return array_column(
            $this->builder()->select('action')->distinct()->orderBy('action')->get()->getResultArray(),
            'action'
        );
    }
}
