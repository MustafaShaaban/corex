<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Overview;

defined('ABSPATH') || exit;

use Corex\Access\CorexAbility;
use Corex\Config\Readiness\ChecklistMarkup;

/**
 * An Overview card whose data could not be read (spec 118, US-OVR-06). It says "Could not load"
 * under the card's own heading, offers "Try again" (the page again), and never a 0, an empty state
 * or another cause. The failure goes to the PHP error log with a short reference, and an
 * administrator reads the same reference on the card, so the two can be matched.
 */
class OverviewCardFailure
{
    public function report(OverviewCard $card, \Throwable $error): string
    {
        $reference = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- the reference has to reach the PHP error log.
        error_log(sprintf(
            'CoreX Overview: the %s card could not load (reference %s): %s: %s',
            $card->key(),
            $reference,
            $error::class,
            $error->getMessage(),
        ));

        return $this->card($card, $reference);
    }

    private function card(OverviewCard $card, string $reference): string
    {
        // "Access CoreX admin" comes only with site administration (spec 117): someone reading the
        // Overview read-only does not get the reference.
        $forAdministrator = current_user_can(CorexAbility::MANAGE_ADMIN)
            ? '<p class="corex-overview__muted corex-overview__reference">' . esc_html(sprintf(
                /* translators: %s: a short reference, also written to the PHP error log, e.g. 3FA9C21B. */
                __('Reference: %s', 'corex'),
                $reference,
            )) . '</p>'
            : '';

        return '<section class="corex-surface corex-overview__card corex-overview__card--compact corex-overview__card--failed" data-card="'
            . esc_attr($card->key()) . '" role="status">'
            . '<header class="corex-overview__card-head"><h2>' . esc_html($card->title()) . '</h2>'
            . ChecklistMarkup::chip('unavailable', __('Could not load', 'corex')) . '</header>'
            . '<p class="corex-overview__muted">' . esc_html__('This card\'s information could not be read.', 'corex') . '</p>'
            . $forAdministrator
            . '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=corex-settings')) . '">'
            . esc_html__('Try again', 'corex') . '</a></p></section>';
    }
}
