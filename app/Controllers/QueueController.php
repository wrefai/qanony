<?php

namespace App\Controllers;

use App\Models\UploadQueueModel;

/**
 * Queue Monitor Controller
 *
 * Provides a web interface for viewing and managing the upload_queue table.
 * Requires documents.create permission (same users who upload files).
 */
class QueueController extends BaseController
{
    private UploadQueueModel $queueModel;

    public function __construct()
    {
        $this->queueModel = new UploadQueueModel();
    }

    /**
     * Queue monitor page.
     */
    public function index(): string
    {
        $status  = $this->request->getGet('status') ?? '';
        // Allowlist to prevent arbitrary strings reaching the model
        $allowedStatuses = ['', 'pending', 'processing', 'processed', 'failed', 'duplicate'];
        if (! in_array($status, $allowedStatuses, true)) {
            $status = '';
        }
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = 50;

        $rows  = $this->queueModel->getPage($page, $perPage, $status);
        $total = $this->queueModel->countByStatus($status);
        $stats = $this->queueModel->getStats();

        $pager = \Config\Services::pager();

        return view('queue/index', $this->viewData([
            'rows'   => $rows,
            'stats'  => $stats,
            'status' => $status,
            'pager'  => $pager,
            'page'   => $page,
            'total'  => $total,
            'perPage'=> $perPage,
        ]));
    }

    /**
     * AJAX: current stats (for auto-refresh on the monitor page).
     * GET /queue/stats
     */
    public function stats()
    {
        return $this->jsonResponse($this->queueModel->getStats());
    }

    /**
     * AJAX: clear rows by status (processed or failed).
     * POST /queue/clear  body: status=processed|failed
     */
    public function clear()
    {
        $status = $this->request->getPost('status');
        if (!in_array($status, ['processed', 'failed', 'duplicate'], true)) {
            return $this->jsonResponse(['success' => false, 'message' => lang('App.error_occurred')], 400);
        }

        $count = $this->queueModel->deleteByStatus($status);
        return $this->jsonResponse([
            'success' => true,
            'message' => lang('App.queue_cleared'),
            'deleted' => $count,
        ]);
    }
}
