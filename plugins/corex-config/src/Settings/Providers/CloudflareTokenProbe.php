<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings\Providers;

defined('ABSPATH') || exit;

/**
 * Asks Cloudflare whether it accepts an API token (spec 119, US-SET-03, DECISIONS #442).
 *
 * Cloudflare verifies a user's token at `/user/tokens/verify` and a token owned by an account at
 * `/accounts/<id>/tokens/verify`; the second is asked only when the first does not accept it and an
 * account ID is set. Whether the account ID is right is known when a scan runs.
 */
final class CloudflareTokenProbe
{
    private const API = 'https://api.cloudflare.com/client/v4';

    public function probe(string $token, string $accountId): ProviderTestOutcome
    {
        $outcome = $this->verify(self::API . '/user/tokens/verify', $token);

        if (! $outcome->worked && $accountId !== '' && preg_match('/^[a-f0-9]{32}$/i', $accountId) === 1) {
            $outcome = $this->verify(self::API . '/accounts/' . $accountId . '/tokens/verify', $token);
        }

        return $outcome;
    }

    /**
     * @param array<string,mixed>|null $body the decoded answer
     */
    public static function classify(?array $body): ProviderTestOutcome
    {
        if ($body === null) {
            return ProviderTestOutcome::failed(__('Cloudflare gave an answer CoreX cannot read', 'corex'));
        }

        $status = is_array($body['result'] ?? null) ? (string) ($body['result']['status'] ?? '') : '';

        if (($body['success'] ?? false) === true && $status === 'active') {
            return ProviderTestOutcome::worked();
        }

        if (($body['success'] ?? false) === true) {
            return ProviderTestOutcome::failed(sprintf(
                /* translators: %s: the token's status as Cloudflare names it, such as "disabled" or "expired". */
                __('Cloudflare says the token is %s', 'corex'),
                $status === '' ? __('not active', 'corex') : $status,
            ));
        }

        return ProviderTestOutcome::failed(__('Cloudflare refused the token', 'corex'));
    }

    private function verify(string $url, string $token): ProviderTestOutcome
    {
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => ['Authorization' => 'Bearer ' . $token],
        ]);

        if (is_wp_error($response)) {
            return ProviderTestOutcome::failed(__('Cloudflare could not be reached from this server', 'corex'));
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        return self::classify(is_array($body) ? $body : null);
    }
}
