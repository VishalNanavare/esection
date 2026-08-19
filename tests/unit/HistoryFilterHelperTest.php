<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * The two pure helpers behind the Batch History filter work.
 *
 * Both exist because of a failure that shows on screen as "no records" rather
 * than as an error, which is the kind that survives review, so they are pinned
 * here as well as exercised against live data by `spark esection:filtercheck`.
 *
 * @internal
 */
final class HistoryFilterHelperTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('esection');
    }

    // ---------------------------------------------------------------------
    // ordered_date_range()
    // ---------------------------------------------------------------------

    public function testLeavesAnAlreadyOrderedRangeAlone(): void
    {
        $this->assertSame(
            ['2024-01-01', '2024-12-31'],
            ordered_date_range('2024-01-01', '2024-12-31')
        );
    }

    public function testSwapsAnInvertedRange(): void
    {
        // The whole point: from > to makes `en_time >= from AND en_time <= to`
        // match nothing, and the screen reports no records rather than a
        // mistake.
        $this->assertSame(
            ['2024-01-01', '2024-12-31'],
            ordered_date_range('2024-12-31', '2024-01-01')
        );
    }

    public function testTreatsAnIdenticalPairAsOrdered(): void
    {
        // "From always smaller AND EQUAL to To" -- a single-day range is valid
        // and must not be disturbed.
        $this->assertSame(
            ['2024-06-01', '2024-06-01'],
            ordered_date_range('2024-06-01', '2024-06-01')
        );
    }

    public function testLeavesAHalfOpenRangeAlone(): void
    {
        // Blank means "no restriction" on every one of these screens. Ordering
        // against a bound that does not exist would be inventing one.
        $this->assertSame(['2024-05-05', ''], ordered_date_range('2024-05-05', ''));
        $this->assertSame(['', '2024-05-05'], ordered_date_range('', '2024-05-05'));
        $this->assertSame(['', ''], ordered_date_range('', ''));
    }

    public function testLeavesAnUnparseableSideAlone(): void
    {
        // The models already drop a date they cannot read; reordering against
        // one would be guessing which way round the operator meant it.
        $this->assertSame(
            ['nonsense', '2024-01-01'],
            ordered_date_range('nonsense', '2024-01-01')
        );
        $this->assertSame(
            ['2024-01-01', 'nonsense'],
            ordered_date_range('2024-01-01', 'nonsense')
        );
    }

    public function testSwapsAcrossYearsAndFormats(): void
    {
        $this->assertSame(
            ['2019-11-30', '2023-02-01'],
            ordered_date_range('2023-02-01', '2019-11-30')
        );
    }

    // ---------------------------------------------------------------------
    // flatten_address()
    // ---------------------------------------------------------------------

    public function testFlattensAMarkupLineBreakIntoAComma(): void
    {
        // 154 of the 418 stored addresses contain a literal <br>. Select2
        // renders option text AS text, so an uncleaned label shows the tag.
        $this->assertSame(
            'Pariksha Bhavan, Juhu Road, Mumbai-400 049., phone-',
            flatten_address('Pariksha Bhavan, Juhu Road, Mumbai-400 049. <br>phone- ')
        );
    }

    public function testEatsTheWhitespaceAroundTheTag(): void
    {
        // Otherwise the comma is stranded: "049. , phone-".
        $this->assertSame(
            '338 R.A. Kidwai Road, Mumbai-400 019, Phone- 022 2409 5869',
            flatten_address('338 R.A. Kidwai Road, Mumbai-400 019 <br> Phone-   022 2409 5869')
        );
    }

    public function testDropsATrailingComma(): void
    {
        $this->assertSame('Some College', flatten_address('Some College,<br>'));
        $this->assertSame('Some College', flatten_address('Some College <br>'));
    }

    public function testHandlesTheSelfClosingAndUppercaseForms(): void
    {
        $this->assertSame('A, B', flatten_address('A<br />B'));
        $this->assertSame('A, B', flatten_address('A<BR>B'));
        $this->assertSame('A, B', flatten_address('A<br/>B'));
    }

    public function testLeavesAPlainAddressUntouched(): void
    {
        $plain = 'Camalane, Ghatkopar West, Mumbai, Maharashtra 400086';
        $this->assertSame($plain, flatten_address($plain));
    }

    public function testCollapsesRunsOfWhitespace(): void
    {
        $this->assertSame('A B C', flatten_address("A   B \n\t C"));
    }

    public function testReturnsPlainTextNotEscapedMarkup(): void
    {
        // Escaping is the caller's job at the point of use -- this is the
        // division esc_address() deliberately does not follow, because that one
        // emits markup itself.
        $this->assertSame("O'Brien & Sons College", flatten_address("O'Brien & Sons College"));
    }

    public function testHandlesEmptyInput(): void
    {
        $this->assertSame('', flatten_address(''));
        $this->assertSame('', flatten_address('<br>'));
    }
}
