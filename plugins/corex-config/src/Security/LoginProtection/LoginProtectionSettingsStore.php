<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

use Corex\Support\Options\LoadedOptions;

/**
 * Reads and normalizes persisted login-protection settings.
 *
 * The settings are read as CoreX boots, on every request, so they are stored where WordPress loads
 * them with the site's options ({@see LoadedOptions}). Whatever writes this option writes it so.
 *
 * Two shapes are stored (spec 124, FR-014). The previous release wrote `enabled` and
 * `block_default_endpoints`; this one writes the three switches ({@see LoginSwitches}) and, beside
 * them, those two keys as an older CoreX should read them. A read never rewrites the option.
 */
final class LoginProtectionSettingsStore
{
    public const OPTION = 'corex_login_protection_settings';

    /** The stored keys of the three switches (spec 124, FR-013). */
    public const HIDE_WP_LOGIN = 'hide_wp_login';

    public const HIDE_ADMIN = 'hide_admin';

    public const LIMIT_ATTEMPTS = 'limit_attempts';

    private const SWITCH_KEYS = [self::HIDE_WP_LOGIN, self::HIDE_ADMIN, self::LIMIT_ATTEMPTS];

    /** The stored keys {@see self::update()} changes one by one; the switches come as one value. */
    private const CHANGEABLE_KEYS = [
        'custom_slug',
        'threshold',
        'window_seconds',
        'lockout_seconds',
        'trusted_proxy_mode',
        'trusted_proxy_ranges',
        'retain_days',
        'successful_login_logging',
    ];

    /**
     * Persist a sanitized login-protection payload and return the stored settings. The custom login
     * URL is served by {@see LoginRouteGuard} via request interception, so no rewrite flush is needed.
     *
     * The payload names the switches or, from a screen of the previous release, `enabled` and
     * `block_default_endpoints`, which are read in their old meaning. A switch left out of a payload
     * that names another is off. Hiding `wp-login.php` needs a usable address: a payload that hides
     * it and clears the address is refused, and nothing is written.
     *
     * @param array<string,mixed> $input
     *
     * @throws LoginSettingsRefused when wp-login.php would be hidden behind no usable address
     */
    public function save(array $input): LoginProtectionSettings
    {
        $switches = $this->switchesIn($input);

        // A payload with no address at all asks for the default, as it always has; one that sends
        // an address the owner cleared or that cannot be used is not quietly given another.
        if ($switches->hideWpLogin && array_key_exists('custom_slug', $input)) {
            $reason = LoginSlug::rejectionReason(sanitize_title(trim((string) $input['custom_slug'])));
            if ($reason !== null) {
                throw LoginSettingsRefused::addressNeededToHide($reason);
            }
        }

        $stored = [
            self::HIDE_WP_LOGIN        => $switches->hideWpLogin,
            self::HIDE_ADMIN           => $switches->hideAdmin,
            self::LIMIT_ATTEMPTS       => $switches->limitAttempts,
            'enabled'                  => $switches->previousEnabled(),
            'custom_slug'              => $this->slug($input['custom_slug'] ?? null),
            'block_default_endpoints'  => $switches->previousBlockDefaultEndpoints(),
            'threshold'                => max(1, (int) ($input['threshold'] ?? 5)),
            'window_seconds'           => max(1, (int) ($input['window_seconds'] ?? 300)),
            'lockout_seconds'          => max(1, (int) ($input['lockout_seconds'] ?? 900)),
            'trusted_proxy_mode'       => (bool) ($input['trusted_proxy_mode'] ?? false),
            'trusted_proxy_ranges'     => $this->strings($input['trusted_proxy_ranges'] ?? []),
            'retain_days'              => max(1, (int) ($input['retain_days'] ?? 30)),
            'successful_login_logging' => (bool) ($input['successful_login_logging'] ?? true),
        ];
        LoadedOptions::store(self::OPTION, $stored);

        return $this->current();
    }

    /**
     * Change only the keys given, and leave every other stored key as it is (spec 124, FR-015).
     *
     * `save()` writes every setting, with a default for each one it is not given; this writes over
     * the option as stored, so a value no request sent (a 30-second window set from the command line,
     * a key another release wrote) is not rewritten. The switches are written, with the previous
     * release's two keys beside them (FR-014), only when they are given; otherwise all five stay as
     * stored and are read as {@see self::current()} reads them. Nothing is written when nothing is
     * given.
     *
     * @param array<string,mixed> $changes stored key => value; keys this store does not keep are ignored
     *
     * @throws LoginSettingsRefused when wp-login.php would be hidden behind no usable address
     */
    public function update(array $changes, ?LoginSwitches $switches = null): LoginProtectionSettings
    {
        $changes = array_intersect_key($changes, array_flip(self::CHANGEABLE_KEYS));

        if ($changes === [] && $switches === null) {
            return $this->current();
        }

        $hiding = ($switches ?? $this->current()->switches())->hideWpLogin;
        if ($hiding && array_key_exists('custom_slug', $changes)) {
            $reason = LoginSlug::rejectionReason(sanitize_title(trim((string) $changes['custom_slug'])));
            if ($reason !== null) {
                throw LoginSettingsRefused::addressNeededToHide($reason);
            }
        }

        $stored = get_option(self::OPTION, []);
        $stored = is_array($stored) ? $stored : [];

        if ($switches !== null) {
            $changes += [
                'enabled'                 => $switches->previousEnabled(),
                'block_default_endpoints' => $switches->previousBlockDefaultEndpoints(),
                self::HIDE_WP_LOGIN       => $switches->hideWpLogin,
                self::HIDE_ADMIN          => $switches->hideAdmin,
                self::LIMIT_ATTEMPTS      => $switches->limitAttempts,
            ];
        }

        LoadedOptions::store(self::OPTION, array_replace($stored, $changes));

        return $this->current();
    }

