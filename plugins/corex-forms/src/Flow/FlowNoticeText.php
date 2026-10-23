<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * Builds the notice of a flow that is not taking answers, in the site's language (spec 122, FR-032).
 *
 * CoreX's own wording is a translatable string. The owner's message and alternative label are
 * handed to the site's string-translation plugin through the hooks WPML defines and Polylang
 * answers too; with neither installed they are shown as the owner wrote them (DECISIONS #478).
 */
final class FlowNoticeText
{
    private const CONTEXT = 'corex';

    /** Offers the owner's words to the string-translation plugin, under names made from the flow. */
    public function register(Flow $flow): void
    {
        $availability = $flow->availability;
        if ($availability->closedMessage !== '') {
            do_action('wpml_register_single_string', self::CONTEXT, $this->name($flow, 'closed-message'), $availability->closedMessage);
        }
        if ($availability->alternative->label !== '') {
            do_action('wpml_register_single_string', self::CONTEXT, $this->name($flow, 'alternative-label'), $availability->alternative->label);
        }
    }

    /** The notice for a status that tells visitors something, or null for one that does not. */
    public function notice(Flow $flow, FlowStatus $status): ?FlowNotice
    {
        if (! $status->tellsVisitors()) {
            return null;
        }
        $availability = $flow->availability;
        $alternative = $availability->alternative;
        $translated = new FlowAlternative(
            $alternative->type,
            $this->translate($flow, 'alternative-label', $alternative->label),
            $alternative->url,
            $alternative->email,
        );
        if ($status->state === FlowStatus::SCHEDULED && $status->at !== null) {
            return new FlowNotice(
                __('Not open yet', 'corex'),
                /* translators: %s: the date and time a form starts taking answers, in the site's format. */
                sprintf(__('This form opens on %s.', 'corex'), $this->when($status->at)),
                $translated,
            );
        }

        return new FlowNotice(
            __('Closed', 'corex'),
            $availability->closedMessage !== ''
                ? $this->translate($flow, 'closed-message', $availability->closedMessage)
                : __('This form is closed.', 'corex'),
            $translated,
        );
    }

    /**
     * Why a flow is not open, as the flow list says it beside its state (spec 122, FR-031):
     * "Closed on 3 March 2026 at 09:00", "Reached 200 responses", "Opens on …". Empty when open.
     */
    public function reason(FlowStatus $status): string
    {
        return match (true) {
            $status->state === FlowStatus::SCHEDULED && $status->at !== null
                /* translators: %s: the date and time the form starts taking answers. */
                => sprintf(__('Opens on %s', 'corex'), $this->when($status->at)),
            $status->reason === FlowStatus::BY_DATE && $status->at !== null
                /* translators: %s: the date and time the form stopped taking answers. */
                => sprintf(__('Closed on %s', 'corex'), $this->when($status->at)),
            $status->reason === FlowStatus::BY_CAP => sprintf(
                /* translators: %d: how many responses the form took before it closed. */
                _n('Reached %d response', 'Reached %d responses', (int) $status->responses, 'corex'),
                (int) $status->responses,
            ),
            default => '',
        };
    }

    /** A date and time in the site's own formats, time zone and language. */
    public function when(DateTimeImmutable $instant): string
    {
        return sprintf(
            /* translators: 1: a date, 2: a time of day, both in the site's formats. */
            __('%1$s at %2$s', 'corex'),
            wp_date((string) get_option('date_format'), $instant->getTimestamp()),
            wp_date((string) get_option('time_format'), $instant->getTimestamp()),
        );
    }

    private function translate(Flow $flow, string $part, string $text): string
    {
        if ($text === '') {
            return '';
        }
        $translated = apply_filters('wpml_translate_single_string', $text, self::CONTEXT, $this->name($flow, $part));

        return is_string($translated) && $translated !== '' ? $translated : $text;
    }

    private function name(Flow $flow, string $part): string
    {
        return sprintf('flow-%s-%s', $flow->slug, $part);
    }
}
