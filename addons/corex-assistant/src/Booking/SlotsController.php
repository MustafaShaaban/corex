<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Booking;

defined('ABSPATH') || exit;

use Corex\Assistant\AssistantSettings;
use WP_REST_Response;

/**
 * `GET corex/v1/assistant/slots` (spec 129, "pick a time"): the times a visitor may ask for, asked when
 * the hand-off form opens. Public and read-only: it says only when the team takes calls, which the form
 * shows anyway. Never cached, because the slots move on with the clock.
 */
final class SlotsController
{
    /**
     * @param \Closure(): int $now
     */
    public function __construct(
        private readonly AssistantSettings $settings,
        private readonly \Closure $now,
    ) {
    }

    public function register(): void
    {
        register_rest_route('corex/v1', '/assistant/slots', [
            'methods' => 'GET',
            'callback' => [$this, 'slots'],
            // Public: the hours a business takes calls are what the form tells every visitor.
            'permission_callback' => '__return_true',
        ]);
    }

    public function slots(): WP_REST_Response
    {
        $hours = $this->settings->booking();
        $response = new WP_REST_Response([
            'zone' => $hours->timezone,
            'slots' => $hours->slots(($this->now)()),
        ]);
        $response->header('Cache-Control', 'no-store');

        return $response;
    }
}
