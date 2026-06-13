<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\ControlPanel;

defined('ABSPATH') || exit;

/**
 * Renders the Corex control panel (spec 044, US1): the dashboard onboarding checklist and
 * one status card per configuration domain. All judgement lives in the pure
 * {@see ControlPanelStatus} / {@see OnboardingChecklist}; this only escapes + lays out their
 * output. Status is conveyed by icon + text (never color alone) for WCAG 2.2 AA.
 */
final class ControlPanelView
{
    public function __construct(
        private readonly ControlPanelStatus $status,
        private readonly OnboardingChecklist $checklist,
    ) {
    }

    /**
     * @param array<string,mixed> $values      settings values keyed by Config dot-key
     * @param array<string,bool>  $failedTests domain => true when its last test failed
     */
    public function render(array $values, array $failedTests = []): string
    {
        $domains = $this->status->domains($values, $failedTests);

        return '<div class="corex-panel">'
            . $this->renderChecklist($domains)
            . $this->renderCards($domains)
            . '</div>';
    }

    /**
     * @param list<DomainStatus> $domains
     */
    private function renderChecklist(array $domains): string
    {
        if ($this->checklist->allSet($domains)) {
            return '<div class="corex-onboarding is-complete"><p><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> '
                . esc_html__('You are all set — every Corex integration is configured.', 'corex')
                . '</p></div>';
        }

        $items = '';
        foreach ($this->checklist->steps($domains) as $step) {
            $items .= sprintf(
                '<li><a href="%s">%s</a></li>',
                esc_attr($step->link),
                esc_html($step->label),
            );
        }

        return '<div class="corex-onboarding"><h2>'
            . esc_html__('Finish setting up Corex', 'corex')
            . '</h2><ul class="corex-onboarding__list">' . $items . '</ul></div>';
    }

    /**
     * @param list<DomainStatus> $domains
     */
    private function renderCards(array $domains): string
    {
        $cards = '';
        foreach ($domains as $domain) {
            $cards .= $this->card($domain);
        }

        return '<div class="corex-panel__cards">' . $cards . '</div>';
    }

    private function card(DomainStatus $domain): string
    {
        $warning = '';
        if (! $domain->isConfigured() && $domain->missing !== []) {
            $warning = '<p class="corex-card__warning">'
                . sprintf(
                    /* translators: %s: comma-separated list of missing settings */
                    esc_html__('Missing: %s', 'corex'),
                    esc_html(implode(', ', $domain->missing)),
                )
                . ' <a href="' . esc_attr($domain->setupLink) . '">'
                . esc_html__('How to set this up', 'corex') . '</a></p>';
        }

        return sprintf(
            '<section class="corex-card is-%1$s" id="corex-domain-%2$s">'
            . '<header class="corex-card__head"><h3 class="corex-card__title">%3$s</h3>%4$s</header>%5$s</section>',
            esc_attr($domain->status),
            esc_attr($domain->domain),
            esc_html($domain->label),
            $this->badge($domain->status),
            $warning,
        );
    }

    private function badge(string $status): string
    {
        [$icon, $label] = match ($status) {
            DomainStatus::CONFIGURED => ['yes-alt', __('Configured', 'corex')],
            DomainStatus::ERROR      => ['dismiss', __('Error', 'corex')],
            default                  => ['warning', __('Needs setup', 'corex')],
        };

        return sprintf(
            '<span class="corex-badge is-%1$s"><span class="dashicons dashicons-%2$s" aria-hidden="true"></span> %3$s</span>',
            esc_attr($status),
            esc_attr($icon),
            esc_html($label),
        );
    }
}
