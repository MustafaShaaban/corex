<?php

/**
 * Framework-shipped default configuration (lowest precedence layer).
 * Overridden by WordPress options, then by a project-root .env.
 *
 * @package Corex
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

return [
    'name'      => 'Corex',
    'env'       => 'production',
    'providers' => [],

    // Generator target (set by `wp corex init`). Empty path → the CLI provider
    // falls back to a default under wp-content.
    'namespace' => 'App',
    'prefix'    => 'corex',
    'path'      => '',

    // Self-update source (spec 034). The URL of a JSON manifest describing the latest
    // framework release ({version, package, url, requires, tested}). Empty → updates are a
    // no-op: Corex never phones home unless you configure a source you control.
    'updates'   => [
        'endpoint' => '',
    ],
];
