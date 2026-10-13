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
];
