<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security;

defined('ABSPATH') || exit;

use Corex\Access\AbilityCheck;
use Corex\Access\CorexAbility;
use Corex\Config\Security\LoginProtection\LockoutReleaseOutcome;
use Corex\Config\Security\LoginProtection\LockoutReleaser;
use Corex\Http\ResponseEnvelope;
use DateTimeImmutable;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Releases a lockout, or every active lockout, from the Security Center (spec 124, FR-017).
 *
 * - `POST corex/v1/security/lockouts/{id}/release` ends the lockout of the panel row `id`;
 * - `POST corex/v1/security/lockouts/release-all` ends every active lockout and nothing else.
 *
 * "Manage operations" and the REST nonce are asked of both, as of the login settings. Every answer
 * carries the panel's rows as {@see LockoutRows} draws them on load, so the screen redraws from it:
 * in `data.lockouts` when something was released (200), in `details.lockouts` when nothing was,
 * because the lockout had already ended or none was active (409, the request asks for a change the
 * lockout's state no longer allows) or the row names no lockout (404). The person releasing is the
 * current user, recorded in the activity log by {@see LockoutReleaser}.
 */
final class LockoutReleaseController
{
    private const SCREEN = 'corex-operations-security';

    private const NAMESPACE = 'corex/v1';

    public function __construct(
        private readonly LockoutReleaser $releaser,
        private readonly LockoutRows $rows,
        private readonly AbilityCheck $abilities,
    ) {
    }

    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            register_rest_route(self::NAMESPACE, '/security/lockouts/(?P<id>[0-9]+)/release', [
                'methods'             => 'POST',
                'callback'            => [$this, 'releaseOne'],
                'permission_callback' => [$this, 'allowed'],
                'args'                => [
                    'id' => [
                        'required'    => true,
                        'description' => __('The lockout to release: the id of its row on the Lockouts panel.', 'corex'),
                    ],
                ],
            ]);
            register_rest_route(self::NAMESPACE, '/security/lockouts/release-all', [
                'methods'             => 'POST',
                'callback'            => [$this, 'releaseAll'],
                'permission_callback' => [$this, 'allowed'],
            ]);
        });
    }

    /** Releasing a lockout: the screen's ability (spec 117, FR-002), with the REST nonce. */
    public function allowed(WP_REST_Request $request): bool
    {
        return $this->abilities->allows(CorexAbility::MANAGE_OPERATIONS, self::SCREEN) && $this->hasNonce($request);
    }

    public function releaseOne(WP_REST_Request $request): WP_REST_Response
    {
        $now = new DateTimeImmutable('now');
        // The URL's id, never `get_param()`: an `id` in the body would win over it there.
        $id = (string) ($request->get_url_params()['id'] ?? '');

        return $this->answer(
            $this->releaser->releaseOne($id, get_current_user_id(), $now),
            $now,
            __('The lockout was released. That username can sign in again from that network now.', 'corex'),
        );
    }

    public function releaseAll(WP_REST_Request $request): WP_REST_Response
    {
        $now = new DateTimeImmutable('now');
        $outcome = $this->releaser->releaseAll(get_current_user_id(), $now);

        return $this->answer(
            $outcome,
            $now,
            sprintf(
                /* translators: %s: how many lockouts were released. */
                _n('%s active lockout was released.', '%s active lockouts were released.', max(1, $outcome->released), 'corex'),
                number_format_i18n($outcome->released),
            ),
        );
    }

    private function answer(LockoutReleaseOutcome $outcome, DateTimeImmutable $now, string $released): WP_REST_Response
    {
        $lockouts = $this->rows->rows($now);

        if ($outcome->status === LockoutReleaseOutcome::RELEASED) {
            return new WP_REST_Response(
                ResponseEnvelope::success(['released' => $outcome->released, 'lockouts' => $lockouts], $released)->toArray(),
                200,
            );
        }

        [$code, $message, $status] = match ($outcome->status) {
            LockoutReleaseOutcome::ALREADY_ENDED => [
                'lockout_already_ended',
                __('This lockout has already ended. Nothing was changed.', 'corex'),
                409,
            ],
            LockoutReleaseOutcome::NONE_ACTIVE => [
                'no_active_lockouts',
                __('There is no active lockout to release. Nothing was changed.', 'corex'),
                409,
            ],
            default => [
                'lockout_not_found',
                __('That lockout was not found. It may have been removed when old sign-in records were cleared.', 'corex'),
                404,
            ],
        };

        return new WP_REST_Response(
            ResponseEnvelope::error($code, $message, ['lockouts' => $lockouts])->toArray(),
            $status,
        );
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
