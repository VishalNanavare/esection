<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-graduation-cap me-2 text-indigo"></i> Courses</h3>
                    <p class="text-muted small mb-0">Manage the master list of courses. Retired courses stay on historical records but disappear from new entry forms.</p>
                </div>
                <div>
                    <a href="<?= base_url('settings') ?>" class="btn btn-glass me-2"><i class="fa fa-arrow-left me-1"></i> Back</a>
                    <button type="button" class="btn btn-indigo" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                        <i class="fa fa-plus me-1"></i> Add Course
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-glass" id="course_table">
                    <thead>
                        <tr>
                            <th class="col-sr">#</th>
                            <th>Course Name</th>
                            <th>Code</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="course_rows">
                        <?= $this->include('settings/_courses_rows') ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Add Course -->
<div class="modal fade" id="addCourseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content glass-card border-secondary border-opacity-25">
            <div class="modal-header border-bottom border-secondary border-opacity-25">
                <h5 class="modal-title fw-bold text-dark"><i class="fa fa-graduation-cap me-2 text-indigo"></i> Add Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= base_url('settings/courses/store') ?>" method="post" class="js-ajax"
                  data-title="Courses" data-refresh="#course_rows" data-close-modal="#addCourseModal"
                  data-reset-on-success="1" data-busy-button="Saving...">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-secondary small fw-semibold">Course Name</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. B.A." required>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-secondary small fw-semibold">Course Code (optional)</label>
                            <input type="text" name="code" class="form-control" placeholder="e.g. BA">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-25">
                    <button type="button" class="btn btn-glass" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-indigo">Save Course</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Course -->
<div class="modal fade" id="editCourseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content glass-card border-secondary border-opacity-25">
            <div class="modal-header border-bottom border-secondary border-opacity-25">
                <h5 class="modal-title fw-bold text-dark"><i class="fa fa-edit me-2 text-indigo"></i> Edit Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="edit_course_form" method="post" class="js-ajax"
                  data-title="Courses" data-refresh="#course_rows" data-close-modal="#editCourseModal"
                  data-busy-button="Saving...">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-secondary small fw-semibold">Course Name</label>
                            <input type="text" name="name" id="edit_course_name" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-secondary small fw-semibold">Course Code (optional)</label>
                            <input type="text" name="code" id="edit_course_code" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-25">
                    <button type="button" class="btn btn-glass" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-indigo">Update Course</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <?= $this->include('common/ajax_settings_courses_js') ?>
<?= $this->endSection() ?>
