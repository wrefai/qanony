<?php

namespace App\Controllers;

use App\Models\AuditLogModel;
use App\Models\UserModel;

class AuditController extends BaseController
{
    protected AuditLogModel $auditModel;

    public function __construct()
    {
        $this->auditModel = new AuditLogModel();
    }

    public function index()
    {
        return view('audit/index', $this->viewData([
            'title'   => lang('App.audit_log'),
            'actions' => $this->auditModel->getDistinctActions(),
            'users'   => (new UserModel())->select('id, username, full_name')->findAll(),
        ]));
    }

    public function data()
    {
        $page    = (int) ($this->request->getGet('page') ?? 1);
        $perPage = (int) ($this->request->getGet('per_page') ?? 25);

        $filters = [
            'action'      => $this->request->getGet('action'),
            'user_id'     => $this->request->getGet('user_id'),
            'entity_type' => $this->request->getGet('entity_type'),
            'date_from'   => $this->request->getGet('date_from'),
            'date_to'     => $this->request->getGet('date_to'),
            'search'      => $this->request->getGet('search'),
        ];

        $result = $this->auditModel->getFiltered($filters, $page, $perPage);

        return $this->jsonResponse($result);
    }
}
