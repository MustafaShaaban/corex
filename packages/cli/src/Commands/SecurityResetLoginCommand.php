<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Commands;

defined('ABSPATH') || exit;

use Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore;
use Corex\Config\Security\LoginProtection\LoginLockoutStore;
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
        update_option(LoginProtectionSettingsStore::OPTION, $settings, false);

        return [
            'restored_login_url' => function_exists('wp_login_url') ? wp_login_url() : admin_url('wp-login.php'),
            'released_lockouts' => $this->attempts->releaseActiveLockouts($now),
            'unguard_constant_active' => defined('COREX_LOGIN_UNGUARD') && COREX_LOGIN_UNGUARD === true,
        ];
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
