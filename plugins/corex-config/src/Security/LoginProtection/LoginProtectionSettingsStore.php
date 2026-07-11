<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

/**
 * Reads and normalizes persisted login-protection settings.
 */
final class LoginProtectionSettingsStore
{
    public const OPTION = 'corex_login_protection_settings';

    /**
     * Persist a sanitized login-protection payload and return the stored settings. The custom login
     * URL is served by {@see LoginRouteGuard} via request interception, so no rewrite flush is needed.
     *
     * @param array<string,mixed> $input
     */
    public function save(array $input): LoginProtectionSettings
    {
        $stored = [
            'enabled'                  => (bool) ($input['enabled'] ?? false),
            'custom_slug'              => sanitize_title((string) ($input['custom_slug'] ?? 'corex-login')) ?: 'corex-login',
            'block_default_endpoints'  => (bool) ($input['block_default_endpoints'] ?? true),
            'threshold'                => max(1, (int) ($input['threshold'] ?? 5)),
            'window_seconds'           => max(1, (int) ($input['window_seconds'] ?? 300)),
            'lockout_seconds'          => max(1, (int) ($input['lockout_seconds'] ?? 900)),
            'trusted_proxy_mode'       => (bool) ($input['trusted_proxy_mode'] ?? false),
            'trusted_proxy_ranges'     => $this->strings($input['trusted_proxy_ranges'] ?? []),
            'retain_days'              => max(1, (int) ($input['retain_days'] ?? 30)),
            'successful_login_logging' => (bool) ($input['successful_login_logging'] ?? true),
        ];
        update_option(self::OPTION, $stored, false);

        return $this->current();
    }

    public function current(): LoginProtectionSettings
    {
        $stored = get_option(self::OPTION, []);
        $stored = is_array($stored) ? $stored : [];

        return new LoginProtectionSettings(
            enabled: (bool) ($stored['enabled'] ?? false),
            customSlug: sanitize_title((string) ($stored['custom_slug'] ?? 'corex-login')),
            blockDefaultEndpoints: (bool) ($stored['block_default_endpoints'] ?? true),
            threshold: max(1, (int) ($stored['threshold'] ?? 5)),
            windowSeconds: max(1, (int) ($stored['window_seconds'] ?? 300)),
            lockoutSeconds: max(1, (int) ($stored['lockout_seconds'] ?? 900)),
            trustedProxyMode: (bool) ($stored['trusted_proxy_mode'] ?? false),
            trustedProxyRanges: $this->strings($stored['trusted_proxy_ranges'] ?? []),
            retainDays: max(1, (int) ($stored['retain_days'] ?? 30)),
            successfulLoginLogging: (bool) ($stored['successful_login_logging'] ?? true),
        );
    }

    /**
     * @param mixed $value
     *
     * @return list<string>
     */
    private function strings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $candidate): string => trim((string) $candidate),
            $value,
        )));
    }
}
