<?php

namespace App\Services;

use App\Models\AcademicYearModel;

class AcademicYearService
{
    protected AcademicYearModel $academicYearModel;
    protected ActivityLogService $activityLogService;

    public function __construct()
    {
        helper('esection');
        $this->academicYearModel  = new AcademicYearModel();
        $this->activityLogService = new ActivityLogService();
    }

    /**
     * @param bool $withUsage attach how many records depend on each year, which
     *                        is what decides whether it may be deleted
     */
    public function getAll(bool $withUsage = false): array
    {
        $years = $this->academicYearModel->getAllOrdered();

        if (! $withUsage) {
            return $years;
        }

        $counts = $this->academicYearModel->usageCounts();

        foreach ($years as &$year) {
            $year['usage_count'] = (int) ($counts[$year['year_label']] ?? 0);
        }
        unset($year);

        return $years;
    }

    public function getById(int $id): ?array
    {
        $res = $this->academicYearModel->find($id);
        return $res ?: null;
    }

    public function searchForSelect2(?string $term): array
    {
        return $this->academicYearModel->searchForSelect2($term);
    }

    /**
     * @throws \InvalidArgumentException when the label is missing or already used
     */
    private function mapData(array $postData, ?int $ignoreId = null): array
    {
        $label = trim(sanitize_xss($postData['year_label'] ?? ''));
        if ($label === '') {
            throw new \InvalidArgumentException('Academic year label is required.');
        }

        $existing = $this->academicYearModel->findByLabel($label);
        if ($existing && (int) $existing['id'] !== $ignoreId) {
            throw new \InvalidArgumentException('That academic year already exists.');
        }

        // start_date / end_date are deliberately absent.
        //
        // The two columns remain in the table but are no longer collected or
        // written by anything. Mapping them here would mean every save wrote
        // NULL over them -- harmless while all 22 rows are already NULL, but it
        // would silently erase any value that ever did exist, which is exactly
        // the failure nobody notices until it matters. Not writing a column at
        // all is the only version of "leave the data alone" that is actually
        // true.
        return [
            'year_label' => $label,
        ];
    }

    public function save(array $postData): void
    {
        $data  = $this->mapData($postData);
        $newId = $this->academicYearModel->insertYear($data, ! empty($postData['is_current']));

        $this->activityLogService->record('academic_year.create', 'academic_year', $newId, 'Created academic year ' . $data['year_label']);
    }

    public function update(int $id, array $postData): void
    {
        $data = $this->mapData($postData, $id);
        $this->academicYearModel->updateYear($id, $data, ! empty($postData['is_current']));

        $this->activityLogService->record('academic_year.update', 'academic_year', $id, 'Updated academic year ' . $data['year_label']);
    }

    public function setCurrent(int $id): void
    {
        $year = $this->getById($id);
        if (! $year) {
            throw new \InvalidArgumentException('Academic year not found.');
        }

        $this->academicYearModel->markCurrent($id);

        $this->activityLogService->record('academic_year.set_current', 'academic_year', $id, 'Marked ' . $year['year_label'] . ' as the current academic year');
    }

    public function delete(int $id): void
    {
        if (! feature_enabled('feature_delete_enabled')) {
            throw new \InvalidArgumentException('Deleting records is currently disabled. Ask an administrator to enable it in Settings > Feature Toggles.');
        }

        $year = $this->getById($id);

        // Previously absent, and its absence was silent: delete() on an id that
        // does not exist reported "Academic year deleted." and wrote an
        // activity-log line reading "Deleted academic year 999". setCurrent()
        // immediately above has always had this check.
        if (! $year) {
            throw new \InvalidArgumentException('Academic year not found.');
        }

        // The guard this method never had.
        //
        // Every table stores the year LABEL as free text, not a foreign key, so
        // the database will not stop this and nothing cascades. Deleting
        // 2022-2023 today would leave 2,471 records naming a year that no
        // longer exists: they vanish from the year filter, the picker stops
        // offering it, and no error is raised anywhere. The records are still
        // in the table, which is what makes it hard to notice.
        //
        // Not one of the 22 years is currently unused, so in practice this
        // closes the delete button rather than narrowing it -- which is the
        // point. The rest of the app reaches the same end by not giving these
        // lookup tables a delete route at all (courses and universities have
        // no delete method anywhere); academic years is the one that has the
        // button, so it needs the check.
        $usage = $this->academicYearModel->usageCountFor($year['year_label']);

        if ($usage > 0) {
            throw new \InvalidArgumentException(sprintf(
                '%s cannot be deleted: %s record%s still use it. Delete is only available for a period with no data.',
                $year['year_label'],
                number_format($usage),
                $usage === 1 ? '' : 's'
            ));
        }

        // A year nothing references can still be the one the system defaults
        // to. Removing that leaves no current year at all, which no screen
        // reports and every new record then has to be told by hand.
        if ((int) ($year['is_current'] ?? 0) === 1) {
            throw new \InvalidArgumentException(
                $year['year_label'] . ' is the current academic year. Set another year as current before deleting it.'
            );
        }

        $this->academicYearModel->delete($id);

        $this->activityLogService->record('academic_year.delete', 'academic_year', $id, 'Deleted academic year ' . ($year['year_label'] ?? (string) $id));
    }
}
