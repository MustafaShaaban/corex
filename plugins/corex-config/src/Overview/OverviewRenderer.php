<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Overview;

use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OperationsModeStore;

defined('ABSPATH') || exit;

/**
 * Lays out the CoreX Overview (spec 064, rebuilt by spec 118): the mode bar, the four state tiles,
 * then the launch readiness card beside the forms and data cards, then attention, analytics and
 * security, and recent activity.
 *
 * Each card is drawn on its own (US-OVR-06). A card that throws is replaced by a "Could not load"
 * card from {@see OverviewCardFailure}, which logs a reference; every other card still renders and
 * the page never becomes a critical error. A failed card never shows a 0 or an empty state.
 */
final class OverviewRenderer
{
    /**
     * @param array<string,OverviewCard> $cards keyed by place: tiles, readiness, forms, data,
     *                                          attention, integrations, activity
     */
    public function __construct(
        private readonly OperationsMode $mode,
        private readonly OperationsModeStore $modeStore,
        private readonly OverviewCardFailure $failure,
        private readonly array $cards,
    ) {
    }

    /**
     * @param array<string,mixed> $settingValues settings values keyed by Config dot-key
     */
    public function render(array $settingValues): string
    {
        $card = fn (string $place): string => $this->drawn($place, $settingValues);

        return '<div class="corex-overview">'
            . $this->envBar($this->mode->describe($this->modeStore->current()))
            . $card('tiles')
            . '<div class="corex-overview__grid corex-overview__grid--primary">'
            . $card('readiness')
            . '<div class="corex-overview__col">' . $card('forms') . $card('data') . '</div></div>'
            . '<div class="corex-overview__grid corex-overview__grid--secondary">'
            . $card('attention') . $card('integrations') . $card('activity')
            . '</div></div>';
    }

    /** @param array<string,mixed> $settingValues */
    private function drawn(string $place, array $settingValues): string
    {
        $card = $this->cards[$place] ?? null;
        if ($card === null) {
            return '';
        }

        try {
            return $card->render($settingValues);
        } catch (\Throwable $error) {
            return $this->failure->report($card, $error);
        }
    }

    /**
     * @param array{mode:string,label:string,tone:string,detail:string} $env
     */
    private function envBar(array $env): string
    {
        return '<section class="corex-overview__env corex-surface is-' . esc_attr($env['tone']) . '">'
            . '<span class="corex-overview__env-badge">' . esc_html($env['label']) . '</span>'
            . '<span class="corex-overview__env-detail">' . esc_html($env['detail']) . '</span>'
            . $this->versionBadge() . '</section>';
    }

    /**
     * The real installed CoreX framework version, from the corex-core plugin constant. It
     * renders in the environment bar so operators can confirm exactly which build is live.
     */
    private function versionBadge(): string
    {
        $version = defined('COREX_CORE_VERSION') ? (string) COREX_CORE_VERSION : '';
        if ($version === '') {
            return '';
        }

        return '<span class="corex-overview__env-version">' . esc_html(sprintf(
            /* translators: %s: the installed CoreX framework version, e.g. 0.33.0 */
            __('CoreX v%s', 'corex'),
            $version,
        )) . '</span>';
    }
}
