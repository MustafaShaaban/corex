<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Multisite;

defined('ABSPATH') || exit;

/**
 * Exposes the site identity without coupling consumers to WordPress globals (spec FR-001).
 */
interface SiteContext
{
    public function id(): int;

    public function networkId(): int;

    public function isMainSite(): bool;

    public function url(): string;
}
