<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Admin;

defined('ABSPATH') || exit;

use Corex\Assistant\AssistantSettings;
use Corex\Assistant\Booking\BookingHours;
use Corex\Assistant\Chain\Cooldowns;
use Corex\Assistant\Chain\Provider;
use Corex\Assistant\Limits\VisitorLimits;

/**
 * The cards of CoreX → Assistant (spec 129, FR-014): the assistant and its look, the services in the
 * owner's order, the booking hours, the limits, and "Check services". Every value is escaped here; the
 * form posts to {@see AssistantScreen::save()}.
 */
final class AssistantScreenView
{
    /**
     * @param \Closure(string): bool  $locked    whether `.env` sets this Config key
     * @param \Closure(): list<string> $languages the languages the owner writes copy for
     * @param \Closure(int): string   $when      a moment as the site writes dates
     * @param list<string>            $previewZones zones the booking hours are shown in
     */
    public function __construct(
        private readonly AssistantSettings $settings,
        private readonly Cooldowns $cooldowns,
        private readonly VisitorLimits $limits,
        private readonly \Closure $locked,
        private readonly \Closure $languages,
        private readonly \Closure $when,
        private readonly array $previewZones,
    ) {
    }

    public function cards(): string
    {
        return $this->assistantCard() . $this->servicesCard() . $this->bookingCard() . $this->limitsCard() . $this->checkCard();
    }

    private function assistantCard(): string
    {
        $copy = '';
        foreach (($this->languages)() as $language) {
            $saved = $this->settings->brandCopy($language);
            $suggestions = '';
            for ($i = 0; $i < AssistantSettings::MAX_SUGGESTIONS; $i++) {
                $suggestions .= sprintf(
                    '<input type="text" class="regular-text" name="brand[copy][%1$s][suggestions][]" value="%2$s" aria-label="%3$s" />',
                    esc_attr($language),
                    esc_attr($saved['suggestions'][$i] ?? ''),
                    /* translators: 1: a number, 2: a language code such as ar. */
                    esc_attr(sprintf(__('Suggested question %1$d (%2$s)', 'corex'), $i + 1, $language)),
                );
            }
            $copy .= sprintf(
                '<fieldset class="corex-assistant-copy" lang="%1$s"><legend>%2$s</legend>'
                . self::row('name-' . $language, self::literal(__('Name', 'corex')), '<input type="text" class="regular-text" id="corex-assistant-name-%1$s" name="brand[copy][%1$s][name]" value="%3$s" />')
                . self::row('welcome-' . $language, self::literal(__('Welcome', 'corex')), '<textarea class="large-text" rows="2" id="corex-assistant-welcome-%1$s" name="brand[copy][%1$s][welcome]">%4$s</textarea>')
                . '<p class="corex-assistant-copy__suggestions"><span>%5$s</span>%6$s</p></fieldset>',
                esc_attr($language),
                /* translators: %s: a language code, such as ar. */
                esc_html(sprintf(__('Words in %s (blank: the built-in ones)', 'corex'), $language)),
                esc_attr($saved['name'] ?? ''),
                esc_textarea($saved['welcome'] ?? ''),
                esc_html__('Suggested questions', 'corex'),
                $suggestions,
            );
        }

        return self::card(
            'assistant',
            __('Assistant', 'corex'),
            __('A chat button on every public page. It shows once the switch is on and at least one service below is on with a key.', 'corex'),
            self::row('enabled', __('Show the assistant', 'corex'), ($this->locked)(AssistantSettings::ENABLED)
                ? esc_html($this->settings->enabled() ? __('On, set in the site’s .env file', 'corex') : __('Off, set in the site’s .env file', 'corex'))
                : self::toggle('enabled', 'corex-assistant-enabled', $this->settings->enabled()))
            . self::row('icon', __('Button image', 'corex'), sprintf(
                '<input type="url" class="regular-text" id="corex-assistant-icon" name="brand[icon]" value="%1$s" placeholder="https://" />'
                . ' <button type="button" class="button" data-corex-assistant-media>%2$s</button><p class="description">%3$s</p>',
                esc_attr($this->settings->icon()),
                esc_html__('Choose image', 'corex'),
                esc_html__('About 32 pixels on the button; a square image with a transparent background works best. Blank: a speech-bubble icon.', 'corex'),
            ))
            . $copy,
        );
    }

