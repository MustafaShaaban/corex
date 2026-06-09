<?php

/**
 * @package Corex\Captcha
 */

declare(strict_types=1);

namespace Corex\Captcha;

defined('ABSPATH') || exit;

/**
 * A remote provider driver (reCAPTCHA / Turnstile / hCaptcha — all `{success}`
 * shaped, differing only by verify URL + secret). Posts the token to the provider
 * and passes only on a confirmed `success`. Fail-closed: a missing secret/token, a
 * transport error, or a non-success response all return false. The secret is never
 * logged.
 */
final class RemoteCaptcha implements Captcha
{
    public function __construct(
        private readonly string $verifyUrl,
        private readonly string $secret,
    ) {
    }

    /**
     * @param array<string,mixed> $context
     */
    public function verify(string $token, array $context = []): bool
    {
        if ($this->secret === '' || $token === '') {
            return false;
        }

        $response = wp_remote_post($this->verifyUrl, [
            'timeout' => 10,
            'body'    => ['secret' => $this->secret, 'response' => $token],
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        return is_array($body) && ($body['success'] ?? false) === true;
    }
}
