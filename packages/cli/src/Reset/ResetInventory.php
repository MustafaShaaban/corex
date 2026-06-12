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
 * add-on plugin files, the `corex_*` option keys (including `corex_features_*`), and the
 * seeded demo Home page id (null when none was seeded).
 */
final class ResetInventory
{
    /**
     * @param list<string> $addonPlugins active corex-* plugin files (excludes core + theme)
     * @param list<string> $optionKeys   corex_* option keys to delete
     * @param list<int>    $pageIds      kit-seeded page ids to remove (spec 031)
     */
    public function __construct(
        public readonly array $addonPlugins = [],
        public readonly array $optionKeys = [],
        public readonly ?int $demoPageId = null,
        public readonly array $pageIds = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->addonPlugins === []
            && $this->optionKeys === []
            && $this->demoPageId === null
            && $this->pageIds === [];
    }
}
