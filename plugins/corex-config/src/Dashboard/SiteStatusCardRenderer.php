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

        echo '<div class="card"><h2>' . esc_html__('Site status', 'corex') . '</h2>';

        if ($model['isEmptyState']) {
            echo '<p>' . esc_html__('No starter kit applied yet.', 'corex') . ' '
                . '<a href="' . esc_url(admin_url('admin.php?page=corex-addons')) . '">'
                . esc_html__('Enable a kit to build your site.', 'corex') . '</a></p></div>';

            return;
        }

        echo '<p><strong>' . esc_html__('Applied kits:', 'corex') . '</strong> '
            . esc_html($model['appliedKits'] === [] ? __('none', 'corex') : implode(', ', $model['appliedKits'])) . '</p>';

        echo '<p><strong>' . esc_html__('Form submissions:', 'corex') . '</strong> '
            . '<a href="' . esc_url($model['submissionsUrl']) . '">'
            . esc_html((string) $model['submissionCount']) . '</a></p>';

        echo '<p><strong>' . esc_html__('Front page:', 'corex') . '</strong> '
            . esc_html($this->frontPageLabel($model['frontPage'])) . '</p></div>';
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
