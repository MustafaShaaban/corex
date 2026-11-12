<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Overview\Cards;

defined('ABSPATH') || exit;

use Corex\Config\Overview\OverviewCard;
use Corex\Config\Overview\OverviewFacts;
use Corex\Config\Overview\OverviewModel;

/**
 * Analytics & security: PageSpeed, the captcha, transactional email and login protection, each in
 * the words of what is so (spec 114, US-SAFE-10).
 */
final class IntegrationsCard implements OverviewCard
{
    public function __construct(
        private readonly OverviewModel $model,
        private readonly OverviewFacts $facts,
    ) {
    }

    public function key(): string
    {
        return 'integrations';
    }

    public function title(): string
    {
        return __('Analytics & security', 'corex');
    }

    public function render(array $settingValues): string
    {
        $rows = '';
        foreach ($this->model->integrations($this->facts->gather($settingValues)) as $row) {
            $rows .= '<li class="corex-overview__integration is-' . esc_attr($row['tone']) . '">'
                . '<span class="corex-overview__integration-label">' . esc_html($row['label']) . '</span>'
                . '<span class="corex-overview__integration-note">' . esc_html($row['note']) . '</span></li>';
        }

        return '<section class="corex-surface corex-overview__card" data-card="integrations">'
            . '<header class="corex-overview__card-head"><h2>' . esc_html($this->title()) . '</h2>'
            . '<a class="corex-overview__link" href="' . esc_url(admin_url('admin.php?page=corex-operations-security')) . '">'
            . esc_html__('Details', 'corex') . ' &rarr;</a></header>'
            . '<ul class="corex-overview__integrations">' . $rows . '</ul></section>';
    }
}
