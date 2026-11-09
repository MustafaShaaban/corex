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
 * `GET corex/v1/assistant/slots` (spec 129, FR-021): the times the hand-off form offers, asked when
 * the form opens rather than printed in the page, so a cached page never offers a time that passed.
 *
 * It reads settings and the clock and changes nothing, so it needs no token: what it says is what
 * the business's hours already say to anyone who asks. Empty while booking is off or the assistant
 * is not live.
 */
final class SlotsController
{
    public const ROUTE = '/assistant/slots';

    /**
     * @param \Closure(): string $siteZone the site's time zone, from Settings, General
     * @param \Closure(): int    $now
     */
    public function __construct(
        private readonly AssistantSettings $settings,
        private readonly \Closure $siteZone,
        private readonly \Closure $now,
    ) {
    }

    public function register(): void
    {
        register_rest_route('corex/v1', self::ROUTE, [
            'methods' => 'GET',
            'callback' => [$this, 'slots'],
            // Public and read-only: the business's opening hours as times (Principle VII: nothing to guard).
            'permission_callback' => '__return_true',
        ]);
    }

    public function slots(): WP_REST_Response
    {
        $response = new WP_REST_Response(['slots' => $this->offered()]);
        // The times move on with the clock.
        $response->header('Cache-Control', 'no-store');

        return $response;
    }

    /** @return list<int> */
    public function offered(): array
    {
        if (! $this->settings->live() || ! $this->settings->bookingEnabled()) {
            return [];
        }

        return $this->settings->booking(($this->siteZone)())->slots(($this->now)());
    }
}
