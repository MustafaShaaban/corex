<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Overview\Cards;

defined('ABSPATH') || exit;

use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;
use Corex\Config\Activity\ActivitySentences;
use Corex\Config\Overview\OverviewCard;
use Corex\Support\DateTime\AdminDateTime;

/**
 * Recent activity, as sentences (spec 118, US-OVR-07): who did what, in words, with a link to what
 * it was done to while that can still be opened. The card has no link to a list: the only list
 * CoreX has is of refused visits, which is not this, and a link to it called "Audit log" said it
 * was (TC-OVR-07-05). The time is in the admin's date format.
 */
final class ActivityCard implements OverviewCard
{
    public function __construct(
        private readonly ActivityService $activity,
        private readonly ActivitySentences $sentences,
        private readonly AdminDateTime $dateTime,
    ) {
    }

    public function key(): string
    {
        return 'activity';
    }

    public function title(): string
    {
        return __('Recent activity', 'corex');
    }

    public function render(array $settingValues): string
    {
        $events = $this->activity->query([], 1, 5);
        $head   = '<section class="corex-surface corex-overview__card corex-overview__activity" data-card="activity">'
            . '<header class="corex-overview__card-head"><h2>' . esc_html($this->title()) . '</h2></header>';

        if ($events === []) {
            return $head . '<div class="corex-overview__empty"><p>'
                . esc_html__('No recent framework events yet.', 'corex') . '</p><p class="corex-overview__muted">'
                . esc_html__('Activity appears here as forms, data, access, and security events occur.', 'corex')
                . '</p></div></section>';
        }

        $rows = '';
        foreach ($events as $event) {
            $said = $this->sentences->of($event);
            $text = $said['href'] !== ''
                ? '<a href="' . esc_url($said['href']) . '">' . esc_html($said['text']) . '</a>'
                : esc_html($said['text']);
            $when = $this->dateTime->format(
                $event->occurredAt->getTimestamp(),
                AdminDateTime::FULL,
                __('Not recorded', 'corex'),
            );

            $rows .= '<li class="corex-overview__event is-' . esc_attr($this->tone($event->outcome)) . '">'
                . '<span class="corex-overview__event-summary">' . $text . '</span>'
                . $when->toHtml('corex-overview__event-time') . '</li>';
        }

        return $head . '<ul class="corex-overview__events">' . $rows . '</ul></section>';
    }

    private function tone(string $outcome): string
    {
        return in_array($outcome, [ActivityEvent::OUTCOME_FAILURE, ActivityEvent::OUTCOME_DENIED], true) ? 'warning' : 'neutral';
    }
}
