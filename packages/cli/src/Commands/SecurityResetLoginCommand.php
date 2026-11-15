<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Commands;

defined('ABSPATH') || exit;

use Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore;
use Corex\Config\Security\LoginProtection\LoginLockoutStore;
use Corex\Config\Security\LoginProtection\LoginSlug;
use Corex\Config\Security\LoginProtection\LoginSwitches;
use Corex\Config\Security\LoginProtection\LoginUrl;
use DateTimeImmutable;

/**
 * Restores a safe login path without depending on the protected custom login URL.
 *
 * It turns all three login switches off (spec 124, FR-013, User Story 6 scenario 7), in both stored
 * shapes, so this release and an older one both read them off, puts the login address back to
 * `corex-login`, and releases every active lockout. Every other stored setting is kept.
 */
final class SecurityResetLoginCommand
{
    public function __construct(
        private readonly LoginLockoutStore $attempts,
        private readonly LoginProtectionSettingsStore $settings,
    ) {
    }

    /**
     * The settings are written under their lock, so a save from the screen at the same moment is not
     * lost. When another save holds them past the wait they are written anyway, without it, because
     * this is how a locked-out owner gets back in; `written_without_lock` says so, and so does the
     * command's output.
     *
     * @return array{restored_login_url:string,released_lockouts:int,unguard_constant_active:bool,written_without_lock:bool}
     */
    public function restore(DateTimeImmutable $now): array
    {
        $off = LoginSwitches::off();
        $locked = $this->settings->rewriteForRecovery(static fn (array $settings): array => array_replace($settings, [
            LoginProtectionSettingsStore::HIDE_WP_LOGIN  => $off->hideWpLogin,
            LoginProtectionSettingsStore::HIDE_ADMIN     => $off->hideAdmin,
            LoginProtectionSettingsStore::LIMIT_ATTEMPTS => $off->limitAttempts,
            'enabled'                                    => $off->previousEnabled(),
            'block_default_endpoints'                    => $off->previousBlockDefaultEndpoints(),
            // Reset the slug too: recovery exists for owners locked out by a bad one, and leaving
            // it in place means re-enabling protection walks straight back into the same lockout.
            'custom_slug'                                => LoginSlug::DEFAULT,
        ]));

        return [
            'restored_login_url' => $this->restoredLoginUrl(),
            'released_lockouts' => $this->attempts->releaseActiveLockouts($now),
            'unguard_constant_active' => defined('COREX_LOGIN_UNGUARD') && COREX_LOGIN_UNGUARD === true,
            'written_without_lock' => ! $locked,
        ];
    }

    /**
     * What the command prints after a reset, one line each.
     *
     * @param array{restored_login_url:string,released_lockouts:int,unguard_constant_active:bool,written_without_lock:bool} $result
     *
     * @return list<string>
     */
    public function report(array $result): array
    {
        $lines = [
            'Login switches: hide wp-login.php off, hide the admin off, limit failed sign-ins off.',
            sprintf('Restored login URL: %s', $result['restored_login_url']),
            sprintf('Released active lockouts: %d', $result['released_lockouts']),
            sprintf('COREX_LOGIN_UNGUARD active: %s', $result['unguard_constant_active'] ? 'yes' : 'no'),
        ];

        if ($result['written_without_lock']) {
            $lines[] = 'Another save of the login settings was in progress; it may have been overwritten. Check the settings once you are signed in.';
        }

        return $lines;
    }

    /**
     * The login URL that will actually work on the next request.
     *
     * Protection is off by the time this is read, so the stock login is the honest answer — and
     * LoginUrl::defaultUrl() builds it without the filters that would otherwise hand back the
     * address this command has just disabled (DECISIONS #140).
     */
    private function restoredLoginUrl(): string
    {
        return LoginUrl::defaultUrl();
    }

    public function run(): void
    {
        foreach ($this->report($this->restore(new DateTimeImmutable('now'))) as $line) {
            \WP_CLI::log($line);
        }

        \WP_CLI::success('CoreX login protection reset.');
    }
}
