<?php

/**
 * @package Corex\Captcha
 */

declare(strict_types=1);

namespace Corex\Captcha;

defined('ABSPATH') || exit;

use Corex\Http\ResponseEnvelope;
use Corex\Support\Config\ConfigInterface;
use WP_REST_Request;
use WP_REST_Response;

/**
 * The "Test verification" REST action for the captcha card (spec 044, US2). Runs a probe
 * against the configured provider and answers with the spec-043 envelope classified by
 * {@see CaptchaDiagnostic}. `manage_options` + a REST nonce gate it (Principle VII); the
 * secret is used only in the outbound probe and **never** appears in the response (FR-008).
 */
final class CaptchaTestController
{
    private const PROBE_TOKEN = 'corex-captcha-test-token';

    public function __construct(private readonly ConfigInterface $config)
    {
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'route']);
    }

    public function route(): void
    {
        register_rest_route('corex/v1', '/captcha/test', [
            'methods'             => 'POST',
            'callback'            => [$this, 'test'],
            'permission_callback' => [$this, 'canRun'],
        ]);
    }

    public function canRun(WP_REST_Request $request): bool
    {
        return current_user_can('manage_options')
            && wp_verify_nonce((string) $request->get_header('X-WP-Nonce'), 'wp_rest') !== false;
    }

    public function test(WP_REST_Request $request): WP_REST_Response
    {
        $diagnostic = $this->diagnose();

        $envelope = $diagnostic->kind === CaptchaDiagnostic::OK || $diagnostic->kind === CaptchaDiagnostic::NOT_APPLICABLE
            ? ResponseEnvelope::success(['kind' => $diagnostic->kind], $diagnostic->message)
            : ResponseEnvelope::error($diagnostic->kind, $diagnostic->message);

        return new WP_REST_Response($envelope->toArray(), $envelope->ok ? 200 : 400);
    }

    private function diagnose(): CaptchaDiagnostic
    {
        $driver    = (string) $this->config->get('captcha.driver', 'none');
        $siteKey   = trim((string) $this->config->get('captcha.site_key', ''));
        $secret    = trim((string) $this->config->get('captcha.secret', ''));
        $hasSite   = $siteKey !== '';
        $hasSecret = $secret !== '';
        $endpoint  = CaptchaResolver::endpoint($driver);

        if ($endpoint === null) {
            return CaptchaDiagnostic::ofKind(CaptchaDiagnostic::NOT_APPLICABLE);
        }
        if (! $hasSite || ! $hasSecret) {
            return CaptchaDiagnostic::ofKind(CaptchaDiagnostic::MISSING_KEYS);
        }

        $response = wp_remote_post($endpoint, [
            'timeout' => 10,
            'body'    => ['secret' => $secret, 'response' => self::PROBE_TOKEN],
        ]);

        if (is_wp_error($response)) {
            return CaptchaDiagnostic::fromVerifyResponse($driver, true, true, null, true);
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        return CaptchaDiagnostic::fromVerifyResponse($driver, true, true, is_array($body) ? $body : null);
    }
}
