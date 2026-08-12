<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

abstract class BaseController extends Controller
{
    /**
     * An array of helpers to be loaded automatically upon
     * class instantiation. These helpers will be available
     * to all controllers that extend BaseController.
     *
     * @var list<string>
     */
    protected $helpers = ['esection', 'url', 'form', 'html'];

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do not edit this line
        parent::initController($request, $response, $logger);

        // Preload helper globally
        helper(['esection', 'url', 'form', 'html']);
    }

    /**
     * One reply shape for a POST, whichever kind of caller sent it.
     *
     * An AJAX caller gets JSON carrying the message plus whatever that screen
     * needs to repaint itself, so the page updates in place -- one round trip
     * rather than "act, then fetch the table again".
     *
     * Anything else still gets the original redirect-with-flash. That is not
     * dead code: it keeps every screen working if JavaScript fails to load,
     * which is what makes the AJAX layer an enhancement rather than a
     * dependency.
     *
     * @param string|null $fallbackUrl Where the NON-AJAX path lands. Null means
     *        redirect()->back(). That distinction matters: five actions here are
     *        reached from a filtered, paginated URL (a batch detail page, or
     *        /bulk-email/log?status=failed&q=...&page=3) and CodeIgniter stores
     *        _ci_previous_url complete with its query string. Hardcoding a URL
     *        would drop a no-JavaScript operator on page 1 of an unfiltered list
     *        after every single row action.
     * @param array $extra Merged into the JSON body. By convention:
     *        'html'         re-rendered region(s) from the same partial the page used
     *        'state'        values the page's controls derive from
     *        'redirect_url' client navigates there on success
     *        'pdf_url'      client points its already-open tab at this
     *        'remaining'    row count left, so the client can leave an emptied page
     * @param int $failStatus Explicit, NOT derived from $ok. Callers here
     *        distinguish 422 (the operator can fix it) from 500 (we logged a
     *        trace); collapsing both into 422 would hide server faults from the
     *        access log and from anyone reading the network tab.
     */
    protected function respondToPost(
        bool $ok,
        string $message,
        ?string $fallbackUrl = null,
        array $extra = [],
        int $failStatus = 422
    ) {
        if (! $this->request->isAJAX()) {
            $redirect = $fallbackUrl === null
                ? redirect()->back()
                : redirect()->to($fallbackUrl);

            return $redirect->with($ok ? 'success' : 'error', $message);
        }

        return $this->response
            ->setStatusCode($ok ? 200 : $failStatus)
            ->setJSON(array_merge([
                'status'  => $ok ? 'success' : 'error',
                'message' => $message,
            ], $extra));
    }
}