    private function servicesCard(): string
    {
        $rows = '';
        foreach ($this->settings->order() as $id) {
            $rows .= $this->serviceRow($id);
        }

        return self::card(
            'services',
            __('Services', 'corex'),
            __('Asked from the top: when one is out of its free limit, the next answers. Paid services cost money for each answer; keep them last and on only if you want them. Visitors never see which service answered.', 'corex'),
            '<div class="corex-assistant-services-wrap"><table class="corex-assistant-services"><thead><tr>'
            . '<th scope="col">' . esc_html__('Order', 'corex') . '</th>'
            . '<th scope="col">' . esc_html__('Service', 'corex') . '</th>'
            . '<th scope="col">' . esc_html__('On', 'corex') . '</th>'
            . '<th scope="col">' . esc_html__('Key', 'corex') . '</th>'
            . '<th scope="col">' . esc_html__('Model', 'corex') . '</th>'
            . '<th scope="col">' . esc_html__('Status', 'corex') . '</th>'
            . '</tr></thead><tbody data-corex-assistant-order>' . $rows . '</tbody></table></div>',
        );
    }

    private function serviceRow(string $id): string
    {
        $entry = ['label' => self::serviceName($id)] + Provider::CATALOGUE[$id];
        $name = 'services[' . $id . ']';

        return sprintf(
            '<tr data-corex-assistant-service="%1$s">'
            // Posted whatever the row's other fields are (locked, off), so the row keeps its place in the order.
            . '<td class="corex-assistant-services__move"><input type="hidden" name="services[%1$s][row]" value="1" />'
            . '<button type="submit" class="button-link" name="move" value="%1$s:up" data-corex-assistant-move="up" aria-label="%2$s">&#8593;</button>'
            . '<button type="submit" class="button-link" name="move" value="%1$s:down" data-corex-assistant-move="down" aria-label="%3$s">&#8595;</button></td>'
            . '<th scope="row">%4$s <span class="corex-state-chip">%5$s</span></th>'
            . '<td>%6$s</td><td>%7$s</td>'
            . '<td><input type="text" name="%8$s[model]" value="%9$s" placeholder="%10$s" aria-label="%11$s" %12$s/></td>'
            . '<td>%13$s</td></tr>',
            esc_attr($id),
            /* translators: %s: an AI service, such as Groq. */
            esc_attr(sprintf(__('Move %s up', 'corex'), $entry['label'])),
            /* translators: %s: an AI service, such as Groq. */
            esc_attr(sprintf(__('Move %s down', 'corex'), $entry['label'])),
            esc_html($entry['label']),
            $entry['paid'] ? esc_html__('Paid', 'corex') : esc_html__('Free tier', 'corex'),
            self::toggle($name . '[on]', 'corex-assistant-on-' . $id, $this->settings->isOn($id), $entry['label']),
            $this->keyField($id, $name, $entry['label']),
            esc_attr($name),
            esc_attr($this->settings->storedModel($id)),
            esc_attr($entry['model']),
            /* translators: %s: an AI service, such as Groq. */
            esc_attr(sprintf(__('%s model', 'corex'), $entry['label'])),
            ($this->locked)(AssistantSettings::modelField($id)) ? 'disabled ' : '',
            esc_html($this->status($id)),
        );
    }

    private function keyField(string $id, string $name, string $label): string
    {
        if (($this->locked)(AssistantSettings::keyField($id))) {
            return esc_html__('Set in the site’s .env file', 'corex');
        }
        $saved = $this->settings->key($id) !== '';

        return sprintf(
            '<input type="password" name="%1$s[key]" value="" autocomplete="off" placeholder="%2$s" aria-label="%3$s" />%4$s',
            esc_attr($name),
            esc_attr($saved ? __('Saved (type to replace)', 'corex') : __('Paste the key', 'corex')),
            /* translators: %s: an AI service, such as Groq. */
            esc_attr(sprintf(__('%s API key', 'corex'), $label)),
            $saved ? sprintf(' <label><input type="checkbox" name="%1$s[remove]" value="1" /> %2$s</label>', esc_attr($name), esc_html__('Remove', 'corex')) : '',
        );
    }

    private function status(string $id): string
    {
        if ($this->settings->key($id) === '') {
            return __('No key', 'corex');
        }
        if (! $this->settings->isOn($id)) {
            return __('Off', 'corex');
        }
        $until = $this->cooldowns->until($id);
        $failure = $this->cooldowns->lastFailure($id);
        $why = $failure === null ? '' : ' ' . sprintf(
            /* translators: 1: a date and time, 2: why the service failed. */
            __('Last failed %1$s: %2$s', 'corex'),
            ($this->when)($failure['at']),
            trim($failure['kind'] . ' ' . ($failure['status'] > 0 ? $failure['status'] : '') . ' ' . $failure['detail']),
        );
        if ($until > 0) {
            return sprintf(
                /* translators: %s: a date and time */
                __('Resting until %s.', 'corex'),
                ($this->when)($until),
            ) . $why;
        }

        return __('Ready.', 'corex') . $why;
    }

