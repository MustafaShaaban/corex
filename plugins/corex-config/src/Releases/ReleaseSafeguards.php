<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

/**
 * What stands between visitors and a half-installed site, and the way back, raised together
 * and for one installation (spec 107, plan D7 and D9; FR-024, FR-042).
 *
 * The way back is written first: an installation whose recovery file cannot be written does
 * not hold visitors off either, because it does not start.
 */
final class ReleaseSafeguards
{
    /**
     * @param string $home The site's front address, which the recovery link is under.
     */
    public function __construct(
        private readonly ReleaseMaintenance $maintenance,
        private readonly ReleaseRecoveryPlugin $recovery,
        private readonly string $home,
    ) {
    }

    /**
     * Write the way back, then hold visitors off.
     *
     * @param string $keyHash The SHA-256 of the installation's key, as hexadecimal.
     * @param int    $now     The time maintenance begins.
     *
     * @throws ReleaseRefused When either file cannot be written. Neither is left.
     */
    public function raise(string $keyHash, int $now): void
    {
        $this->recovery->write($keyHash);

        try {
            $this->maintenance->begin($keyHash, $now);
        } catch (ReleaseRefused $refused) {
            $this->recovery->remove();

            throw $refused;
        }
    }

    /**
     * Go on holding visitors off: WordPress stops by itself ten minutes after the time it was
     * given, and an installation that is still bringing a network's sites to the release is
     * making progress, not stuck.
     */
    public function hold(string $keyHash, int $now): void
    {
        if ($this->maintenance->isOn()) {
            $this->maintenance->begin($keyHash, $now);
        }
    }

    /** Let visitors in. The way back stays for as long as there is a release to go back to. */
    public function lower(): void
    {
        $this->maintenance->end();
    }

    /** Let visitors in and remove the way back: there is nothing to go back to. */
    public function remove(): void
    {
        $this->maintenance->end();
        $this->recovery->remove();
    }

    /** Whether the way back that needs no admin is on the site. */
    public function hasAWayBack(): bool
    {
        return $this->recovery->isWritten();
    }

    /** The address that puts the previous release back without the admin, for an installation's key. */
    public function link(string $key): string
    {
        return $this->recovery->link($this->home, $key);
    }
}
