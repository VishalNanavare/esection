<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Stores the default settings for the ContentSecurityPolicy, if you
 * choose to use it. The values here will be read in and set as defaults
 * for the site. If needed, they can be overridden on a page-by-page basis.
 *
 * Suggested reference for explanations:
 *
 * @see https://www.html5rocks.com/en/tutorials/security/content-security-policy/
 */
class ContentSecurityPolicy extends BaseConfig
{
    // -------------------------------------------------------------------------
    // Broadbrush CSP management
    // -------------------------------------------------------------------------

    /**
     * Default CSP report context
     */
    public bool $reportOnly = false;

    /**
     * Specifies a URL where a browser will send reports
     * when a content security policy is violated.
     */
    public ?string $reportURI = null;

    /**
     * Specifies a reporting endpoint to which violation reports ought to be sent.
     */
    public ?string $reportTo = null;

    /**
     * Instructs user agents to rewrite URL schemes, changing
     * HTTP to HTTPS. This directive is for websites with
     * large numbers of old URLs that need to be rewritten.
     */
    public bool $upgradeInsecureRequests = false;

    // -------------------------------------------------------------------------
    // CSP DIRECTIVES SETTINGS
    // NOTE: once you set a policy to 'none', it cannot be further restricted
    // -------------------------------------------------------------------------

    /**
     * Will default to `'self'` if not overridden
     *
     * @var list<string>|string|null
     */
    public $defaultSrc;

    /**
     * Lists allowed scripts' URLs.
     *
     * @var list<string>|string
     */
    // Inline <script> blocks carry nonce="{csp-script-nonce}"; CodeIgniter
    // swaps in a per-response nonce and appends nonce-<value> here. Once a
    // nonce is present browsers ignore 'unsafe-inline' for this directive,
    // which is the point -- injected script cannot guess the nonce.
    public $scriptSrc = 'self';

    /**
     * Specifies valid sources for JavaScript <script> elements.
     *
     * @var list<string>|string
     */
    public array|string $scriptSrcElem = 'self';

    /**
     * Specifies valid sources for JavaScript inline event
     * handlers and JavaScript URLs.
     *
     * @var list<string>|string
     */
    // 'none' blocks inline handler attributes (onclick=, onchange=). A nonce
    // authorises script ELEMENTS only and can never authorise an attribute,
    // so the two the app had were moved to addEventListener in layouts/app.php.
    public array|string $scriptSrcAttr = 'none';

    /**
     * Lists allowed stylesheets' URLs.
     *
     * @var list<string>|string
     */
    // 'unsafe-inline' is required, not laziness: 74 style="" attributes and 10
    // <style> blocks are spread across the views. Deliberately NOT nonced --
    // adding a style nonce would make browsers ignore 'unsafe-inline' and
    // every one of those 74 attributes would stop applying. Inline CSS is a
    // far smaller risk than inline script; revisit if the styles are ever
    // consolidated into stylesheets.
    public $styleSrc = ['self', 'unsafe-inline'];

    /**
     * Specifies valid sources for stylesheets <link> elements.
     *
     * @var list<string>|string
     */
    public array|string $styleSrcElem = ['self', 'unsafe-inline'];

    /**
     * Specifies valid sources for stylesheets inline
     * style attributes and `<style>` elements.
     *
     * @var list<string>|string
     */
    // Governs style="" specifically. Left at 'self' this would block all 74.
    public array|string $styleSrcAttr = 'unsafe-inline';

    /**
     * Defines the origins from which images can be loaded.
     *
     * @var list<string>|string
     */
    // data: kept for the bundled widgets (select2/flatpickr/sweetalert2) which
    // inline small icons. A data: image cannot execute, so the cost is nil.
    public $imageSrc = ['self', 'data:'];

    /**
     * Restricts the URLs that can appear in a page's `<base>` element.
     *
     * Will default to self if not overridden
     *
     * @var list<string>|string|null
     */
    // Stops injected markup repointing every relative URL via <base>.
    public $baseURI = 'self';

    /**
     * Lists the URLs for workers and embedded frame contents
     *
     * @var list<string>|string
     */
    // The app renders no iframes and no workers.
    public $childSrc = 'none';

    /**
     * Limits the origins that you can connect to (via XHR,
     * WebSockets, and EventSource).
     *
     * @var list<string>|string
     */
    public $connectSrc = 'self';

    /**
     * Specifies the origins that can serve web fonts.
     *
     * @var list<string>|string
     */
    // Font Awesome ships locally under public/assets.
    public $fontSrc = 'self';

    /**
     * Lists valid endpoints for submission from `<form>` tags.
     *
     * @var list<string>|string
     */
    public $formAction = 'self';

    /**
     * Specifies the sources that can embed the current page.
     * This directive applies to `<frame>`, `<iframe>`, `<embed>`,
     * and `<applet>` tags. This directive can't be used in
     * `<meta>` tags and applies only to non-HTML resources.
     *
     * @var list<string>|string|null
     */
    // Clickjacking: the CSP equivalent of the X-Frame-Options header the
    // SecurityHeadersFilter already sends. Modern browsers prefer this one.
    public $frameAncestors = 'self';

    /**
     * The frame-src directive restricts the URLs which may
     * be loaded into nested browsing contexts.
     *
     * @var list<string>|string|null
     */
    public $frameSrc = 'none';

    /**
     * Restricts the origins allowed to deliver video and audio.
     *
     * @var list<string>|string|null
     */
    // 'self' also happens to neutralise the protestware in the bundled
    // sweetalert2 v11.12.4, which tries to autoplay audio from an external
    // host. Its trigger (Russian browser locale AND a .ru/.su/.by host)
    // cannot fire on this deployment, but blocking it costs nothing.
    public $mediaSrc = 'self';

    /**
     * Allows control over Flash and other plugins.
     *
     * @var list<string>|string
     */
    // No <object>/<embed>/<applet> anywhere; 'none' kills a classic XSS vector.
    public $objectSrc = 'none';

    /**
     * @var list<string>|string|null
     */
    public $manifestSrc;

    /**
     * @var list<string>|string
     */
    public array|string $workerSrc = 'none';

    /**
     * Limits the kinds of plugins a page may invoke.
     *
     * @var list<string>|string|null
     */
    public $pluginTypes;

    /**
     * List of actions allowed.
     *
     * @var list<string>|string|null
     */
    public $sandbox;

    /**
     * Nonce placeholder for style tags.
     */
    public string $styleNonceTag = '{csp-style-nonce}';

    /**
     * Nonce placeholder for script tags.
     */
    public string $scriptNonceTag = '{csp-script-nonce}';

    /**
     * Replace nonce tag automatically?
     */
    public bool $autoNonce = true;
}
