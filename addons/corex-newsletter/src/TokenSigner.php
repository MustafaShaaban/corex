<?php

/**
 * @package Corex\Newsletter
 */

declare(strict_types=1);

namespace Corex\Newsletter;

defined('ABSPATH') || exit;

/**
 * Signs and verifies a payload with an HMAC over a config secret (URL-safe base64).
 * Used for the confirm and unsubscribe links — a tampered token verifies to null
 * (fail-closed), and there is no email enumeration. Pure; constant-time compare.
 */
final class TokenSigner
{
    public function __construct(private readonly string $secret)
    {
    }

    public function sign(string $payload): string
    {
        $signature = hash_hmac('sha256', $payload, $this->secret);

        return rtrim(strtr(base64_encode($payload . '|' . $signature), '+/', '-_'), '=');
    }

    public function verify(string $token): ?string
    {
        $decoded = base64_decode(strtr($token, '-_', '+/'), true);
        if ($decoded === false) {
            return null;
        }

        $parts = explode('|', $decoded, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$payload, $signature] = $parts;
        $expected = hash_hmac('sha256', $payload, $this->secret);

        return hash_equals($expected, $signature) ? $payload : null;
    }
}
