<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security;

defined('ABSPATH') || exit;

use Corex\Config\Security\LoginProtection\LoginProtectionSettings;
use Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore;
use Corex\Config\Security\LoginProtection\LoginSlug;
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
                    'args'                => $this->saveArgs(),
                ],
            ]);
        });
    }

    /**
     * Declared validation for the write route, which previously had none at all.
     *
     * The numeric bounds are enforced here so a nonsense value is refused at the edge instead of
     * being clamped somewhere downstream into something the owner never asked for. The address has
     * rules of its own that depend on normalising it first, so it is checked in save().
     *
     * @return array<string,array<string,mixed>>
     */
    private function saveArgs(): array
    {
        return [
            'enabled' => [
                'type'     => 'boolean',
                'required' => false,
            ],
            'block_default_endpoints' => [
                'type'     => 'boolean',
                'required' => false,
            ],
            'custom_slug' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'max_attempts' => [
                'type'    => 'integer',
                'minimum' => 1,
                'maximum' => 50,
            ],
            'window_seconds' => [
                'type'    => 'integer',
                'minimum' => 60,
                'maximum' => 86400,
            ],
            'lockout_seconds' => [
                'type'    => 'integer',
                'minimum' => 60,
                'maximum' => 604800,
            ],
            'retention_days' => [
                'type'    => 'integer',
                'minimum' => 1,
                'maximum' => 365,
            ],
            'trusted_proxies' => [
                'type'  => 'array',
                'items' => ['type' => 'string'],
            ],
            'trusted_proxy_mode' => [
                'type'     => 'boolean',
                'required' => false,
            ],
            'successful_login_logging' => [
                'type'     => 'boolean',
                'required' => false,
            ],
        ];
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

        $slug = $this->slugFrom($body);
        $rejection = LoginSlug::rejectionReason($slug);

        // Refuse rather than let the store substitute a working address behind the owner's back:
        // they would leave believing the login is where they typed it. Nothing is written, so the
        // configuration that is currently working stays working.
        if ($rejection !== null) {
            return new WP_REST_Response([
                'code'    => 'corex_invalid_login_slug',
                'message' => $this->slugRejectionMessage($rejection),
            ], 400);
        }

        $ranges = array_values(array_filter(array_map(
            'sanitize_text_field',
            (array) ($body['trusted_proxies'] ?? []),
        )));

        $saved = $this->store->save([
            'enabled'                  => filter_var($body['enabled'] ?? false, FILTER_VALIDATE_BOOL),
            'custom_slug'              => $slug,
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

    /**
     * The address the owner is asking for, normalised the way it will be stored.
     *
     * Validation has to run on the normalised value, not the raw one: "Team Entry" is fine and
     * becomes "team-entry", while "!!!" normalises to nothing and is not.
     *
     * @param array<string,mixed> $body
     */
    private function slugFrom(array $body): string
    {
        // Omitted and empty are different intentions. A client that never sends the field is asking
        // for the default. An owner who cleared the box typed nothing on purpose and should be told
        // so, not quietly given an address they did not choose.
        if (! array_key_exists('custom_slug', $body)) {
            return LoginSlug::DEFAULT;
        }

        return sanitize_title(trim((string) $body['custom_slug']));
    }

    /**
     * Turn a rejection code into something an owner can act on.
     *
     * LoginSlug returns codes rather than sentences because it runs on plugins_loaded, long before
     * translations load. This is the layer that talks to a person, so it is the layer that
     * translates.
     */
    private function slugRejectionMessage(string $reason): string
    {
        return match ($reason) {
            LoginSlug::REASON_RESERVED => __(
                'That login address is reserved by WordPress. Choose a different one.',
                'corex',
            ),
            default => __(
                'Use 3 to 81 characters for the login address: lowercase letters, numbers, and hyphens, starting with a letter or number.',
                'corex',
            ),
        };
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
