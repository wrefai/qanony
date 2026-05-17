<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<!-- Welcome -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><?= lang('App.welcome', [esc($currentUser['full_name'] ?? $currentUser['username'] ?? '')]) ?></h4>
</div>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <!-- Total Documents -->
    <div class="col-sm-6 col-lg-3">
        <div class="card stat-card shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-file-earmark-text"></i>
                </div>
                <div>
                    <div class="stat-value text-primary"><?= number_format($totalDocuments ?? 0) ?></div>
                    <div class="stat-label text-muted"><?= lang('App.total_documents') ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Principles -->
    <div class="col-sm-6 col-lg-3">
        <div class="card stat-card shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-bookmark-star"></i>
                </div>
                <div>
                    <div class="stat-value text-success"><?= number_format($totalPrinciples ?? 0) ?></div>
                    <div class="stat-label text-muted"><?= lang('App.total_principles') ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Users -->
    <div class="col-sm-6 col-lg-3">
        <div class="card stat-card shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-info bg-opacity-10 text-info">
                    <i class="bi bi-people"></i>
                </div>
                <div>
                    <div class="stat-value text-info"><?= number_format($totalUsers ?? 0) ?></div>
                    <div class="stat-label text-muted"><?= lang('App.total_users') ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Indexed Documents -->
    <div class="col-sm-6 col-lg-3">
        <div class="card stat-card shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-database-check"></i>
                </div>
                <div>
                    <div class="stat-value text-warning"><?= number_format($docStats['indexed'] ?? 0) ?></div>
                    <div class="stat-label text-muted"><?= lang('App.indexed') ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Documents by Type -->
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-pie-chart"></i> <?= lang('App.documents_by_type') ?></h6>
            </div>
            <div class="card-body">
                <?php if (!empty($docStats['by_type'])): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th><?= lang('App.type') ?></th>
                                    <th class="text-center">#</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($docStats['by_type'] as $row): ?>
                                <tr>
                                    <td><?= esc(lang('App.type_' . ($row['document_type'] ?? 'ruling'))) ?></td>
                                    <td class="text-center"><span class="badge bg-primary"><?= (int) $row['count'] ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0"><?= lang('App.no_data') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Documents by Court -->
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-bar-chart"></i> <?= lang('App.documents_by_court') ?></h6>
            </div>
            <div class="card-body">
                <?php if (!empty($docStats['by_court'])): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th><?= lang('App.court_level') ?></th>
                                    <th class="text-center">#</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($docStats['by_court'] as $row): ?>
                                <tr>
                                    <td><?= esc(lang('App.court_' . ($row['court_level'] ?? ''))) ?></td>
                                    <td class="text-center"><span class="badge bg-success"><?= (int) $row['count'] ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0"><?= lang('App.no_data') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-clock-history"></i> <?= lang('App.recent_activity') ?></h6>
                <?php if (in_array('audit.read', $currentPermissions)): ?>
                    <a href="<?= site_url('audit') ?>" class="btn btn-sm btn-outline-primary"><?= lang('App.view') ?></a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php
                    $auditItems = $recentAudit['items'] ?? [];
                    if (!empty($auditItems)):
                ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th><?= lang('App.action') ?></th>
                                <th><?= lang('App.description') ?></th>
                                <th><?= lang('App.username') ?></th>
                                <th><?= lang('App.date') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($auditItems as $entry): ?>
                            <tr>
                                <td><span class="badge bg-secondary"><?= esc($entry['action']) ?></span></td>
                                <td><?= esc(mb_substr($entry['description'] ?? '', 0, 80)) ?></td>
                                <td><?= esc($entry['username'] ?? '-') ?></td>
                                <td><small><?= esc($entry['created_at']) ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <p class="text-muted p-3 mb-0"><?= lang('App.no_data') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
