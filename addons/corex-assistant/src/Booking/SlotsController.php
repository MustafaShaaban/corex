<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Booking;

defined('ABSPATH') || exit;

use WP_REST_Response;

/**
 * `GET corex/v1/assistant/slots` (spec 129, "pick a time"): the times a visitor may ask for, asked when
 * the hand-off form opens. Public and read-only: it says only when the team takes calls, which the form
 * shows anyway. A time another visitor has asked for is left out ({@see OpenSlots}), and nothing says who
 * asked. Never cached, because the slots move on with the clock and as visitors ask.
 */
final class SlotsController
{
    public function __construct(private readonly OpenSlots $slots)
    {
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
        $response = new WP_REST_Response([
            'zone' => $this->slots->zone(),
            'slots' => $this->slots->open(),
        ]);
        $response->header('Cache-Control', 'no-store');

        return $response;
    }
}
