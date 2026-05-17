<?php

namespace App\Controllers;

use App\Models\LegalDocumentModel;
use App\Models\LegalPrincipleModel;
use App\Models\UserModel;
use App\Models\AuditLogModel;

class DashboardController extends BaseController
{
    public function index()
    {
        $docModel       = new LegalDocumentModel();
        $principleModel = new LegalPrincipleModel();
        $userModel      = new UserModel();
        $auditModel     = new AuditLogModel();

        $data = $this->viewData([
            'title'          => lang('App.dashboard'),
            'totalDocuments' => $docModel->countAllResults(false),
            'totalPrinciples' => $principleModel->countAllResults(false),
            'totalUsers'     => $userModel->countAllResults(false),
            'docStats'       => $docModel->getStats(),
            'recentAudit'    => $auditModel->getFiltered([], 1, 10),
        ]);

        return view('dashboard/index', $data);
    }
}
