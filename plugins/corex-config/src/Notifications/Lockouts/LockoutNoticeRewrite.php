<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Notifications\Lockouts;

defined('ABSPATH') || exit;

use Corex\Config\Security\LoginProtection\LoginProtectionPolicy;
use Corex\Database\Schema\DataMigrationStep;
use DateTimeImmutable;
use Exception;

/**
 * Rewrites, once, the lockout notices stored before spec 124 to the rule a new one follows (FR-020,
 * US-SEC-10 scenario 5).
 *
 * The release before kept the typed username in a notice's title, merge key and source, and the
 * visitor's address in its body and details. Each such notice becomes "unknown username" (an old
 * lockout recorded no account, and none is looked up now), with the network only as a fingerprint.
 *
 * Its fingerprints are the ones the Lockouts panel shows for the rows of that old lockout, which hold
 * plain hashes: the old values are read here, hashed as the release before hashed them, keyed into a
 * fingerprint, and dropped, never written anywhere. Its
 * merge key is its own (`security.lockout:legacy:<id>`), so it can never collide with a notice
 * written since the update, and a new lockout opens a notice of its own.
 *
 * It runs as a data step of the product foundation's schema (version 5), in the scope of each site a
 * migration reaches. It reads in batches by id, so every row is read once, and it selects only notices
 * not yet rewritten, so a second run changes nothing.
 */
final class LockoutNoticeRewrite implements DataMigrationStep
{
    private const LEGACY_KEY_PREFIX = LoginLockoutNotice::TYPE . ':';

    public function __construct(
        private readonly LockoutNoticeStore $notices,
        private readonly LoginProtectionPolicy $policy,
        private readonly int $batchSize = 100,
    ) {
    }

    public function id(): string
    {
        return 'lockout-notice-privacy';
    }

    public function run(): void
    {
        $afterId = 0;

        do {
            $batch = $this->notices->legacyAfter($afterId, $this->batchSize);
            foreach ($batch as $notice) {
                $this->notices->rewrite($notice->id, $this->rewritten($notice));
                $afterId = $notice->id;
            }
        } while (count($batch) === $this->batchSize);
    }

    private function rewritten(StoredLockoutNotice $old): LoginLockoutNotice
    {
        return LoginLockoutNotice::of(
            null,
            null,
            $this->policy->fingerprint($this->policy->legacyIdentityHashOf($this->typedUsername($old))),
            $this->network($old->metadata['client_ip'] ?? null),
            $this->lockedUntil($old),
        )->withDedupKey(self::LEGACY_KEY_PREFIX . 'legacy:' . $old->id);
    }

    /** What was typed, from where the old producer put it: the source id, else its merge key. */
    private function typedUsername(StoredLockoutNotice $old): string
    {
        if ($old->sourceId !== null && $old->sourceId !== '') {
            return $old->sourceId;
        }

        return str_starts_with($old->dedupKey, self::LEGACY_KEY_PREFIX)
            ? substr($old->dedupKey, strlen(self::LEGACY_KEY_PREFIX))
            : $old->dedupKey;
    }

    private function network(mixed $clientIp): string
    {
        if (! is_string($clientIp) || filter_var($clientIp, FILTER_VALIDATE_IP) === false) {
            return '';
        }

        return $this->policy->fingerprint($this->policy->legacyNetworkHashOf($clientIp));
    }

    private function lockedUntil(StoredLockoutNotice $old): DateTimeImmutable
    {
        $stored = $old->metadata['locked_until'] ?? null;
        if (is_string($stored) && $stored !== '') {
            try {
                return new DateTimeImmutable($stored);
            } catch (Exception) {
                // An end that cannot be read: the notice's own time stands in for it.
            }
        }

        return $old->latestOccurredAt;
    }
}
