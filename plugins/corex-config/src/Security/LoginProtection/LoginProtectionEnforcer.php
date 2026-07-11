<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use InvalidArgumentException;
use WP_Error;
use WP_User;

/**
 * Wires the login-protection policy into the real WordPress authentication lifecycle:
 * blocks locked-out identities before authentication completes, records failed attempts,
 * and logs supported successful sign-ins. Without this adapter the policy never enforces.
 */
final class LoginProtectionEnforcer
{
    public function __construct(
        private readonly LoginProtectionService $service,
        private readonly ClientIpResolver $ips,
        private readonly LoginProtectionSettings $settings,
    ) {
    }

    public function register(): void
    {
        // Late enough that the username/password resolution has run, so a valid credential during
        // an active lockout is still refused; early enough to short-circuit before wp_login fires.
        add_filter('authenticate', [$this, 'blockLockedOut'], 30, 3);
        add_action('wp_login_failed', [$this, 'recordFailure'], 10, 1);
        add_action('wp_login', [$this, 'recordSuccess'], 10, 2);
    }

    /**
     * @param WP_User|WP_Error|null $user
     *
     * @return WP_User|WP_Error|null
     */
    public function blockLockedOut(mixed $user, string $username, string $password): mixed
    {
        if (! $this->settings->enabled || $username === '') {
            return $user;
        }

        $context = $this->context($username);
        if ($context === null) {
            return $user;
        }

        if ($this->service->assess($context, new DateTimeImmutable('now'))->locked) {
            return new WP_Error(
                'corex_login_locked',
                __('Too many failed sign-in attempts. Please wait before trying again.', 'corex'),
            );
        }

        return $user;
    }

    public function recordFailure(string $username): void
    {
        if (! $this->settings->enabled || $username === '') {
            return;
        }

        $context = $this->context($username);
        if ($context !== null) {
            $this->service->recordFailure($context, new DateTimeImmutable('now'));
        }
    }

    public function recordSuccess(string $username, mixed $user = null): void
    {
        if (! $this->settings->enabled || ! $user instanceof WP_User) {
            return;
        }

        $context = $this->context($username);
        if ($context !== null) {
            $this->service->recordSuccess($context, $user->ID, new DateTimeImmutable('now'));
        }
    }

    private function context(string $identity): ?LoginProtectionContext
    {
        try {
            $userAgent = isset($_SERVER['HTTP_USER_AGENT'])
                ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))
                : 'unknown';

            return new LoginProtectionContext(
                identity: $identity,
                clientIp: $this->ips->resolve(is_array($_SERVER) ? $_SERVER : []),
                userAgent: substr($userAgent === '' ? 'unknown' : $userAgent, 0, 255),
            );
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
