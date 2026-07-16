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
use DateTimeImmutable;

/**
 * Restores a safe login path without depending on the protected custom login URL.
 */
final class SecurityResetLoginCommand
{
    public function __construct(private readonly LoginLockoutStore $attempts)
    {
    }

    /**
     * @return array{restored_login_url:string,released_lockouts:int,unguard_constant_active:bool}
     */
    public function restore(DateTimeImmutable $now): array
    {
        $settings = get_option(LoginProtectionSettingsStore::OPTION, []);
        $settings = is_array($settings) ? $settings : [];
        $settings['enabled'] = false;
        $settings['block_default_endpoints'] = false;
        // Reset the slug too: recovery exists for owners locked out by a bad one, and leaving it
        // in place means re-enabling protection walks straight back into the same lockout.
        $settings['custom_slug'] = LoginSlug::DEFAULT;
        update_option(LoginProtectionSettingsStore::OPTION, $settings, false);

        return [
            'restored_login_url' => $this->restoredLoginUrl(),
            'released_lockouts' => $this->attempts->releaseActiveLockouts($now),
            'unguard_constant_active' => defined('COREX_LOGIN_UNGUARD') && COREX_LOGIN_UNGUARD === true,
        ];
    }

    /**
     * The login URL that will actually work on the next request.
     *
     * Deliberately not wp_login_url(): LoginRouteGuard's filters are already registered in this
     * process and still rewrite to the custom slug, so wp_login_url() reports the address this
     * command has just disabled. That is not hypothetical — it printed a URL that 404'd for an
     * owner who was already locked out, which is the one moment the advice has to be right
     * (DECISIONS #140). Reading the raw option sidesteps the filter.
     */
    private function restoredLoginUrl(): string
    {
        return rtrim((string) get_option('siteurl'), '/') . '/wp-login.php';
    }

    public function run(): void
    {
        $result = $this->restore(new DateTimeImmutable('now'));

        \WP_CLI::log(sprintf('Restored login URL: %s', $result['restored_login_url']));
        \WP_CLI::log(sprintf('Released active lockouts: %d', $result['released_lockouts']));
        \WP_CLI::log(sprintf(
            'COREX_LOGIN_UNGUARD active: %s',
            $result['unguard_constant_active'] ? 'yes' : 'no',
        ));
        \WP_CLI::success('CoreX login protection reset.');
    }
}
