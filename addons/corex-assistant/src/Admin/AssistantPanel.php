<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Admin;

defined('ABSPATH') || exit;

use Corex\Assistant\AssistantSettings;
use Corex\Assistant\Chain\Cooldowns;
use Corex\Assistant\Chain\Provider;
use Corex\Assistant\Limits\VisitorLimits;

/**
 * What Settings → Assistant shows under its fields (spec 129, FR-014): each service's state, today's
 * questions against the cap, and "Check services". Drawn through the `corex_settings_section_extra`
 * filter, so the Settings screen does not have to know the add-on.
 */
final class AssistantPanel
{
    public const SECTION = 'assistant';

    /**
     * @param \Closure(int): string $when a moment as the site writes dates
     */
    public function __construct(
        private readonly AssistantSettings $settings,
        private readonly Cooldowns $cooldowns,
        private readonly VisitorLimits $limits,
        private readonly \Closure $when,
    ) {
    }

    public function extra(mixed $html, mixed $sectionKey): string
    {
        $html = is_string($html) ? $html : '';

        return $sectionKey === self::SECTION ? $html . $this->render() : $html;
    }

    public function render(): string
    {
        $rows = '';
        foreach (Provider::CATALOGUE as $id => $entry) {
            $rows .= sprintf(
                '<tr><th scope="row">%1$s</th><td>%2$s</td><td>%3$s</td></tr>',
                esc_html($entry['label']),
                esc_html($this->settings->model($id)),
                esc_html($this->status($id)),
            );
        }

        return sprintf(
            '<div class="corex-assistant-panel" data-corex-assistant-panel>'
            . '<h3>%1$s</h3><p>%2$s</p>'
            . '<table class="widefat striped corex-assistant-panel__services"><thead><tr><th scope="col">%3$s</th><th scope="col">%4$s</th><th scope="col">%5$s</th></tr></thead><tbody>%6$s</tbody></table>'
            . '<p>%7$s</p>'
            . '<p><button type="button" class="button" data-corex-assistant-check %8$s>%9$s</button> <span class="description">%10$s</span></p>'
            . '<div class="corex-assistant-panel__results" role="status" aria-live="polite" data-corex-assistant-results></div>'
            . '</div>',
            esc_html__('Services', 'corex'),
            esc_html__('Asked in this order. A service whose free limit is spent rests, and the next one answers. Visitors never see which service answered.', 'corex'),
            esc_html__('Service', 'corex'),
            esc_html__('Model', 'corex'),
            esc_html__('Status', 'corex'),
            $rows,
            esc_html(sprintf(
                /* translators: 1: questions asked today, 2: the daily limit */
                __('Questions today, whole site: %1$d of %2$d.', 'corex'),
                $this->limits->siteToday(),
                $this->limits->sitePerDay(),
            )),
            $this->settings->chain() === [] ? 'disabled' : '',
            esc_html__('Check services', 'corex'),
            esc_html__('Sends one short question to each service that has a key. Save new keys first.', 'corex'),
        );
    }

    private function status(string $id): string
    {
        if ($this->settings->key($id) === '') {
            return __('No key: skipped', 'corex');
        }
        $until = $this->cooldowns->until($id);
        if ($until > 0) {
            return sprintf(
                /* translators: %s: a date and time */
                __('Resting until %s', 'corex'),
                ($this->when)($until),
            );
        }

        return __('Ready', 'corex');
    }
}
