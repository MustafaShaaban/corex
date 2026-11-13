<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

/**
 * One write of the login settings at a time, per site (spec 124, FR-015).
 *
 * A save reads the stored option, changes some keys and writes it back. Two saves that overlap both
 * read the same option, and the second write loses the first one's change. A MySQL named lock, as
 * spec 079's access requests use (FR-008a), makes the second wait: it is held by this connection and
 * let go by MySQL if the request dies, so nothing is left to clear. It is named for the database and
 * the site's options table, so each site of a network, and each site on one database server, has
 * its own.
 */
final class LoginSettingsLock
{
    /**
     * How long a save waits for another to finish before it gives up. What it waits for is one read
     * and one write, so this is only reached by a save that has stopped or a database that has.
     */
    private const WAIT_SECONDS = 2;

    /**
     * Run the read-change-write while holding the lock, and let it go however it ends.
     *
     * @template T
     *
     * @param callable(): T $write
     *
     * @return T
     *
     * @throws LoginSettingsBusy when another save holds the lock past the wait
     */
    public function hold(callable $write): mixed
    {
        global $wpdb;

        $name = $this->name();
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $name, self::WAIT_SECONDS)) !== 1) {
            throw new LoginSettingsBusy();
        }

        try {
            return $write();
        } finally {
            $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
        }
    }

    /** The lock's name for the site this request is on. */
    public function name(): string
    {
        global $wpdb;

        return 'corex_login_settings_' . md5($wpdb->dbname . '|' . $wpdb->options);
    }
}
