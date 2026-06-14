<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Insights;

defined('ABSPATH') || exit;

use Corex\Http\ResponseEnvelope;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST for the Corex → Insights screen: `GET corex/v1/insights` returns the cached results
 * (`manage_options`); `POST corex/v1/insights/run` runs one provider and stores it
 * (`manage_options` **and** a valid REST nonce — Principle VII). Results never contain a secret
 * (the `InsightResult` value carries only scores/metrics/recommendations). The pure seams
 * (`canManage`, `verifiedNonce`, `result`) are unit-tested; the WP_REST_* callbacks are thin.
 */
final class InsightsController
{
    public function __construct(
        private readonly InsightRegistry $registry,
        private readonly InsightStore $store,
        private readonly string $option = 'corex_insights',
    ) {
    }

    public function register(): void
    {
        register_rest_route('corex/v1', '/insights', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => [$this, 'canManage'],
        ]);

        register_rest_route('corex/v1', '/insights/run', [
            'methods'             => 'POST',
            'callback'            => [$this, 'run'],
            'permission_callback' => [$this, 'canRun'],
        ]);
    }

    public function canManage(): bool
    {
        return current_user_can('manage_options');
    }

    public function canRun(WP_REST_Request $request): bool
    {
        return $this->canManage() && $this->verifiedNonce((string) $request->get_header('X-WP-Nonce'));
    }

    /**
     * A state-changing run must carry a valid REST nonce (Principle VII).
     */
    public function verifiedNonce(string $nonce): bool
    {
        return wp_verify_nonce($nonce, 'wp_rest') !== false;
    }

    /**
     * The cached latest result for every provider.
     *
     * @return list<array<string,mixed>>
     */
    public function stored(): array
    {
        $state = (array) get_option($this->option, []);

        return array_values($this->store->all($state));
    }

    /**
     * Run one provider against the site URL, cache it, and return its (secret-free) payload — or
     * null if the provider id is unknown (the route maps that to a 404).
     *
     * @return array<string,mixed>|null
     */
    public function result(string $providerId): ?array
    {
        $provider = $this->registry->find($providerId);

        if ($provider === null) {
            return null;
        }

        $result = $provider->run((string) home_url('/'));
        $state  = (array) get_option($this->option, []);

        update_option($this->option, $this->store->put($state, $result));

        return $result->toArray();
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(ResponseEnvelope::success(['results' => $this->stored()])->toArray());
    }

    public function run(WP_REST_Request $request): WP_REST_Response
    {
        $payload = $this->result((string) $request->get_param('provider'));

        if ($payload === null) {
            return new WP_REST_Response(
                ResponseEnvelope::error('unknown_provider', __('Unknown insight provider.', 'corex'))->toArray(),
                404,
            );
        }

        return new WP_REST_Response(ResponseEnvelope::success(['result' => $payload])->toArray());
    }
}
