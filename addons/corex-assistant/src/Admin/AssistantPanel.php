<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Admin;

defined('ABSPATH') || exit;

use Corex\Assistant\AssistantSettings;
use Corex\Assistant\Booking\SlotsController;
use Corex\Assistant\Chain\Cooldowns;
use Corex\Assistant\Chain\Provider;
use Corex\Assistant\Limits\PaidAnswers;
use Corex\Assistant\Limits\VisitorLimits;

/**
 * What Settings → Assistant shows under its fields (spec 129, FR-014, FR-019): under Services, each
 * service in the owner's order with Free or Paid, On or Off, its state and how it last failed,
 * today's questions and paid answers, and "Check services"; under Booking, the next time a visitor
 * would be offered. Drawn through the `corex_settings_section_extra` filter, so the Settings screen
 * does not have to know the add-on.
 */
final class AssistantPanel
{
    /** The Settings page the assistant's sections are on (its `section` address). */
    public const PAGE = 'assistant';

    public const SERVICES = 'assistant_services';
    public const BOOKING = 'assistant_booking';

    /**
     * @param \Closure(int): string $when a moment as the site writes dates
     */
    public function __construct(
        private readonly AssistantSettings $settings,
        private readonly Cooldowns $cooldowns,
        private readonly VisitorLimits $limits,
        private readonly PaidAnswers $paid,
        private readonly SlotsController $slots,
        private readonly \Closure $when,
    ) {
    }

    public function extra(mixed $html, mixed $sectionKey): string
    {
        $html = is_string($html) ? $html : '';

        return match ($sectionKey) {
            self::SERVICES => $html . $this->render(),
            self::BOOKING => $html . $this->booking(),
            default => $html,
        };
    }

    public function render(): string
    {
        $rows = '';
        foreach ($this->settings->order() as $place => $id) {
            $rows .= sprintf(
                '<tr><td>%1$d</td><th scope="row">%2$s</th><td>%3$s</td><td>%4$s</td><td>%5$s</td><td>%6$s</td></tr>',
                $place + 1,
                esc_html(Provider::CATALOGUE[$id]['label']),
                Provider::isPaid($id) ? esc_html__('Paid', 'corex') : esc_html__('Free', 'corex'),
                $this->settings->isOn($id) ? esc_html__('On', 'corex') : esc_html__('Off', 'corex'),
                esc_html($this->settings->model($id)),
                $this->status($id),
            );
        }

        return sprintf(
            '<div class="corex-assistant-panel" data-corex-assistant-panel>'
            . '<h3>%1$s</h3><p>%2$s</p>'
            . '<table class="widefat striped corex-assistant-panel__services"><thead><tr><th scope="col">%3$s</th><th scope="col">%4$s</th>'
            . '<th scope="col">%5$s</th><th scope="col">%6$s</th><th scope="col">%7$s</th><th scope="col">%8$s</th></tr></thead><tbody>%9$s</tbody></table>'
            . '<p>%10$s</p>'
            . '<p><button type="button" class="button" data-corex-assistant-check %11$s>%12$s</button> <span class="description">%13$s</span></p>'
            . '<div class="corex-assistant-panel__results" role="status" aria-live="polite" data-corex-assistant-results></div>'
            . '</div>',
            esc_html__('How the services are doing', 'corex'),
            esc_html__('As saved, in the order they are asked. A service whose limit is spent, or that failed, rests, and the next one answers. Visitors never see which service answered.', 'corex'),
            esc_html__('Place', 'corex'),
            esc_html__('Service', 'corex'),
            esc_html__('Cost', 'corex'),
            esc_html__('Switch', 'corex'),
            esc_html__('Model', 'corex'),
            esc_html__('Status', 'corex'),
            $rows,
            esc_html(sprintf(
                /* translators: 1: questions asked today, 2: the daily limit, 3: paid answers today, 4: their daily limit */
                __('Today: %1$d of %2$d questions for the whole site, %3$d of %4$d paid answers.', 'corex'),
                $this->limits->siteToday(),
                $this->limits->sitePerDay(),
                $this->paid->today(),
                $this->paid->cap(),
            )),
            $this->settings->keyed() === [] ? 'disabled' : '',
            esc_html__('Check services', 'corex'),
            esc_html__('Asks every service that has a key, on or off, a visitor\'s question with your site\'s content, and says how long each took. Paid ones charge for that one answer. Save new keys first.', 'corex'),
        );
    }

    /** The next time the callback form would offer, on the business's clock. */
    public function booking(): string
    {
        if (! $this->settings->bookingEnabled()) {
            return '';
        }

        $slots = $this->slots->offered();
        $text = $slots === []
            ? __('With these hours no time is offered now, so the form asks for the best time instead. Check the days and the hours.', 'corex')
            : sprintf(
                /* translators: 1: a date and time, 2: how many times are offered */
                _n('The next time offered is %1$s; %2$d time in all.', 'The next time offered is %1$s; %2$d times in all.', count($slots), 'corex'),
                ($this->when)($slots[0]),
                count($slots),
            );

        return '<p class="corex-assistant-panel__booking" data-corex-assistant-booking>' . esc_html($text) . '</p>';
    }

    /** Ready, Resting until a time, or No key, and how it last failed, escaped. */
    private function status(string $id): string
    {
        if ($this->settings->key($id) === '') {
            return esc_html__('No key: skipped', 'corex');
        }

        $until = $this->cooldowns->until($id);
        $state = $until > 0
            /* translators: %s: a date and time */
            ? sprintf(__('Resting until %s', 'corex'), ($this->when)($until))
            : __('Ready', 'corex');
        $failure = $this->cooldowns->lastFailure($id);
        if ($failure === null) {
            return esc_html($state);
        }

        return esc_html($state) . '<br /><span class="description">' . esc_html(sprintf(
            /* translators: 1: a date and time, 2: what kind of failure, such as limit, 3: the service's own words */
            __('Last failed %1$s: %2$s. %3$s', 'corex'),
            ($this->when)($failure['at']),
            trim($failure['kind'] . ' ' . ($failure['status'] > 0 ? (string) $failure['status'] : '')),
            $failure['detail'],
        )) . '</span>';
    }
}
