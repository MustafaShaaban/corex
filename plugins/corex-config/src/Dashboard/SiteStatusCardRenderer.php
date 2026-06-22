<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Dashboard;

use Corex\Config\Data\SubmissionsReader;
use Corex\Provisioning\KitProvisioner;
use Corex\Provisioning\PageContent;

defined('ABSPATH') || exit;

/**
 * Renders the Corex dashboard "Site status" card (spec 042): which kits are applied, the live contact-submission
 * count linked to Corex → Data, and the current front-page status. The boundary that reads live WordPress state
 * and feeds the pure {@see SiteStatusCard} view model, then prints escaped, translated markup. Degrades
 * gracefully — with no kit framework or no submissions it shows an actionable empty state, never an error.
 */
final class SiteStatusCardRenderer
{
    public function __construct(
        private readonly KitProvisioner $provisioner,
        private readonly SubmissionsReader $submissions,
        private readonly SiteStatusCard $card,
        private readonly PageContent $content,
    ) {
    }

    public function render(): void
    {
        $model = $this->card->model(
            $this->appliedKitLabels(),
            $this->submissionCount(),
            admin_url('admin.php?page=corex-data'),
            $this->frontPageStatus(),
        );

        echo '<section class="corex-site-status"><div class="corex-site-status__head">'
            . '<p class="corex-admin__eyebrow">' . esc_html__('LIVE FRAMEWORK STATE', 'corex') . '</p>'
            . '<h2>' . esc_html__('Site status', 'corex') . '</h2></div>';

        if ($model['isEmptyState']) {
            echo '<div class="corex-state corex-state--empty" role="status"><div><h3>'
                . esc_html__('No starter kit applied yet', 'corex') . '</h3><p>'
                . '<a href="' . esc_url(admin_url('admin.php?page=corex-addons')) . '">'
                . esc_html__('Enable a kit to build your site.', 'corex') . '</a></p></div></div></section>';

            return;
        }

        echo '<div class="corex-stat-grid">';
        $this->renderStat(
            __('Applied kits', 'corex'),
            (string) count($model['appliedKits']),
            $model['appliedKits'] === [] ? __('None applied', 'corex') : implode(', ', $model['appliedKits']),
        );
        $this->renderStat(
            __('Form submissions', 'corex'),
            (string) $model['submissionCount'],
            __('Open CoreX Data', 'corex'),
            $model['submissionsUrl'],
        );
        $this->renderStat(
            __('Front page', 'corex'),
            $this->frontPageLabel($model['frontPage']),
            __('Current WordPress reading setting', 'corex'),
        );
        echo '</div></section>';
    }

    private function renderStat(string $label, string $value, string $detail, string $url = ''): void
    {
        $valueHtml = $url === ''
            ? esc_html($value)
            : '<a href="' . esc_url($url) . '">' . esc_html($value) . '</a>';

        echo '<article class="corex-stat-card"><p class="corex-stat-card__label">' . esc_html($label) . '</p>'
            . '<p class="corex-stat-card__value">' . wp_kses_post($valueHtml) . '</p>'
            . '<p class="corex-stat-card__detail">' . esc_html($detail) . '</p></article>';
    }

    /**
     * @return list<string>
     */
    private function appliedKitLabels(): array
    {
        $labels = [];

        foreach ($this->provisioner->applicableKits() as $kit) {
            if ($kit->applied) {
                $labels[] = $kit->label;
            }
        }

        return $labels;
    }

    private function submissionCount(): int
    {
        try {
            return $this->submissions->total();
        } catch (\Throwable) {
            return 0; // forms add-on inactive / source unavailable — degrade to zero, never error
        }
    }

    private function frontPageStatus(): string
    {
        if (get_option('show_on_front') !== 'page') {
            return SiteStatusCard::FRONT_BLOG_INDEX;
        }

        $frontId = (int) get_option('page_on_front');
        $content = (string) get_post_field('post_content', $frontId);

        return $this->content->isBlank($content) ? SiteStatusCard::FRONT_BLANK : SiteStatusCard::FRONT_COREX_PAGE;
    }

    private function frontPageLabel(string $status): string
    {
        return match ($status) {
            SiteStatusCard::FRONT_COREX_PAGE => __('a static page', 'corex'),
            SiteStatusCard::FRONT_BLANK      => __('a blank page — add content', 'corex'),
            default                          => __('the blog posts index', 'corex'),
        };
    }
}
