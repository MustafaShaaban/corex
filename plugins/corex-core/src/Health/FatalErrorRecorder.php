<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Health;

defined('ABSPATH') || exit;

/**
 * Records that a request ended in a PHP fatal error (spec 118, OVR-D3). It runs at shutdown, after
 * WordPress's own fatal error handler (which does not exit), and asks PHP for the last error: on a
 * request without a fatal error it writes nothing. WordPress's recovery mode is left as it is.
 */
final class FatalErrorRecorder
{
    private const FATAL = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];

    public function __construct(private readonly FatalErrorLog $log)
    {
    }

    public function register(): void
    {
        register_shutdown_function([$this, 'capture']);
    }

    public function capture(): void
    {
        $error = error_get_last();
        if (! is_array($error) || ! in_array((int) ($error['type'] ?? 0), self::FATAL, true)) {
            return;
        }

        try {
            $this->log->record([
                'time' => time(),
                'type' => (int) $error['type'],
                ...FatalErrorSource::of((string) ($error['file'] ?? ''), $this->roots()),
            ]);
        } catch (\Throwable) {
            // A request that already failed must not fail again over its own record.
        }
    }

    /** @return array<string,string> */
    private function roots(): array
    {
        return array_filter([
            FatalErrorSource::MU_PLUGIN => defined('WPMU_PLUGIN_DIR') ? (string) WPMU_PLUGIN_DIR : '',
            FatalErrorSource::PLUGIN    => defined('WP_PLUGIN_DIR') ? (string) WP_PLUGIN_DIR : '',
            FatalErrorSource::THEME     => function_exists('get_theme_root') ? (string) get_theme_root() : '',
            FatalErrorSource::CORE      => defined('ABSPATH') ? (string) ABSPATH : '',
        ]);
    }
}
