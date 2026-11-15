<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

defined('ABSPATH') || exit;

use Corex\Access\AbilityCheck;
use Corex\Access\CorexAbility;
use Corex\Http\ResponseEnvelope;
use WP_REST_Request;
use WP_REST_Response;

/**
 * "Test connection" for the Insights providers' keys (spec 119, US-SET-03). "Manage settings" and a
 * REST nonce gate it, as the captcha test route is gated, and a refusal is recorded under Settings
 * (spec 117, FR-002, FR-005); the answer carries the line the page shows and whether the key worked,
 * never the key.
 */
final class ProviderTestController
{
    public function __construct(
        private readonly ProviderTestService $tests,
        private readonly AbilityCheck $abilities,
    ) {
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'route']);
    }

    public function route(): void
    {
        register_rest_route('corex/v1', '/settings/providers/(?P<provider>pagespeed|cloudflare)/test', [
            'methods'             => 'POST',
            'callback'            => [$this, 'test'],
            'permission_callback' => [$this, 'canRun'],
        ]);
    }

    public function canRun(WP_REST_Request $request): bool
    {
        return $this->abilities->allows(CorexAbility::MANAGE_SETTINGS, SettingsPages::SCREEN)
            && wp_verify_nonce((string) $request->get_header('X-WP-Nonce'), 'wp_rest') !== false;
    }

    public function test(WP_REST_Request $request): WP_REST_Response
    {
        $provider = (string) $request->get_param('provider');

        if (! $this->tests->testable($provider)) {
            $envelope = ResponseEnvelope::error('provider_not_ready', __('Save a key first, then test it.', 'corex'));

            return new WP_REST_Response($envelope->toArray(), 400);
        }

        $record   = $this->tests->test($provider);
        $envelope = ResponseEnvelope::success(['worked' => $record->worked, 'status' => $record->line()], $record->line());

        return new WP_REST_Response($envelope->toArray(), 200);
    }
}
