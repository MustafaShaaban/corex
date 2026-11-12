<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security;

defined('ABSPATH') || exit;

use Corex\Access\AbilityCheck;
use Corex\Access\CorexAbility;
use Corex\Config\Security\LoginProtection\LoginProtectionSettings;
use Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore;
use Corex\Config\Security\LoginProtection\LoginSettingsInput;
use Corex\Config\Security\LoginProtection\LoginSettingsSaver;
use Corex\Config\Security\LoginProtection\LoginUrl;
use Corex\Http\ResponseEnvelope;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Reads and saves the Security Center's login-protection settings: the three switches, the login
 * address, the limits, trusted proxies, retention and recording successful sign-ins (spec 124,
 * FR-013, FR-015). "Manage operations" and the REST nonce are asked of both methods.
 *
 * A save changes only the fields it sends and refuses a value out of its range with the range,
 * keyed by field in spec 043's validation answer, with nothing written ({@see LoginSettingsSaver}).
 */
final class SecuritySettingsController
{
    private const SCREEN = 'corex-operations-security';

    private const NAMESPACE = 'corex/v1';

    public function __construct(
        private readonly LoginProtectionSettingsStore $store,
        private readonly LoginSettingsSaver $saver,
        private readonly LoginSettingsMessages $messages,
        private readonly AbilityCheck $abilities = new AbilityCheck(),
    ) {
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
     * The fields a save may send, every one optional: a field left out is not changed (FR-015).
     *
     * They carry no `type` or bounds on purpose. WordPress would refuse a bad value before the
     * callback in its own shape, one field at a time and in English; {@see LoginSettingsInput}
     * checks them all, against {@see LoginSettingsInput::RANGES}, and the refusal names each field
     * with its range in one translated answer.
     *
     * @return array<string,array<string,mixed>>
     */
    private function saveArgs(): array
    {
        $fields = [
            'hide_wp_login'            => 'Hide wp-login.php (on or off).',
            'hide_admin'               => 'Hide the admin from visitors who are not signed in (on or off).',
            'limit_attempts'           => 'Limit failed sign-ins (on or off).',
            'enabled'                  => 'The protection switch of the previous screen, read in its old meaning; not with the three switches.',
            'block_default_endpoints'  => 'The hide switch of the previous screen, read in its old meaning; not with the three switches.',
            'custom_slug'              => 'The login address.',
            'max_attempts'             => 'Failed attempts before a lockout.',
            'window_minutes'           => 'The counting window, in whole minutes.',
            'lockout_minutes'          => 'The lockout length, in whole minutes.',
            'window_seconds'           => 'The counting window in seconds, as the previous screen sends it; not with window_minutes.',
            'lockout_seconds'          => 'The lockout length in seconds, as the previous screen sends it; not with lockout_minutes.',
            'trusted_proxies'          => 'Trusted proxies: text with one IPv4 or IPv6 address or CIDR range per line, or a list of them.',
            'trusted_proxy_mode'       => 'Trust the proxies (on or off); follows the list when not sent with it.',
            'retention_days'           => 'Days sign-in records are kept.',
            'successful_login_logging' => 'Record successful sign-ins (on or off).',
        ];

        foreach (LoginSettingsInput::RANGES as $field => [$min, $max]) {
            $fields[$field] .= sprintf(' From %d to %d.', $min, $max);
        }

        return array_map(
            static fn (string $description): array => ['required' => false, 'description' => $description],
            $fields,
        );
    }

    /**
     * Reading or changing the settings: the screen's ability (spec 117, FR-002). The view ability does
     * not read them: Operations & Security has no read-only view (DECISIONS #422, amended 2026-11-11).
     */
    public function allowed(WP_REST_Request $request): bool
    {
        return $this->abilities->allows(CorexAbility::MANAGE_OPERATIONS, self::SCREEN) && $this->hasNonce($request);
    }

    public function show(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(['login_protection' => $this->payload($this->store->current())], 200);
    }

    public function save(WP_REST_Request $request): WP_REST_Response
    {
        $body = $request->get_json_params();
        $body = is_array($body) ? $body : $request->get_body_params();

        $outcome = $this->saver->save(is_array($body) ? $body : []);

        if ($outcome->isRefused() || $outcome->settings === null) {
            $refusal = ResponseEnvelope::validation(
                $this->messages->errors($outcome->problems),
                __('Nothing was saved. Correct the settings named below and save again.', 'corex'),
            )->toArray();

            return new WP_REST_Response(['details' => $this->messages->details($outcome->problems)] + $refusal, 422);
        }

        return new WP_REST_Response([
            'login_protection' => $this->payload($outcome->settings),
            'message'          => __('Login protection settings saved.', 'corex'),
        ], 200);
    }

    /**
     * The settings as stored, in the route's shape: the switches, the previous release's two keys
     * (which the current screen still reads until slice 10), the lengths in whole minutes and in
     * seconds, and the trusted proxies with the switch that trusts them, which is what a warning
     * beside that field is about.
     *
     * A length that is not a whole minute (set from the command line) has no minutes: `null`, with
     * its seconds beside it, so nothing rounds it to a value the owner did not choose.
     *
     * @return array<string,mixed>
     */
    private function payload(LoginProtectionSettings $settings): array
    {
        return [
            'hide_wp_login'            => $settings->hideWpLogin,
            'hide_admin'               => $settings->hideAdmin,
            'limit_attempts'           => $settings->limitAttempts,
            'enabled'                  => $settings->enabled,
            'block_default_endpoints'  => $settings->blockDefaultEndpoints,
            'custom_slug'              => $settings->customSlug,
            // Without this the screen keeps showing the address from before the save, so an owner
            // who just moved their login is told the old one.
            'login_url'                => esc_url_raw(LoginUrl::forSettings($settings)),
            'max_attempts'             => $settings->threshold,
            'window_minutes'           => $this->wholeMinutes($settings->windowSeconds),
            'window_seconds'           => $settings->windowSeconds,
            'lockout_minutes'          => $this->wholeMinutes($settings->lockoutSeconds),
            'lockout_seconds'          => $settings->lockoutSeconds,
            'trusted_proxy_mode'       => $settings->trustedProxyMode,
            'trusted_proxies'          => $settings->trustedProxyRanges,
            'retention_days'           => $settings->retainDays,
            'successful_login_logging' => $settings->successfulLoginLogging,
        ];
    }

    private function wholeMinutes(int $seconds): ?int
    {
        return $seconds % 60 === 0 ? intdiv($seconds, 60) : null;
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
