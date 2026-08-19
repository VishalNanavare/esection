<?php

use CodeIgniter\HTTP\URI;
use CodeIgniter\Pager\PagerRenderer;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The shared pager bar (app/Views/common/pagination_glass.php).
 *
 * Every paginated screen in the app renders through this one template, so a
 * mistake here is a mistake on all of them at once. It is rendered for real
 * against a real PagerRenderer rather than asserted by reading the source.
 *
 * The case that matters most is the single-page one: the template used to open
 * with `if ($pager->getPageCount() > 1)` and emit an empty string, which was
 * right while it held nothing but page links and wrong the moment it had to
 * carry a count.
 *
 * @internal
 */
final class PaginationBarTest extends CIUnitTestCase
{
    private function render(int $total, int $perPage, int $currentPage): string
    {
        $pageCount = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $pager = new PagerRenderer([
            'uri'          => new URI('https://example.test/students/history'),
            'pageCount'    => max(1, $pageCount),
            'currentPage'  => $currentPage,
            'total'        => $total,
            'perPage'      => $perPage,
            'segment'      => 0,
            'pageSelector' => 'page',
        ]);

        return view('common/pagination_glass', ['pager' => $pager]);
    }

    // -----------------------------------------------------------------
    // the count -- must survive the cases the old early-return swallowed
    // -----------------------------------------------------------------

    public function testAnEmptyResultStillRendersAndSaysSo(): void
    {
        $html = $this->render(0, 20, 1);

        $this->assertStringContainsString('es-pagebar', $html, 'the bar must render at all');
        $this->assertStringContainsString('No records', $html);
        $this->assertStringNotContainsString('<ul', $html, 'no page links for a single page');
    }

    public function testASinglePageRendersACountWithNoLinks(): void
    {
        $html = $this->render(7, 20, 1);

        $this->assertStringContainsString('7 records', $html);
        $this->assertStringNotContainsString('<ul', $html);
    }

    public function testASingleRecordIsNotPluralised(): void
    {
        $html = $this->render(1, 20, 1);

        $this->assertStringContainsString('1 record', $html);
        $this->assertStringNotContainsString('1 records', $html);
    }

    public function testAMultiPageResultShowsTheRowRange(): void
    {
        // page 2 of 3,934 at 20 a page
        $html = $this->render(3934, 20, 2);

        $this->assertStringContainsString('Showing', $html);
        $this->assertStringContainsString('21', $html);
        $this->assertStringContainsString('40', $html);
        $this->assertStringContainsString('3,934', $html, 'the total must be thousands-separated');
    }

    public function testTheLastPageRangeStopsAtTheTotal(): void
    {
        // 3,934 at 20 a page -> page 197 holds rows 3,921-3,934
        $html = $this->render(3934, 20, 197);

        $this->assertStringContainsString('3,921', $html);
        $this->assertStringContainsString('3,934', $html);
        $this->assertStringNotContainsString('3,940', $html, 'must not run past the total');
    }

    // -----------------------------------------------------------------
    // first / last
    // -----------------------------------------------------------------

    public function testFirstAndLastControlsExistOnAMultiPageResult(): void
    {
        $html = $this->render(3934, 20, 100);

        $this->assertStringContainsString('Go to first page', $html);
        $this->assertStringContainsString('Go to last page', $html);
        $this->assertStringContainsString('Go to previous page', $html);
        $this->assertStringContainsString('Go to next page', $html);
    }

    public function testFirstAndLastPointAtTheRealEndPages(): void
    {
        $html = $this->render(3934, 20, 100);

        $this->assertStringContainsString('page=1"', $html, 'First must link to page 1');
        $this->assertStringContainsString('page=197"', $html, 'Last must link to the final page');
    }

    public function testFirstIsDisabledOnPageOne(): void
    {
        $html = $this->render(3934, 20, 1);

        // The two leading controls are First and Prev; both are dead on page 1.
        $head = substr($html, 0, strpos($html, 'Go to next page'));
        $this->assertSame(
            2,
            substr_count($head, 'page-item disabled'),
            'First and Previous must both be disabled on the first page'
        );
    }

    public function testLastIsDisabledOnTheFinalPage(): void
    {
        $html = $this->render(3934, 20, 197);

        $tail = substr($html, strpos($html, 'Go to next page'));
        $this->assertStringContainsString('disabled', $tail);

        // A disabled control must not carry a working href.
        $this->assertStringNotContainsString('page=198', $html);
    }

    public function testDisabledControlsHaveNoNavigableHref(): void
    {
        $html = $this->render(100, 20, 1);

        // On page 1 the First/Prev links are href="#": present but inert.
        $this->assertStringContainsString('href="#"', $html);
    }

    // -----------------------------------------------------------------
    // layout contract the CSS depends on
    // -----------------------------------------------------------------

    public function testTheCountIsLeftAndTheNavIsRight(): void
    {
        $html = $this->render(3934, 20, 2);

        $countAt = strpos($html, 'es-pagebar__count');
        $navAt   = strpos($html, 'es-pagebar__nav');

        $this->assertNotFalse($countAt);
        $this->assertNotFalse($navAt);
        $this->assertLessThan($navAt, $countAt, 'the count must precede the nav in source order');
    }

    public function testEveryPageLinkKeepsTheBootstrapClasses(): void
    {
        // CI4's own default_full.php omits these, which is why this template
        // exists at all -- without them the pager is unstyled under Bootstrap 5.
        $html = $this->render(3934, 20, 2);

        $this->assertSame(
            substr_count($html, '<li class="page-item'),
            substr_count($html, 'class="page-link"'),
            'every page-item must contain exactly one page-link'
        );
    }
}
