<?php

namespace Config;

use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\FrameworkException;
use CodeIgniter\HotReloader\HotReloader;

/*
 * --------------------------------------------------------------------
 * Application Events
 * --------------------------------------------------------------------
 * Events allow you to tap into the execution of the program without
 * modifying or extending core files. This file provides a central
 * location to define your events, though they can always be added
 * at run-time, also, if needed.
 *
 * You create code that can execute by subscribing to events with
 * the 'on()' method. This accepts any form of callable, including
 * Closures, that will be executed when the event is triggered.
 *
 * Example:
 *      Events::on('create', [$myInstance, 'myMethod']);
 */

/*
 * Runtime hardening for two php.ini values this app cannot fix at the source.
 *
 * D:\webserver\php\8.5\php.ini is shared with eleven other vhosts on this
 * box -- the same reason SecurityHeadersFilter strips X-Powered-By in PHP
 * rather than setting expose_php=Off. Both settings below are per-request, so
 * they harden E-Section without touching any sibling project.
 *
 * pre_system is the right hook: it runs before the session service is ever
 * constructed, which is the only point where use_strict_mode can still matter.
 */
Events::on('pre_system', static function (): void {
    // php.ini has session.use_strict_mode=0, which means PHP will ADOPT a
    // session id supplied by the client instead of rejecting an unknown one.
    // That is the precondition for session fixation: plant a known id in a
    // victim's browser, wait for them to log in, reuse the id. Login already
    // regenerates the id, which closes the main path -- this closes the
    // window before login as well, and costs nothing.
    ini_set('session.use_strict_mode', '1');

    // php.ini sets max_execution_time=0 (no limit). nginx gives up on this
    // vhost after fastcgi_read_timeout 600, so any request still running past
    // 600s is producing a response no client will ever receive -- it is only
    // holding a php-cgi worker. Capping at the same 600 changes nothing a user
    // can observe and bounds the damage from a slow or looping request.
    //
    // CLI is deliberately exempt: spark commands and the backup task are
    // expected to run long and have no proxy in front of them.
    if (! is_cli()) {
        ini_set('max_execution_time', '600');
    }
});

Events::on('pre_system', static function (): void {
    if (ENVIRONMENT !== 'testing') {
        $value = ini_get('zlib.output_compression');

        if (filter_var($value, FILTER_VALIDATE_BOOLEAN) || (int) $value > 0) {
            throw FrameworkException::forEnabledZlibOutputCompression();
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        ob_start(static fn ($buffer) => $buffer);
    }

    /*
     * --------------------------------------------------------------------
     * Debug Toolbar Listeners.
     * --------------------------------------------------------------------
     * If you delete, they will no longer be collected.
     */
    if (CI_DEBUG && ! is_cli()) {
        Events::on('DBQuery', 'CodeIgniter\Debug\Toolbar\Collectors\Database::collect');
        service('toolbar')->respond();
        // Hot Reload route - for framework use on the hot reloader.
        if (ENVIRONMENT === 'development') {
            service('routes')->get('__hot-reload', static function (): void {
                (new HotReloader())->run();
            });
        }
    }
});
