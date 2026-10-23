<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Reset;

defined('ABSPATH') || exit;

/**
 * The Corex footprint a soft reset acts on, gathered from WordPress by the command and
 * handed to the pure planner so the planner never reads WP itself: the active `corex-*`
 * add-on plugin files, the site-scoped `corex_*` option keys (including `corex_features_*`), the
 * seeded demo Home page id (null when none was seeded), the pages a kit touched, and whether the
 * kit recorded the reading settings it changed (spec 115).
 */
final class ResetInventory
{
    /**
     * @param list<string>    $addonPlugins     active corex-* plugin files (excludes core + theme)
     * @param list<string>    $optionKeys       corex_* option keys to delete
     * @param list<ResetPage> $pages            the pages a kit touched (spec 031, spec 115)
     * @param bool            $hasReadingRecord whether the front-page settings before the kit were recorded
     */
    public function __construct(
        public readonly array $addonPlugins = [],
        public readonly array $optionKeys = [],
        public readonly ?int $demoPageId = null,
        public readonly array $pages = [],
        public readonly bool $hasReadingRecord = false,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->addonPlugins === []
            && $this->optionKeys === []
            && $this->demoPageId === null
            && $this->pages === []
            && ! $this->hasReadingRecord;
    }
}
