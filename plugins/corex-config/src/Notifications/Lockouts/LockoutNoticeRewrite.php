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
use RuntimeException;

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
 * merge key is its own (`security.lockout.legacy:<id>`, outside every key the old producer could
 * write), so it can never collide with another notice, and a new lockout opens a notice of its own.
 *
 * A notice whose write fails is skipped, the rest are rewritten, and the ones that failed are
 * reported together at the end. They are still in the old shape, so the next run takes them again.
 *
 * It runs as a data step of the product foundation's schema (version 5), in the scope of each site a
 * migration reaches. It reads in batches by id, so every row is read once, and it selects only notices
 * not yet rewritten, so a second run changes nothing.
 */
final class LockoutNoticeRewrite implements DataMigrationStep
{
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
        $failed = [];

        do {
            $batch = $this->notices->legacyAfter($afterId, $this->batchSize);
            foreach ($batch as $notice) {
                $afterId = $notice->id;
                try {
                    $this->notices->rewrite($notice->id, $this->rewritten($notice));
                } catch (RuntimeException) {
                    $failed[] = $notice->id;
                }
            }
        } while (count($batch) === $this->batchSize);

        if ($failed !== []) {
            throw new RuntimeException(sprintf(
                '%d lockout notices could not be rewritten (ids %s).',
                count($failed),
                implode(', ', $failed),
            ));
        }
    }

    private function rewritten(StoredLockoutNotice $old): LoginLockoutNotice
    {
        return LoginLockoutNotice::of(
            null,
            null,
            $this->policy->identityFingerprint($this->policy->legacyIdentityHashOf($this->typedUsername($old))),
            $this->network($old->metadata['client_ip'] ?? null),
            $this->lockedUntil($old),
        )->asLegacy($old->id);
    }

    /** What was typed, from where the old producer put it: the source id, else its merge key. */
    private function typedUsername(StoredLockoutNotice $old): string
    {
        if ($old->sourceId !== null && $old->sourceId !== '') {
            return $old->sourceId;
        }

        return str_starts_with($old->dedupKey, LoginLockoutNotice::OLD_KEY_PREFIX)
            ? substr($old->dedupKey, strlen(LoginLockoutNotice::OLD_KEY_PREFIX))
            : $old->dedupKey;
    }

    private function network(mixed $clientIp): string
    {
        if (! is_string($clientIp) || filter_var($clientIp, FILTER_VALIDATE_IP) === false) {
            return '';
        }

        return $this->policy->networkFingerprint($this->policy->legacyNetworkHashOf($clientIp));
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
