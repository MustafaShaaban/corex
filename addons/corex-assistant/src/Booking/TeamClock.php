<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Booking;

defined('ABSPATH') || exit;

use Corex\Mail\EmailLanguage;

/**
 * A moment on a zone's clock as the team reads it (spec 129, FR-022): "Thu 12 Nov, 7:00 pm", in the site's
 * language and with its time format. Drawn in the site's language as every CoreX email is (DECISIONS #356):
 * the team reads the inbox and its notification in that language, whatever the visitor's page was in.
 */
final class TeamClock
{
    /** The time format when the site has none saved. */
    private const TIME = 'g:i a';

    public function __construct(private readonly EmailLanguage $language)
    {
    }

    public function __invoke(int $at, string $zone): string
    {
        return (string) $this->language->during(static function () use ($at, $zone): string {
            $time = (string) get_option('time_format', self::TIME);
            $clock = new \DateTimeZone($zone);

            return sprintf(
                /* translators: a requested call time for the team. 1: its day, such as "Thu 12 Nov"; 2: its time, such as "7:00 pm". */
                __('%1$s, %2$s', 'corex'),
                /* translators: the day of a requested call time, as PHP's date() writes it: "Thu 12 Nov". */
                (string) wp_date(__('D j M', 'corex'), $at, $clock),
                (string) wp_date($time !== '' ? $time : self::TIME, $at, $clock),
            );
        });
    }
}