    private function bookingCard(): string
    {
        $hours = $this->settings->booking();
        $zones = '';
        // A site set to an offset ("UTC+2") keeps it until the owner picks a place: it is not in PHP's list.
        $listed = BookingHours::isOffset($hours->timezone) ? [$hours->timezone, ...\DateTimeZone::listIdentifiers()] : \DateTimeZone::listIdentifiers();
        foreach ($listed as $zone) {
            $zones .= sprintf('<option value="%1$s"%2$s>%3$s</option>', esc_attr($zone), selected($zone, $hours->timezone, false), esc_html(self::zoneName($zone)));
        }
        $days = '';
        foreach (self::week() as $day) {
            $days .= sprintf(
                '<label class="corex-assistant-day"><input type="checkbox" name="booking[days][]" value="%1$d"%2$s /> %3$s</label>',
                $day,
                in_array($day, $hours->days, true) ? ' checked' : '',
                esc_html(self::dayName($day)),
            );
        }
        $slots = '';
        foreach (BookingHours::SLOT_LENGTHS as $minutes) {
            /* translators: %d: minutes. */
            $label = sprintf(_n('%d minute', '%d minutes', $minutes, 'corex'), $minutes);
            $slots .= sprintf('<option value="%1$d"%2$s>%3$s</option>', $minutes, selected($minutes, $hours->slot, false), esc_html($label));
        }
        $preview = '';
        foreach ($this->previewZones as $zone) {
            /* translators: 1: a time zone, 2: opening hours such as 10:00 to 18:00. */
            $preview .= '<li>' . esc_html(sprintf(__('In %1$s: %2$s', 'corex'), $zone, $hours->hoursIn($zone, time()))) . '</li>';
        }

        return self::card(
            'booking',
            __('Booking', 'corex'),
            __('When the team takes calls. With this on, the callback form lets a visitor pick a time, shown in their own time zone; the team gets it in both. It is a time the team confirms, not a reservation.', 'corex'),
            self::row('booking-enabled', __('Let visitors pick a time', 'corex'), self::toggle('booking[enabled]', 'corex-assistant-booking-enabled', $hours->enabled))
            . self::row('booking-zone', __('Time zone', 'corex'), '<select id="corex-assistant-booking-zone" name="booking[timezone]" data-corex-select>' . $zones . '</select>')
            . '<fieldset class="corex-assistant-row"><legend class="corex-assistant-row__label">' . esc_html__('Working days', 'corex') . '</legend>'
            . '<div class="corex-assistant-row__control">' . $days . '</div></fieldset>'
            . self::row('booking-start', __('Opens', 'corex'), sprintf('<input type="time" id="corex-assistant-booking-start" name="booking[start]" value="%s" />', esc_attr($hours->start)))
            . self::row('booking-end', __('Closes', 'corex'), sprintf('<input type="time" id="corex-assistant-booking-end" name="booking[end]" value="%s" /><p class="description">%s</p>', esc_attr($hours->end), esc_html__('A closing time at or before the opening time closes the next day.', 'corex')))
            . self::row('booking-slot', __('Slot length', 'corex'), '<select id="corex-assistant-booking-slot" name="booking[slot]" data-corex-select>' . $slots . '</select>')
            . self::row('booking-notice', __('Notice (minutes)', 'corex'), sprintf('<input type="number" id="corex-assistant-booking-notice" name="booking[notice]" min="0" max="%d" value="%d" />', BookingHours::MAX_NOTICE, $hours->notice))
            . self::row('booking-horizon', __('Days shown', 'corex'), sprintf('<input type="number" id="corex-assistant-booking-horizon" name="booking[horizon]" min="1" max="%d" value="%d" />', BookingHours::MAX_HORIZON, $hours->horizon))
            . ($preview !== '' ? '<p>' . esc_html__('Today, as visitors elsewhere see these hours:', 'corex') . '</p><ul class="corex-assistant-preview">' . $preview . '</ul>' : ''),
        );
    }

    private function limitsCard(): string
    {
        $limits = $this->settings->limits();
        $fields = [
            'hour' => __('Questions a visitor may ask in an hour', 'corex'),
            'day' => __('Questions a visitor may ask in a day', 'corex'),
            'site' => __('Questions the whole site answers in a day', 'corex'),
            'paid' => __('Questions sent to paid services in a day', 'corex'),
        ];
        $rows = '';
        foreach ($fields as $name => $label) {
            $rows .= self::row('limit-' . $name, $label, sprintf('<input type="number" min="0" id="corex-assistant-limit-%1$s" name="limits[%1$s]" value="%2$d" />', esc_attr($name), $limits[$name]));
        }

        return self::card(
            'limits',
            __('Limits', 'corex'),
            sprintf(
                /* translators: 1: questions today, 2: questions sent to paid services today. */
                __('Past a limit the chat offers its callback form instead of answering, until the next hour or day (UTC). Today: %1$d questions, %2$d sent to paid services.', 'corex'),
                $this->limits->siteToday(),
                $this->limits->paidToday(),
            ),
            $rows,
        );
    }

