<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><?= esc($title) ?></h4>
    <a href="<?= site_url('documents/' . $document['id']) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> <?= lang('App.back') ?>
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="post" action="<?= site_url('documents/' . $document['id'] . '/update') ?>">
            <?= csrf_field() ?>

            <div class="row g-3">
                <!-- Title -->
                <div class="col-12">
                    <label for="title" class="form-label"><?= lang('App.document_title') ?> <span class="text-danger">*</span></label>
                    <input type="text" name="title" id="title" class="form-control"
                           value="<?= esc(old('title', $document['title'] ?? '')) ?>" required maxlength="500">
                </div>

                <!-- Document Type -->
                <div class="col-md-4">
                    <label for="document_type" class="form-label"><?= lang('App.document_type') ?></label>
                    <select name="document_type" id="document_type" class="form-select">
                        <option value="">-</option>
                        <?php
                        $types = ['ruling', 'memorandum', 'law', 'regulation', 'legal_opinion', 'contract'];
                        foreach ($types as $t):
                        ?>
                            <option value="<?= $t ?>" <?= old('document_type', $document['document_type'] ?? '') === $t ? 'selected' : '' ?>>
                                <?= lang('App.type_' . $t) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Court Level -->
                <div class="col-md-4">
                    <label for="court_level" class="form-label"><?= lang('App.court_level') ?></label>
                    <select name="court_level" id="court_level" class="form-select">
                        <option value="">-</option>
                        <?php
                        $courts = ['first_instance', 'appeal', 'tamyeez', 'administrative', 'constitutional', 'commercial', 'criminal', 'personal_status', 'labor'];
                        foreach ($courts as $c):
                        ?>
                            <option value="<?= $c ?>" <?= old('court_level', $document['court_level'] ?? '') === $c ? 'selected' : '' ?>>
                                <?= lang('App.court_' . $c) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Case Number -->
                <div class="col-md-4">
                    <label for="case_number" class="form-label"><?= lang('App.case_number') ?></label>
                    <input type="text" name="case_number" id="case_number" class="form-control"
                           value="<?= esc(old('case_number', $document['case_number'] ?? '')) ?>" maxlength="100">
                </div>

                <!-- Document Date -->
                <div class="col-md-4">
                    <label for="document_date" class="form-label"><?= lang('App.document_date') ?></label>
                    <input type="date" name="document_date" id="document_date" class="form-control"
                           value="<?= esc(old('document_date', $document['document_date'] ?? '')) ?>">
                </div>

                <!-- Keywords -->
                <div class="col-md-8">
                    <label for="keywords" class="form-label"><?= lang('App.search') ?> (<?= lang('App.filter') ?>)</label>
                    <input type="text" name="keywords" id="keywords" class="form-control"
                           value="<?= esc(old('keywords', $document['keywords'] ?? '')) ?>"
                           placeholder="keyword1, keyword2, ...">
                </div>
            </div>

            <!-- Submit -->
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> <?= lang('App.save') ?>
                </button>
                <a href="<?= site_url('documents/' . $document['id']) ?>" class="btn btn-outline-secondary">
                    <?= lang('App.cancel') ?>
                </a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
