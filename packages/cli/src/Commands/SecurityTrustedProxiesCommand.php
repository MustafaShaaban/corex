<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Commands;

defined('ABSPATH') || exit;

use Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore;
use Corex\Config\Security\LoginProtection\LoginSettingsBusy;
use Corex\Config\Security\LoginProtection\TrustedProxyRange;

/**
 * Says which proxies a site trusts to report a visitor's address.
 *
 * Behind a proxy, a load balancer or a CDN the connection comes from the proxy, so a login lockout
 * and a form's rate limit count every visitor as one client until the proxy is named here. Every
 * proxy between the visitor and WordPress has to be named: the first one that is not is taken for
 * the client.
 *
 * The list is checked by the rule the Security screen's route uses ({@see TrustedProxyRange}): each
 * entry an address or range, no `/0`, at most 100, stored in one spelling. It is written under the
 * login settings' lock, so a save from the screen at the same moment is not lost.
 */
final class SecurityTrustedProxiesCommand
{
    public function __construct(private readonly LoginProtectionSettingsStore $store)
    {
    }

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
     *                      which would let any visitor choose the address a login lockout counts),
     *                      or past the 100th. When there are any, nothing is saved.
     *
     * @throws LoginSettingsBusy when a save of the login settings holds them past the wait
     */
    public function trust(array $ranges): array
    {
        if (TrustedProxyRange::tooMany(count($ranges))) {
            return array_values(array_slice($ranges, TrustedProxyRange::MAX_LINES));
        }

        $refused = array_values(array_filter($ranges, static fn (string $range): bool => ! TrustedProxyRange::isValid($range)));

        if ($refused !== []) {
            return $refused;
        }

        $spelled = array_map(static fn (string $range): string => (string) TrustedProxyRange::canonical($range), $ranges);
        $this->store->rewrite(static fn (array $stored): array => array_replace($stored, [
            'trusted_proxy_mode'   => $spelled !== [],
            'trusted_proxy_ranges' => $spelled,
        ]));

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

        try {
            $refused = $this->trust($args);
        } catch (LoginSettingsBusy) {
            \WP_CLI::error('The login settings are being saved by another request. Nothing was changed; run the command again.');
        }

        if ($refused !== []) {
            \WP_CLI::error($this->refusal($args));
        }

        $this->report();
        \WP_CLI::success('Trusted proxies saved.');
    }

    /**
     * Why the list was refused, naming the entries: a range that trusts every address is said apart,
     * because it is well-formed and the owner may not see what is wrong with it.
     *
     * @param list<string> $ranges The list given to {@see self::trust()}.
     */
    public function refusal(array $ranges): string
    {
        if (TrustedProxyRange::tooMany(count($ranges))) {
            return sprintf('Give at most %d proxies. Nothing was changed.', TrustedProxyRange::MAX_LINES);
        }

        $reasons = array_map(static fn (string $range): ?string => TrustedProxyRange::rejectionReason($range), $ranges);
        $everyAddress = array_values(array_filter(
            $ranges,
            static fn (string $range, int $i): bool => $reasons[$i] === TrustedProxyRange::REASON_TRUSTS_EVERY_ADDRESS,
            ARRAY_FILTER_USE_BOTH,
        ));
        $malformed = array_values(array_filter(
            $ranges,
            static fn (string $range, int $i): bool => $reasons[$i] === TrustedProxyRange::REASON_FORMAT,
            ARRAY_FILTER_USE_BOTH,
        ));

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
