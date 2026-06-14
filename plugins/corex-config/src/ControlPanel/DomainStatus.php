<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\ControlPanel;

defined('ABSPATH') || exit;

/**
 * The status of one configuration domain on the Corex control panel (spec 044). A pure
 * value object — `configured` / `needs_setup` / `error`, the human labels of what is
 * missing, and a link to where to fix it. Derived from settings values; persists nothing.
 */
final class DomainStatus
{
    public const CONFIGURED  = 'configured';
    public const NEEDS_SETUP = 'needs_setup';
    public const ERROR       = 'error';

    /**
     * @param list<string> $missing human labels of missing/invalid required items
     */
    public function __construct(
        public readonly string $domain,
        public readonly string $label,
        public readonly string $status,
        public readonly array $missing,
        public readonly string $setupLink,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->status === self::CONFIGURED;
    }
}
