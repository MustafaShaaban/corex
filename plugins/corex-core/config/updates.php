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
    // Self-update source (spec 034). The URL of a JSON manifest describing the latest
    // framework release ({version, package, url, requires, tested}). Empty → updates are a
    // no-op: Corex never phones home unless you configure a source you control.
    // Read as `updates.endpoint`: option `corex_updates_endpoint`, `.env` name `UPDATES_ENDPOINT`.
    'endpoint' => '',
];