    public function current(): LoginProtectionSettings
    {
        $stored = get_option(self::OPTION, []);
        $stored = is_array($stored) ? $stored : [];

        $enabled = (bool) ($stored['enabled'] ?? false);
        $blockDefaultEndpoints = (bool) ($stored['block_default_endpoints'] ?? true);
        $switches = $this->storedSwitches($stored, $enabled, $blockDefaultEndpoints);

        return new LoginProtectionSettings(
            enabled: $switches === null ? $enabled : $switches->previousEnabled(),
            customSlug: $this->slug($stored['custom_slug'] ?? null),
            blockDefaultEndpoints: $switches === null ? $blockDefaultEndpoints : $switches->previousBlockDefaultEndpoints(),
            threshold: max(1, (int) ($stored['threshold'] ?? 5)),
            windowSeconds: max(1, (int) ($stored['window_seconds'] ?? 300)),
            lockoutSeconds: max(1, (int) ($stored['lockout_seconds'] ?? 900)),
            trustedProxyMode: (bool) ($stored['trusted_proxy_mode'] ?? false),
            trustedProxyRanges: $this->strings($stored['trusted_proxy_ranges'] ?? []),
            retainDays: max(1, (int) ($stored['retain_days'] ?? 30)),
            successfulLoginLogging: (bool) ($stored['successful_login_logging'] ?? true),
            switches: $switches,
        );
    }

    /**
     * The switches a payload asks for: its own, or the previous release's two keys in their meaning.
     *
     * @param array<string,mixed> $input
     */
    private function switchesIn(array $input): LoginSwitches
    {
        if (array_intersect(self::SWITCH_KEYS, array_keys($input)) === []) {
            return LoginSwitches::fromPreviousRelease(
                (bool) ($input['enabled'] ?? false),
                (bool) ($input['block_default_endpoints'] ?? true),
            );
        }

        return new LoginSwitches(
            (bool) ($input[self::HIDE_WP_LOGIN] ?? false),
            (bool) ($input[self::HIDE_ADMIN] ?? false),
            (bool) ($input[self::LIMIT_ATTEMPTS] ?? false),
        );
    }

    /**
     * The stored switches, or null when the option is read in the previous release's meaning.
     *
     * The switches count only when all three are stored and the two old keys beside them still say
     * what this release wrote. An older CoreX changes only the old keys: its screen saves them alone,
     * and its recovery command turns them off and keeps every other key. When they disagree they were
     * changed after the switches were written, and they win, so an older recovery is not undone by
     * running this release again.
     *
     * @param array<string,mixed> $stored
     */
    private function storedSwitches(array $stored, bool $enabled, bool $blockDefaultEndpoints): ?LoginSwitches
    {
        foreach (self::SWITCH_KEYS as $key) {
            if (! array_key_exists($key, $stored)) {
                return null;
            }
        }

        $switches = new LoginSwitches(
            (bool) $stored[self::HIDE_WP_LOGIN],
            (bool) $stored[self::HIDE_ADMIN],
            (bool) $stored[self::LIMIT_ATTEMPTS],
        );

        $agrees = (! array_key_exists('enabled', $stored) || $enabled === $switches->previousEnabled())
            && (! array_key_exists('block_default_endpoints', $stored)
                || $blockDefaultEndpoints === $switches->previousBlockDefaultEndpoints());

        return $agrees ? $switches : null;
    }

    /**
     * The one slug resolution both reads and writes use.
     *
     * Reads went through a different rule than writes, so a stored slug could read back as
     * something the writer would never have produced — including an empty string, which meant
     * "no login URL exists" while the default endpoint stayed hidden. Because the container
     * rebuilds LoginProtectionSettings on every make(), an unusable read also took the entire
     * ConfigServiceProvider down and silently disabled login protection (DECISIONS #140).
     * Anything that resolves a stored slug MUST come through here.
     */
    private function slug(mixed $raw): string
    {
        return LoginSlug::orDefault(sanitize_title((string) ($raw ?? LoginSlug::DEFAULT)));
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
