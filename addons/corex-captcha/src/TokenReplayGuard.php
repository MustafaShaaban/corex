<?php

/**
 * @package Corex\Captcha
 */

declare(strict_types=1);

namespace Corex\Captcha;

defined('ABSPATH') || exit;

/**
 * Prevents a single reCAPTCHA token from being accepted twice.
 *
 * State is a transient keyed on an HMAC fingerprint of the token — never the token itself —
 * with a TTL a little longer than the token's own validity window. The TTL *is* the bound and
 * the cleanup: an entry that outlives the token's expiry is harmless, because an expired token
 * fails the age check first, so no separate pruning job or table is needed. Transients are
 * shared across requests, so two simultaneous submissions of the same token cannot both win.
 *
 * `consume()` is check-and-set in one call: it returns true exactly once per token (the first
 * caller records the fingerprint) and false thereafter.
 */
class TokenReplayGuard
{
    private const PREFIX = 'corex_captcha_seen_';
    private const TTL_SECONDS = 150; // token window (~120s) + margin

    public function consume(string $token): bool
    {
        $key = self::PREFIX . hash_hmac('sha256', $token, (string) wp_salt('auth'));

        if (get_transient($key) !== false) {
            return false; // already consumed within its validity window
        }

        set_transient($key, 1, self::TTL_SECONDS);

        return true;
    }
}
