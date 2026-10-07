<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Commands;

defined('ABSPATH') || exit;

use Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore;

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
     * @return list<string> The entries that are neither. When there are any, nothing is saved.
     */
    public function trust(array $ranges): array
    {
        $refused = array_values(array_filter($ranges, fn (string $range): bool => ! $this->isAddressOrRange($range)));

        if ($refused !== []) {
            return $refused;
        }

        $settings                         = $this->settings();
        $settings['trusted_proxy_mode']   = $ranges !== [];
        $settings['trusted_proxy_ranges'] = $ranges;
        update_option(LoginProtectionSettingsStore::OPTION, $settings, false);

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
            \WP_CLI::error(sprintf('Not an address or a range: %s. Nothing was changed.', implode(', ', $refused)));
        }

        $this->report();
        \WP_CLI::success('Trusted proxies saved.');
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

    private function isAddressOrRange(string $range): bool
    {
        [$address, $prefix] = array_pad(explode('/', $range, 2), 2, null);
        $packed             = filter_var($address, FILTER_VALIDATE_IP) === false ? false : inet_pton($address);

        if ($packed === false) {
            return false;
        }

        return $prefix === null || (ctype_digit($prefix) && (int) $prefix <= strlen($packed) * 8);
    }
}
