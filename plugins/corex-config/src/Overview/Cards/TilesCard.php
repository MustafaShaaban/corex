<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Overview\Cards;

defined('ABSPATH') || exit;

use Corex\Config\Overview\OverviewCard;
use Corex\Config\Overview\OverviewFacts;
use Corex\Config\Overview\OverviewTileFacts;
use Corex\Config\Overview\OverviewTiles;

/**
 * The Overview's first row: four state tiles (spec 118, US-OVR-04). Login protection is read with
 * the same rule as the analytics and security card, from {@see OverviewFacts}.
 */
final class TilesCard implements OverviewCard
{
    public function __construct(
        private readonly OverviewTiles $tiles,
        private readonly OverviewTileFacts $facts,
        private readonly OverviewFacts $overviewFacts,
    ) {
    }

    public function key(): string
    {
        return 'tiles';
    }

    public function title(): string
    {
        return __('Site state', 'corex');
    }

    public function render(array $settingValues): string
    {
        $tiles = $this->tiles->build($this->facts->gather(time()) + [
            'login' => $this->overviewFacts->gather($settingValues)['login'],
        ]);

        $cells = '';
        foreach ($tiles as $tile) {
            $cells .= '<article class="corex-overview__tile is-' . esc_attr($tile['tone']) . '" data-tile="' . esc_attr($tile['key']) . '">'
                . '<p class="corex-overview__tile-label"><span class="corex-overview__tile-icon corex-admin__nav-icon corex-admin__nav-icon--'
                . esc_attr($tile['icon']) . '" aria-hidden="true"></span>' . esc_html($tile['label']) . '</p>'
                . '<p class="corex-overview__tile-value">' . esc_html($tile['value']) . '</p>'
                . ($tile['detail'] === '' ? '' : '<p class="corex-overview__tile-detail">' . esc_html($tile['detail']) . '</p>')
                . ($tile['meta'] === '' ? '' : '<p class="corex-overview__tile-meta">' . esc_html($tile['meta']) . '</p>')
                . '<a class="corex-overview__tile-link" href="' . esc_url(admin_url($tile['href'])) . '">'
                . esc_html($tile['link']) . '</a></article>';
        }

        return '<div class="corex-overview__tiles" data-card="tiles">' . $cells . '</div>';
    }
}
