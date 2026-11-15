<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Overview;

defined('ABSPATH') || exit;

/**
 * One card of the CoreX Overview (spec 118). Each card reads its own facts and draws itself, and
 * the {@see OverviewRenderer} draws it on its own: a card that throws becomes a "Could not load"
 * card and the others are untouched (US-OVR-06). A card never catches its own failure to draw a 0.
 */
interface OverviewCard
{
    /** A stable key, used in the failure's log line and as the card's `data-card`. */
    public function key(): string;

    /** The heading the card is drawn under, also when it could not load. */
    public function title(): string;

    /**
     * @param array<string,mixed> $settingValues settings values keyed by Config dot-key
     */
    public function render(array $settingValues): string;
}
