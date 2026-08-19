<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * students/_batch_rows.php renders for two callers now: the standalone batch
 * detail page, and Batch History's View dialog.
 *
 * The dialog passes showActions=false. The flag defaults to TRUE so the page is
 * unchanged by its introduction -- that default is the thing most worth pinning,
 * because getting it backwards would silently strip the controls off a screen
 * nobody asked to change.
 *
 * @internal
 */
final class BatchRowsPartialTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('esection');

        // can() returns true outright for an admin, so the actions cell renders
        // its full contents without needing a permissions fixture.
        session()->set('role', 'admin');

        // feature_enabled() reads the settings TABLE. Priming the service's
        // static cache keeps this a view test rather than a database one: what
        // is under test is the partial's column count, not how a toggle is
        // stored. Without it the default-true case dies on "no such table:
        // db_settings" and the assertion never runs.
        $this->primeSettings(['feature_delete_enabled' => '1']);
    }

    protected function tearDown(): void
    {
        $this->primeSettings([]);   // never leak a primed toggle into another test
        parent::tearDown();
    }

    private function primeSettings(array $values): void
    {
        $cache = new ReflectionProperty(\App\Services\SettingService::class, 'cache');
        $cache->setAccessible(true);
        $cache->setValue(null, $values);
    }

    /** @return array<int, array<string, mixed>> */
    private function students(int $n = 2): array
    {
        $rows = [];

        for ($i = 1; $i <= $n; $i++) {
            $rows[] = [
                'id'                                   => $i,
                'student_name'                         => 'Candidate ' . $i,
                'student_nee_name'                     => 'Nee ' . $i,
                'eligibility_case_no'                  => 'CASE/' . $i,
                'verification_of_marksheet_done_by_you' => 'Verified',
                'email'                                => 'c' . $i . '@example.test',
            ];
        }

        return $rows;
    }

    /**
     * saveData:false is not incidental. Config\View::$saveData is true app-wide,
     * so view data persists between render() calls in one request -- without
     * this, the first test's showActions=false leaks into every later render
     * and the "default is true" tests pass or fail depending on method order.
     */
    private function render(array $data): string
    {
        return view('students/_batch_rows', $data, ['saveData' => false]);
    }

    // ------------------------------------------------------------------
    // the dialog's mode
    // ------------------------------------------------------------------

    public function testTheDialogRendersNoActionsCell(): void
    {
        $html = $this->render(['students' => $this->students(), 'showActions' => false]);

        $this->assertStringNotContainsString('edit-student-btn', $html);
        $this->assertStringNotContainsString('delete-student-form', $html);
        $this->assertStringNotContainsString('students/delete/', $html);
    }

    public function testTheDialogStillRendersTheCandidateData(): void
    {
        $html = $this->render(['students' => $this->students(), 'showActions' => false]);

        $this->assertStringContainsString('Candidate 1', $html);
        $this->assertStringContainsString('CASE/1', $html);
        $this->assertStringContainsString('c2@example.test', $html);
    }

    public function testTheDialogRendersExactlySixCellsPerRow(): void
    {
        // The dialog's <thead> has six columns. A seventh cell here would push
        // every row out of alignment with it.
        $html = $this->render(['students' => $this->students(1), 'showActions' => false]);

        $this->assertSame(6, substr_count($html, '<td'), $html);
    }

    public function testTheEmptyStateSpansSixColumnsInTheDialog(): void
    {
        $html = $this->render(['students' => [], 'showActions' => false]);

        $this->assertStringContainsString('colspan="6"', $html);
        $this->assertStringContainsString('No candidates in this batch.', $html);
    }

    // ------------------------------------------------------------------
    // the page's mode -- the default must not have changed
    // ------------------------------------------------------------------

    public function testTheEmptyStateSpansSevenColumnsByDefault(): void
    {
        // No showActions key at all: exactly how the standalone page calls it.
        $html = $this->render(['students' => []]);

        $this->assertStringContainsString('colspan="7"', $html);
    }

    public function testTheActionsCellIsRenderedByDefault(): void
    {
        // can() and feature_enabled() decide what goes INSIDE the cell; the cell
        // itself must exist either way, or the row loses a column against the
        // page's seven-column header.
        $html = $this->render(['students' => $this->students(1)]);

        $this->assertSame(7, substr_count($html, '<td'), $html);
        $this->assertStringContainsString('class="text-end"', $html);
    }

    public function testAnExplicitTrueMatchesTheDefault(): void
    {
        // The CSRF hash is regenerated per render, so it is masked out. It is
        // the only thing that legitimately differs between two renders of the
        // same partial; everything else differing would be the bug.
        $mask = static fn (string $html): string => (string) preg_replace(
            '/value="[0-9a-f]{64}"/',
            'value="CSRF"',
            $html
        );

        $this->assertSame(
            $mask($this->render(['students' => $this->students(1)])),
            $mask($this->render(['students' => $this->students(1), 'showActions' => true]))
        );
    }
}
