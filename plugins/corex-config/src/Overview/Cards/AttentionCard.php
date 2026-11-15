<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Overview\Cards;

defined('ABSPATH') || exit;

use Corex\Config\Overview\OverviewCard;
use Corex\Notifications\NotificationService;

/**
 * "Attention required": the viewer's unread notifications, counted as the bell counts them, a
 * group of repeated ones once (spec 072 FR-019, spec 118 US-SHL-09), with a link to the center.
 */
final class AttentionCard implements OverviewCard
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function key(): string
    {
        return 'attention';
    }

    public function title(): string
    {
        return __('Attention required', 'corex');
    }

    public function render(array $settingValues): string
    {
        $count = $this->notifications->unreadCountForCurrentActor();
        $note  = $count > 0
            ? _n(
                'unread notification needs your attention.',
                'unread notifications need your attention.',
                $count,
                'corex',
            )
            : __('Nothing needs your attention right now.', 'corex');

        return '<section class="corex-surface corex-overview__card corex-overview__card--compact" data-card="attention">'
            . '<header class="corex-overview__card-head"><h2>' . esc_html($this->title()) . '</h2></header>'
            . '<p class="corex-overview__big">' . esc_html(number_format_i18n($count)) . '</p>'
            . '<p class="corex-overview__muted">' . esc_html($note) . '</p>'
            . '<p><a href="' . esc_url(admin_url('admin.php?page=corex-notifications')) . '">'
            . esc_html__('Open Notifications', 'corex') . '</a></p></section>';
    }
}
