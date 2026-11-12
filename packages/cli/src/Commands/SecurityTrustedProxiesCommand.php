<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Commands;

defined('ABSPATH') || exit;

use Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore;
use Corex\Config\Security\LoginProtection\TrustedProxyRange;
use Corex\Support\Options\LoadedOptions;

/**
 * Says which proxies a site trusts to report a visitor's address.
 *
 * Behind a proxy, a load balancer or a CDN the connection comes from the proxy, so a login lockout
 * and a form's rate limit count every visitor as one client until the proxy is named here. Every
 * proxy between the visitor and WordPress has to be named: the first one that is not is taken for
 * the client.
 */
final class SecurityTrustedProxiesCommand
{
    /**
     * @return list<string> The addresses and ranges trusted now; none while the mode is off.
     */
    public function trusted(): array
    {
        $settings = $this->settings();

        if (! ($settings['trusted_proxy_mode'] ?? false)) {
            return [];
        }

        return array_values(array_map('strval', (array) ($settings['trusted_proxy_ranges'] ?? [])));
    }

    /**
     * Replaces the list. An empty list trusts nobody.
     *
     * @param list<string> $ranges Addresses or CIDR ranges.
     *
     * @return list<string> The entries that are neither, or that trust every address (a `/0` range,
     *                      which would let any visitor choose the address a login lockout counts).
     *                      When there are any, nothing is saved.
     */
    public function trust(array $ranges): array
    {
        $refused = array_values(array_filter($ranges, static fn (string $range): bool => ! TrustedProxyRange::isValid($range)));

        if ($refused !== []) {
            return $refused;
        }

        $settings                         = $this->settings();
        $settings['trusted_proxy_mode']   = $ranges !== [];
        $settings['trusted_proxy_ranges'] = $ranges;
        LoadedOptions::store(LoginProtectionSettingsStore::OPTION, $settings);

        return [];
    }

    /**
     * @param list<string>       $args
     * @param array<string,mixed> $assoc
     */
    public function run(array $args, array $assoc): void
    {
        $clear = (bool) ($assoc['clear'] ?? false);

        if ($clear && $args !== []) {
            \WP_CLI::error('Give the proxies to trust, or --clear, not both.');
        }

        if (! $clear && $args === []) {
            $this->report();

            return;
        }

        $refused = $this->trust($args);

        if ($refused !== []) {
            \WP_CLI::error($this->refusal($refused));
        }

        $this->report();
        \WP_CLI::success('Trusted proxies saved.');
    }

    /**
     * Why the list was refused, naming the entries: a range that trusts every address is said apart,
     * because it is well-formed and the owner may not see what is wrong with it.
     *
     * @param list<string> $refused The entries {@see self::trust()} refused.
     */
    public function refusal(array $refused): string
    {
        $everyAddress = array_values(array_filter(
            $refused,
            static fn (string $range): bool => TrustedProxyRange::rejectionReason($range) === TrustedProxyRange::REASON_TRUSTS_EVERY_ADDRESS,
        ));
        $malformed = array_values(array_diff($refused, $everyAddress));

        $sentences = [];
        if ($malformed !== []) {
            $sentences[] = sprintf('Not an address or a range: %s.', implode(', ', $malformed));
        }
        if ($everyAddress !== []) {
            $sentences[] = sprintf(
                'Trusts every address: %s. A range that trusts every address would let anyone choose the address their sign-in attempts are counted by.',
                implode(', ', $everyAddress),
            );
        }
        $sentences[] = 'Nothing was changed.';

        return implode(' ', $sentences);
    }

    private function report(): void
    {
        $trusted = $this->trusted();

        if ($trusted === []) {
            \WP_CLI::log('No proxy is trusted. The address a request connects from identifies its client.');

            return;
        }

        foreach ($trusted as $range) {
            \WP_CLI::line($range);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function settings(): array
    {
        $settings = get_option(LoginProtectionSettingsStore::OPTION, []);

        return is_array($settings) ? $settings : [];
    }
}
