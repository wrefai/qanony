<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><?= esc($title) ?></h4>
    <div>
        <a href="<?= site_url('documents/' . $document['id'] . '/download') ?>" class="btn btn-outline-success me-1">
            <i class="bi bi-download"></i> <?= lang('App.download') ?>
        </a>
        <a href="<?= site_url('documents') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> <?= lang('App.back') ?>
        </a>
    </div>
</div>

<div class="row g-3">
    <!-- Metadata -->
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-info-circle"></i> <?= lang('App.description') ?></h6>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <th class="text-muted" style="width:40%"><?= lang('App.document_type') ?></th>
                        <td>
                            <?php if ($document['document_type']): ?>
                                <span class="badge bg-primary"><?= lang('App.type_' . $document['document_type']) ?></span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th class="text-muted"><?= lang('App.court_level') ?></th>
                        <td>
                            <?php if ($document['court_level']): ?>
                                <span class="badge bg-success"><?= lang('App.court_' . $document['court_level']) ?></span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th class="text-muted"><?= lang('App.case_number') ?></th>
                        <td><?= esc($document['case_number'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th class="text-muted"><?= lang('App.document_date') ?></th>
                        <td><?= esc($document['document_date'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th class="text-muted"><?= lang('App.page_count') ?></th>
                        <td><?= esc($document['page_count'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th class="text-muted"><?= lang('App.file_size') ?></th>
                        <td>
                            <?php
                                $size = $document['file_size'] ?? 0;
                                if ($size > 1048576) {
                                    echo round($size / 1048576, 2) . ' MB';
                                } elseif ($size > 1024) {
                                    echo round($size / 1024, 1) . ' KB';
                                } else {
                                    echo $size . ' B';
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th class="text-muted"><?= lang('App.file_path') ?></th>
                        <td><small class="text-muted"><?= esc($document['file_name'] ?? '') ?></small></td>
                    </tr>
                    <tr>
                        <th class="text-muted"><?= lang('App.created_at') ?></th>
                        <td><?= esc($document['created_at'] ?? '-') ?></td>
                    </tr>
                </table>

                <?php if ($document['keywords']): ?>
                    <hr>
                    <div>
                        <strong class="text-muted"><?= lang('App.search') ?>:</strong>
                        <div class="mt-1">
                            <?php foreach (explode(',', $document['keywords']) as $kw): ?>
                                <span class="badge bg-light text-dark border me-1 mb-1"><?= esc(trim($kw)) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (in_array('documents.update', $currentPermissions)): ?>
                    <hr>
                    <a href="<?= site_url('documents/' . $document['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary w-100">
                        <i class="bi bi-pencil"></i> <?= lang('App.edit') ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Principles -->
        <?php if (!empty($principles)): ?>
        <div class="card shadow-sm mt-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-bookmark-star"></i> <?= lang('App.principles') ?></h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($principles as $p): ?>
                    <div class="list-group-item">
                        <strong><?= esc($p['title'] ?? '') ?></strong>
                        <?php if (!empty($p['category'])): ?>
                            <span class="badge bg-secondary"><?= esc($p['category']) ?></span>
                        <?php endif; ?>
                        <p class="mb-0 mt-1 small text-muted"><?= esc(mb_substr($p['description'] ?? '', 0, 200)) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Defenses -->
        <?php if (!empty($defenses)): ?>
        <div class="card shadow-sm mt-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-shield-check"></i> <?= lang('App.defenses') ?></h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($defenses as $d): ?>
                    <div class="list-group-item">
                        <strong><?= esc($d['title'] ?? '') ?></strong>
                        <?php if (!empty($d['legal_basis'])): ?>
                            <span class="badge bg-info text-dark"><?= esc($d['legal_basis']) ?></span>
                        <?php endif; ?>
                        <p class="mb-0 mt-1 small text-muted"><?= esc(mb_substr($d['description'] ?? '', 0, 200)) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Document Text Preview -->
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-file-text"></i> <?= lang('App.preview') ?></h6>
                <small class="text-muted"><?= number_format(mb_strlen($document['full_text'] ?? '')) ?> <?= lang('App.type') ?></small>
            </div>
            <div class="card-body p-0">
                <div class="document-preview p-3"
                     dir="<?= esc($direction) ?>"
                     lang="<?= esc($locale === 'ar' ? 'ar' : 'en') ?>"
                     style="white-space: pre-wrap; word-break: break-word; font-size: 0.95rem; line-height: 1.8; max-height: 70vh; overflow-y: auto;">
<?= esc($document['full_text'] ?? '') ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
