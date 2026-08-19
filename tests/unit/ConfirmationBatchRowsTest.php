<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * confirmations/_batch_rows.php renders for two callers now: the standalone
 * batch detail page, and Confirmation History's View dialog.
 *
 * The dialog passes showActions=false. Two things make that more than cosmetic:
 * the delete form inside the cell declares data-refresh="#confirmation_batch_rows",
 * a tbody that exists only on the standalone page, so fired from the dialog it
 * would delete a record and then repaint nothing; and the colspan of the empty
 * state was hardcoded to 10, which is wrong the moment a column goes.
 *
 * @internal
 */
final class ConfirmationBatchRowsTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('esection');

        // can() returns true outright for an admin, so the actions cell renders
        // its contents without needing a permissions fixture.
        session()->set('role', 'admin');

        // feature_enabled() reads the settings TABLE. Priming the service's
        // static cache keeps this a view test rather than a database one --
        // without it the default-true case dies on "no such table: db_settings".
        $this->primeSettings(['feature_delete_enabled' => '1']);
    }

    protected function tearDown(): void
    {
        $this->primeSettings([]);
        parent::tearDown();
    }

    private function primeSettings(array $values): void
    {
        $cache = new ReflectionProperty(\App\Services\SettingService::class, 'cache');
        $cache->setAccessible(true);
        $cache->setValue(null, $values);
    }

    /** @return array<int, array<string, mixed>> */
    private function records(int $n = 2): array
    {
        $rows = [];

        for ($i = 1; $i <= $n; $i++) {
            $rows[] = [
                'id'               => $i,
                'student_name'     => 'Candidate ' . $i,
                'student_nee_name' => '-',
                'clg_add'          => 'Some College, Mumbai <br>phone- 123',
                'case_no'          => 'CASE/' . $i,
                'mig_TC'           => 'Yes',
                'p_degree'         => 'Yes',
                's_marks'          => 'Yes',
                'letter_no_date'   => 'L/' . $i,
                'remark'           => 'Fine',
                'conf_from'        => '',
                'conf_from_text'   => '',
                'conf_from_select' => '',
                'etc_data'         => '',
            ];
        }

        return $rows;
    }

    /**
     * saveData:false is not incidental. Config\View::$saveData is true app-wide,
     * so view data persists between render() calls in one request -- without it
     * the first test's showActions=false leaks into every later render and the
     * "default is true" cases pass or fail on method order.
     */
    private function render(array $data): string
    {
        return view('confirmations/_batch_rows', $data, ['saveData' => false]);
    }

    // ------------------------------------------------------------------
    // the dialog's mode
    // ------------------------------------------------------------------

    public function testTheDialogRendersNoDeleteControl(): void
    {
        $html = $this->render(['records' => $this->records(), 'showActions' => false]);

        $this->assertStringNotContainsString('delete-confirmation-form', $html);
        $this->assertStringNotContainsString('confirmations/delete/', $html);
        $this->assertStringNotContainsString('confirmation_batch_rows', $html);
    }

    public function testTheDialogRendersNineCellsPerRow(): void
    {
        // The dialog's <thead> has nine columns. A tenth cell would push every
        // row out of alignment with it.
        $html = $this->render(['records' => $this->records(1), 'showActions' => false]);

        $this->assertSame(9, substr_count($html, '<td'), $html);
    }

    public function testTheDialogEmptyStateSpansNine(): void
    {
        $html = $this->render(['records' => [], 'showActions' => false]);

        $this->assertStringContainsString('colspan="9"', $html);
        $this->assertStringContainsString('No records found for this batch.', $html);
    }

    public function testTheDialogStillRendersTheRecordData(): void
    {
        $html = $this->render(['records' => $this->records(), 'showActions' => false]);

        $this->assertStringContainsString('Candidate 1', $html);
        $this->assertStringContainsString('CASE/2', $html);
        $this->assertStringContainsString('L/1', $html);
    }

    // ------------------------------------------------------------------
    // the page's mode -- the default must not have changed
    // ------------------------------------------------------------------

    public function testTheActionsCellIsRenderedByDefault(): void
    {
        $html = $this->render(['records' => $this->records(1)]);

        $this->assertSame(10, substr_count($html, '<td'), $html);
        $this->assertStringContainsString('delete-confirmation-form', $html);
    }

    public function testTheEmptyStateSpansTenByDefault(): void
    {
        // No showActions key at all: exactly how the standalone page calls it.
        $html = $this->render(['records' => []]);

        $this->assertStringContainsString('colspan="10"', $html);
    }

    // ------------------------------------------------------------------
    // the deep-link highlight must survive the new flag
    // ------------------------------------------------------------------

    public function testHighlightStillAppliesWhenActionsAreHidden(): void
    {
        // $highlightId is the partial's other documented contract. Adding a
        // second optional variable must not have disturbed it.
        $html = $this->render([
            'records'     => $this->records(2),
            'highlightId' => 2,
            'showActions' => false,
        ]);

        $this->assertStringContainsString('confirmation-row-highlight', $html);
        $this->assertSame(1, substr_count($html, 'confirmation-row-highlight'));
    }

    public function testNoHighlightWhenNoneRequested(): void
    {
        $html = $this->render(['records' => $this->records(2), 'showActions' => false]);

        $this->assertStringNotContainsString('confirmation-row-highlight', $html);
    }

    public function testAddressMarkupIsRenderedNotEscapedAway(): void
    {
        // esc_address() turns the stored <br> into a real line break; the cell
        // must not print the tag as text.
        $html = $this->render(['records' => $this->records(1), 'showActions' => false]);

        $this->assertStringNotContainsString('&lt;br&gt;', $html);
        $this->assertStringContainsString('Some College', $html);
    }
}
