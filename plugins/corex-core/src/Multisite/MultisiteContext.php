<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Multisite;

defined('ABSPATH') || exit;

/**
 * Keeps install-shape checks injectable across every CoreX execution context (spec FR-001).
 */
interface MultisiteContext
{
    public function enabled(): bool;

    public function subdomainInstall(): bool;

    public function isNetworkAdmin(): bool;

    public function isSwitched(): bool;
}