    private function checkCard(): string
    {
        return '<section class="corex-surface corex-settings-section corex-assistant-card" data-corex-assistant-panel>'
            . '<h2>' . esc_html__('Check services', 'corex') . '</h2>'
            . '<p>' . esc_html__('Asks each service with a key, on or off, a visitor’s question with the full instructions, and shows which answered and how fast. A paid service is charged for its question and it counts against today’s paid limit. Save new keys first.', 'corex') . '</p>'
            . '<p><button type="button" class="button" data-corex-assistant-check>' . esc_html__('Check services', 'corex') . '</button></p>'
            . '<div role="status" aria-live="polite" data-corex-assistant-results></div></section>';
    }

    /** The week in the order the site starts it. @return list<int> Sunday 0 to Saturday 6 */
    private static function week(): array
    {
        $first = (int) get_option('start_of_week', 0);

        return array_map(static fn (int $offset): int => ($first + $offset) % 7, range(0, 6));
    }

    private static function card(string $key, string $title, string $intro, string $body): string
    {
        return sprintf(
            '<section class="corex-surface corex-settings-section corex-assistant-card" id="corex-assistant-%1$s" aria-labelledby="corex-assistant-%1$s-title">'
            . '<h2 id="corex-assistant-%1$s-title">%2$s</h2><p>%3$s</p>%4$s</section>',
            esc_attr($key),
            esc_html($title),
            esc_html($intro),
            $body,
        );
    }

    /** A labelled row; `$control` is built escaped by its caller. */
    private static function row(string $id, string $label, string $control): string
    {
        return sprintf(
            '<div class="corex-assistant-row"><label class="corex-assistant-row__label" for="corex-assistant-%1$s">%2$s</label><div class="corex-assistant-row__control">%3$s</div></div>',
            esc_attr($id),
            esc_html($label),
            $control,
        );
    }

    private static function toggle(string $name, string $id, bool $on, string $label = ''): string
    {
        return sprintf(
            '<label class="corex-switch"><span class="corex-switch__control">'
            . '<input id="%1$s" name="%2$s" type="checkbox" value="1"%3$s role="switch" class="corex-switch__input"%4$s />'
            . '<span class="corex-toggle__knob" aria-hidden="true"></span></span>'
            . '<span class="corex-switch__state" aria-hidden="true"><span class="corex-switch__on">%5$s</span>'
            . '<span class="corex-switch__off">%6$s</span></span></label>',
            esc_attr($id),
            esc_attr($name),
            $on ? ' checked' : '',
            /* translators: %s: an AI service, such as Groq. */
            $label !== '' ? ' aria-label="' . esc_attr(sprintf(__('%s on', 'corex'), $label)) . '"' : '',
            esc_html__('On', 'corex'),
            esc_html__('Off', 'corex'),
        );
    }

    /** A service's name as the owner reads it: a product's name, which a translation may write in its script. */
    private static function serviceName(string $id): string
    {
        $names = [
            'gemini' => __('Google Gemini', 'corex'),
            'groq' => __('Groq', 'corex'),
            'openrouter' => __('OpenRouter', 'corex'),
            'mistral' => __('Mistral', 'corex'),
            'openai' => __('OpenAI', 'corex'),
            'anthropic' => __('Anthropic Claude', 'corex'),
        ];

        return $names[$id] ?? (string) (Provider::CATALOGUE[$id]['label'] ?? $id);
    }

    /** A time zone as the list shows it: `Africa/Cairo`, `America/Argentina/Buenos Aires`, `UTC+02:00`; UTC in CoreX's words. */
    private static function zoneName(string $zone): string
    {
        return match (true) {
            $zone === 'UTC' => __('UTC', 'corex'),
            BookingHours::isOffset($zone) => BookingHours::city($zone),
            default => str_replace('_', ' ', $zone),
        };
    }

    /** A day of the week, 0 for Sunday, in CoreX's own words like the rest of the screen. */
    private static function dayName(int $day): string
    {
        $names = [
            __('Sunday', 'corex'),
            __('Monday', 'corex'),
            __('Tuesday', 'corex'),
            __('Wednesday', 'corex'),
            __('Thursday', 'corex'),
            __('Friday', 'corex'),
            __('Saturday', 'corex'),
        ];

        return $names[$day] ?? '';
    }

    /** A translated label placed inside a format string: a `%` in a translation stays a `%`. */
    private static function literal(string $text): string
    {
        return str_replace('%', '%%', $text);
    }
}
