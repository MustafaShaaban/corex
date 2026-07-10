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
