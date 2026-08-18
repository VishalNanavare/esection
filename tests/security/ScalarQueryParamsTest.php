<?php

namespace Tests\Security;

use App\Filters\ScalarQueryParamsFilter;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

/**
 * Array-valued query parameters must never reach a controller.
 *
 * 35 read sites across the filter, search and export screens treat the query
 * string as scalar -- `trim((string) ($this->request->getGet('year') ?? ''))`
 * and variations. Hand `?year[]=x` to any of them and the (string) cast meets
 * an array, PHP 8 raises a warning, CodeIgniter promotes it to an uncaught
 * ErrorException, and the endpoint answers 500. Any logged-in account could
 * do it to every list and export in the app by editing the URL.
 *
 * @internal
 */
final class ScalarQueryParamsTest extends CIUnitTestCase
{
    private function runFilterWith(array $query): array
    {
        $request = Services::request(null, false);
        $request->setGlobal('get', $query);
        $_GET = $query;

        (new ScalarQueryParamsFilter())->before($request);

        return [$request->getGet(), $_GET];
    }

    public function testArrayParameterIsDropped(): void
    {
        [$get] = $this->runFilterWith(['year' => ['x'], 'stream' => 'BA']);

        $this->assertArrayNotHasKey('year', $get, 'array parameter survived into the controller');
        $this->assertSame('BA', $get['stream'], 'scalar sibling was lost');
    }

    public function testSuperglobalIsKeptInStep(): void
    {
        [, $superglobal] = $this->runFilterWith(['q' => ['x'], 'page' => '2']);

        $this->assertArrayNotHasKey('q', $superglobal, '$_GET still carries the array');
        $this->assertSame('2', $superglobal['page']);
    }

    /**
     * The controllers' own `?? ''` defaults then apply, so the screen renders
     * as if the parameter had simply been omitted -- which is the behaviour
     * Api::colleges() already chose for `?page[]=`.
     */
    public function testDroppedParameterLeavesTheDefaultToApply(): void
    {
        [$get] = $this->runFilterWith(['student_name' => ['a', 'b']]);

        $this->assertSame('', trim((string) ($get['student_name'] ?? '')));
    }

    public function testNestedArrayIsAlsoDropped(): void
    {
        [$get] = $this->runFilterWith(['clg_add' => ['deep' => ['deeper' => 'x']]]);

        $this->assertArrayNotHasKey('clg_add', $get);
    }

    /**
     * The overwhelmingly common case: nothing to do, and nothing changed.
     */
    public function testOrdinaryQueryStringIsUntouched(): void
    {
        $query = ['year' => '2026', 'stream' => 'BA', 'page' => '3', 'q' => 'sharma'];

        [$get] = $this->runFilterWith($query);

        $this->assertSame($query, $get);
    }

    public function testEmptyQueryStringIsUntouched(): void
    {
        [$get] = $this->runFilterWith([]);

        $this->assertSame([], $get);
    }

    /**
     * Reproduces what used to happen, so the reason this filter exists stays
     * visible: the cast every read site performs is what blows up.
     */
    public function testTheCastThatUsedToFailNowSucceeds(): void
    {
        [$get] = $this->runFilterWith(['year' => ['2026']]);

        // Exactly the expression used in Confirmations, Reminders, BulkEmail...
        $value = trim((string) ($get['year'] ?? ''));

        $this->assertSame('', $value);
    }
}
