<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <i class="bi bi-layers me-2 text-primary"></i>
        <?= lang('App.queue_monitor') ?>
    </h4>
    <div class="d-flex gap-2 align-items-center">
        <!-- Auto-refresh toggle -->
        <div class="form-check form-switch mb-0 me-2">
            <input class="form-check-input" type="checkbox" id="autoRefresh" checked>
            <label class="form-check-label small" for="autoRefresh">Auto-refresh</label>
        </div>
        <?php if (in_array('documents.delete', $currentPermissions)): ?>
        <button class="btn btn-sm btn-outline-secondary" id="btnClearProcessed">
            <i class="bi bi-trash"></i> <?= lang('App.queue_clear_processed') ?>
        </button>
        <button class="btn btn-sm btn-outline-danger" id="btnClearFailed">
            <i class="bi bi-trash"></i> <?= lang('App.queue_clear_failed') ?>
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Stats cards -->
<div class="row g-3 mb-4" id="statsRow">
    <div class="col-6 col-md-2">
        <div class="card text-center border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold text-primary" id="statTotal"><?= (int) $stats['total'] ?></div>
                <small class="text-muted"><?= lang('App.queue_total') ?></small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card text-center border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold text-secondary" id="statPending"><?= (int) $stats['pending'] ?></div>
                <small class="text-muted"><?= lang('App.queue_status_pending') ?></small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card text-center border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold text-info" id="statProcessing"><?= (int) $stats['processing'] ?></div>
                <small class="text-muted"><?= lang('App.queue_status_processing') ?></small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card text-center border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold text-success" id="statProcessed"><?= (int) $stats['processed'] ?></div>
                <small class="text-muted"><?= lang('App.queue_status_processed') ?></small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card text-center border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold text-warning" id="statDuplicate"><?= (int) $stats['duplicate'] ?></div>
                <small class="text-muted"><?= lang('App.queue_status_duplicate') ?></small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card text-center border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold text-danger" id="statFailed"><?= (int) $stats['failed'] ?></div>
                <small class="text-muted"><?= lang('App.queue_status_failed') ?></small>
            </div>
        </div>
    </div>
</div>

<!-- CLI command hint -->
<div class="alert alert-info d-flex align-items-start gap-2 py-2 mb-3">
    <i class="bi bi-terminal fs-5 mt-1"></i>
    <div>
        <strong><?= lang('App.queue_run_cmd') ?></strong><br>
        <code>C:\xampp\php\php.exe spark queue:process --daemon</code>
    </div>
</div>

<!-- Status filter tabs -->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $status === '' ? 'active' : '' ?>"
           href="<?= site_url('queue') ?>"><?= lang('App.all') ?></a>
    </li>
    <?php foreach (['pending','processing','processed','duplicate','failed'] as $s): ?>
    <li class="nav-item">
        <a class="nav-link <?= $status === $s ? 'active' : '' ?>"
           href="<?= site_url('queue?status=' . $s) ?>"><?= lang('App.queue_status_' . $s) ?></a>
    </li>
    <?php endforeach; ?>
</ul>

<!-- Table -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th><?= lang('App.queue_original_name') ?></th>
                        <th><?= lang('App.file_size') ?></th>
                        <th><?= lang('App.status') ?></th>
                        <th><?= lang('App.queue_error') ?></th>
                        <th><?= lang('App.date') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <?= lang('App.queue_empty') ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="text-muted small"><?= esc($row['id']) ?></td>
                        <td>
                            <span class="text-break small fw-semibold"><?= esc($row['original_name']) ?></span>
                            <?php if (!empty($row['document_id'])): ?>
                            <a href="<?= site_url('documents/' . $row['document_id']) ?>"
                               class="ms-1 badge bg-success text-decoration-none" target="_blank">
                                doc #<?= (int)$row['document_id'] ?>
                            </a>
                            <?php endif; ?>
                        </td>
                        <td class="text-nowrap small"><?= number_format($row['file_size'] / 1024, 0) ?> KB</td>
                        <td>
                            <?php
                            $badgeMap = [
                                'pending'    => 'secondary',
                                'processing' => 'info',
                                'processed'  => 'success',
                                'duplicate'  => 'warning',
                                'failed'     => 'danger',
                            ];
                            $bc = $badgeMap[$row['status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $bc ?>"><?= lang('App.queue_status_' . $row['status']) ?></span>
                        </td>
                        <td class="small text-danger">
                            <?php if (!empty($row['error_message'])): ?>
                            <span title="<?= esc($row['error_message']) ?>" style="cursor:help">
                                <?= esc(mb_substr($row['error_message'], 0, 60)) ?>…
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-nowrap small text-muted"><?= esc($row['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($total > $perPage): ?>
    <div class="card-footer">
        <div class="d-flex justify-content-between align-items-center">
            <small class="text-muted"><?= $total ?> <?= lang('App.total') ?></small>
            <?php
            $baseUrl = site_url('queue') . '?status=' . urlencode($status) . '&page=';
            $totalPages = (int) ceil($total / $perPage);
            ?>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $baseUrl . $p ?>"><?= $p ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const autoRefreshEl = document.getElementById('autoRefresh');
    let timer = null;

    function refreshStats() {
        Qanony.ajax(Qanony.siteUrl + '/queue/stats').then(function (data) {
            if (!data) return;
            document.getElementById('statTotal').textContent      = data.total      ?? 0;
            document.getElementById('statPending').textContent    = data.pending     ?? 0;
            document.getElementById('statProcessing').textContent = data.processing  ?? 0;
            document.getElementById('statProcessed').textContent  = data.processed   ?? 0;
            document.getElementById('statDuplicate').textContent  = data.duplicate   ?? 0;
            document.getElementById('statFailed').textContent     = data.failed      ?? 0;
        });
    }

    function startTimer() {
        timer = setInterval(refreshStats, 3000);
    }

    function stopTimer() {
        if (timer) clearInterval(timer);
        timer = null;
    }

    if (autoRefreshEl.checked) startTimer();

    autoRefreshEl.addEventListener('change', function () {
        if (this.checked) { startTimer(); } else { stopTimer(); }
    });

    // Clear buttons
    function clearByStatus(status) {
        const body = new URLSearchParams({ status });
        Qanony.ajax(Qanony.siteUrl + '/queue/clear', {
            method: 'POST',
            body: body.toString(),
        }).then(function (data) {
            if (data && data.success) {
                Qanony.toast(data.message, 'success');
                setTimeout(function () { location.reload(); }, 800);
            }
        });
    }

    const btnP = document.getElementById('btnClearProcessed');
    const btnF = document.getElementById('btnClearFailed');
    if (btnP) btnP.addEventListener('click', function () { clearByStatus('processed'); });
    if (btnF) btnF.addEventListener('click', function () { clearByStatus('failed'); });
});
</script>

<?= $this->endSection() ?>
