<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings\Providers;

defined('ABSPATH') || exit;

/**
 * Asks Google whether it accepts a PageSpeed Insights key, without running a measurement (spec 119,
 * US-SET-03, DECISIONS #442).
 *
 * The request names no page. Google checks the key before it reads the request, so a bad key is
 * answered "API key not valid" (`API_KEY_INVALID`), and a good one gets as far as the missing page.
 * A measurement takes up to a minute and spends quota; this takes one short request.
 */
final class PageSpeedKeyProbe
{
    private const ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    public function probe(string $key): ProviderTestOutcome
    {
        $response = wp_remote_get(add_query_arg('key', rawurlencode($key), self::ENDPOINT), ['timeout' => 10]);

        if (is_wp_error($response)) {
            return ProviderTestOutcome::failed(__('Google could not be reached from this server', 'corex'));
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        return self::classify((int) wp_remote_retrieve_response_code($response), is_array($body) ? $body : null);
    }

    /**
     * @param array<string,mixed>|null $body the decoded answer
     */
    public static function classify(int $status, ?array $body): ProviderTestOutcome
    {
        $said = strtolower((string) wp_json_encode($body['error'] ?? ''));

        if (str_contains($said, 'api_key_invalid') || str_contains($said, 'api key not valid') || str_contains($said, 'keyinvalid')) {
            return ProviderTestOutcome::failed(__('Google refused the key', 'corex'));
        }

        if ($status === 429 || str_contains($said, 'quota') || str_contains($said, 'rate_limit')) {
            return ProviderTestOutcome::failed(__('the key\'s quota is used up for today', 'corex'));
        }

        if (str_contains($said, 'api_key_service_blocked') || str_contains($said, 'permission_denied') || $status === 403) {
            return ProviderTestOutcome::failed(__('the key may not use the PageSpeed Insights API', 'corex'));
        }

        // The key was accepted and the request went on to its (deliberately) missing page.
        if ($status === 400 || $status === 200) {
            return ProviderTestOutcome::worked();
        }

        return ProviderTestOutcome::failed(sprintf(
            /* translators: %d: the HTTP status Google answered with. */
            __('Google answered with HTTP %d', 'corex'),
            $status,
        ));
    }
}
