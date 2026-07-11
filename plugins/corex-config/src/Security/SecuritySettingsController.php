<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security;

defined('ABSPATH') || exit;

use Corex\Config\Security\LoginProtection\LoginProtectionSettings;
use Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Persists the Security Center's login-protection settings (custom login URL, failed-login limiting,
 * threshold/window/lockout, trusted proxies, retention). Cap + nonce gated. Without this route the
 * Security Center controls were display-only and never took effect (FR-076).
 */
final class SecuritySettingsController
{
    private const NAMESPACE = 'corex/v1';

    public function __construct(private readonly LoginProtectionSettingsStore $store)
    {
    }

    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            register_rest_route(self::NAMESPACE, '/security/login-protection', [
                [
                    'methods'             => 'GET',
                    'callback'            => [$this, 'show'],
                    'permission_callback' => [$this, 'allowed'],
                ],
                [
                    'methods'             => 'POST',
                    'callback'            => [$this, 'save'],
                    'permission_callback' => [$this, 'allowed'],
                ],
            ]);
        });
    }

    public function allowed(WP_REST_Request $request): bool
    {
        return current_user_can('manage_options') && $this->hasNonce($request);
    }

    public function show(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(['login_protection' => $this->payload($this->store->current())], 200);
    }

    public function save(WP_REST_Request $request): WP_REST_Response
    {
        $body = $request->get_json_params();
        $body = is_array($body) ? $body : $request->get_body_params();

        $ranges = array_values(array_filter(array_map(
            'sanitize_text_field',
            (array) ($body['trusted_proxies'] ?? []),
        )));

        $saved = $this->store->save([
            'enabled'                  => filter_var($body['enabled'] ?? false, FILTER_VALIDATE_BOOL),
            'custom_slug'              => (string) ($body['custom_slug'] ?? 'corex-login'),
            'block_default_endpoints'  => filter_var($body['block_default_endpoints'] ?? true, FILTER_VALIDATE_BOOL),
            'threshold'                => (int) ($body['max_attempts'] ?? 5),
            'window_seconds'           => (int) ($body['window_seconds'] ?? 300),
            'lockout_seconds'          => (int) ($body['lockout_seconds'] ?? 900),
            'trusted_proxy_mode'       => array_key_exists('trusted_proxy_mode', $body)
                ? filter_var($body['trusted_proxy_mode'], FILTER_VALIDATE_BOOL)
                : $ranges !== [],
            'trusted_proxy_ranges'     => $ranges,
            'retain_days'              => (int) ($body['retention_days'] ?? 30),
            'successful_login_logging' => filter_var($body['successful_login_logging'] ?? true, FILTER_VALIDATE_BOOL),
        ]);

        return new WP_REST_Response([
            'login_protection' => $this->payload($saved),
            'message'          => __('Login protection settings saved.', 'corex'),
        ], 200);
    }

    /** @return array<string,mixed> */
    private function payload(LoginProtectionSettings $settings): array
    {
        return [
            'enabled'                  => $settings->enabled,
            'block_default_endpoints'  => $settings->blockDefaultEndpoints,
            'custom_slug'              => $settings->customSlug,
            'max_attempts'             => $settings->threshold,
            'window_seconds'           => $settings->windowSeconds,
            'lockout_seconds'          => $settings->lockoutSeconds,
            'trusted_proxy_mode'       => $settings->trustedProxyMode,
            'trusted_proxies'          => $settings->trustedProxyRanges,
            'retention_days'           => $settings->retainDays,
            'successful_login_logging' => $settings->successfulLoginLogging,
        ];
    }

    private function hasNonce(WP_REST_Request $request): bool
    {
        $nonce = (string) $request->get_header('X-WP-Nonce');
        if ($nonce === '') {
            $nonce = (string) $request->get_param('_wpnonce');
        }

        return wp_verify_nonce($nonce, 'wp_rest') !== false;
    }
}
