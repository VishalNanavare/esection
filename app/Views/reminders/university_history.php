<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-history me-2 text-indigo"></i> University Reminder History</h3>
                    <p class="text-muted small mb-0">Browse ongoing reminder batches, grouped by university and academic year.</p>
                </div>
                <div>
                    <?php if (feature_enabled('feature_export_enabled') && can('reminders_university.export')): ?>
                        <a href="<?= base_url('reminders/university/history/export?' . http_build_query($filters)) ?>" class="btn btn-glass me-1">
                            <i class="fa fa-file-excel-o me-1"></i> Export to Excel
                        </a>
                    <?php endif; ?>
                    <a href="<?= base_url('reminders/university') ?>" class="btn btn-glass"><i class="fa fa-arrow-left me-1"></i> Back to Reminder Portal</a>
                </div>
            </div>

            <?php /* Every filter optional -- see students/history. */ ?>
            <form action="<?= base_url('reminders/university/history') ?>" method="get" class="row g-3 mb-4 filter-panel">
                <div class="col-md-4">
                    <label class="form-label text-secondary small fw-semibold">University</label>
                    <input type="text" name="university" class="form-control" placeholder="Any part of the name"
                           value="<?= esc($filters['university']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label text-secondary small fw-semibold">Academic Year</label>
                    <select name="year" class="form-select select2-ajax-academic-year">
                        <?php if ($filters['year'] !== ''): ?>
                            <option value="<?= esc($filters['year']) ?>" selected><?= esc($filters['year']) ?></option>
                        <?php else: ?>
                            <option value="">-- All Years --</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label text-secondary small fw-semibold">Course</label>
                    <input type="text" name="course" class="form-control" placeholder="Any part of the course"
                           value="<?= esc($filters['course']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-secondary small fw-semibold">Created From</label>
                    <input type="text" name="date_from" class="form-control es-datepicker" autocomplete="off"
                           placeholder="YYYY-MM-DD" value="<?= esc($filters['date_from']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-secondary small fw-semibold">Created To</label>
                    <input type="text" name="date_to" class="form-control es-datepicker" autocomplete="off"
                           placeholder="YYYY-MM-DD" value="<?= esc($filters['date_to']) ?>">
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <a href="<?= base_url('reminders/university/history') ?>" class="btn btn-glass"><i class="fa fa-refresh me-1"></i> Reset</a>
                    <button type="submit" class="btn btn-indigo px-4"><i class="fa fa-filter me-1"></i> Filter</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-glass">
                    <thead>
                        <tr>
                            <th>University</th>
                            <th>Academic Year</th>
                            <th>Course</th>
                            <th class="text-center">Candidates</th>
                            <th>Started</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($batches)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No reminder batches yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($batches as $b): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?= esc($b['university_name']) ?></td>
                                    <td><?= esc($b['academic_year']) ?></td>
                                    <td><?= esc($b['admission_taken_in'] ?: '-') ?></td>
                                    <td class="text-center"><span class="badge badge-glass-indigo"><?= (int) $b['student_count'] ?></span></td>
                                    <td class="small text-muted"><?= esc($b['created_at']) ?></td>
                                    <td class="text-end">
                                        <a href="<?= base_url('reminders/university/batch/' . $b['id']) ?>" class="btn btn-sm btn-glass text-primary me-1">
                                            <i class="fa fa-eye"></i> View
                                        </a>
                                        <?php if (can('reminders_university.print')): ?>
                                            <a href="<?= base_url('reminders/university/pdf/' . $b['id']) ?>" target="_blank" class="btn btn-sm btn-glass text-emerald">
                                                <i class="fa fa-file-pdf-o"></i> PDF
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
