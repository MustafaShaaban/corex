<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

use Corex\Security\LoginLockoutEvent;
use DateTimeImmutable;

/**
 * Applies login rate limiting without storing raw credentials or raw IP addresses.
 *
 * An attempt is counted by keyed values (spec 124, FR-019), and the rows the release before this one
 * stored under plain hashes are counted with them until they leave the window. The account a username
 * belongs to is looked up once, on the way into a lockout, and kept on the lockout row (FR-016).
 */
final class LoginProtectionService
{
    public function __construct(
        private readonly LoginProtectionPolicy $policy,
        private readonly LoginAttemptStore $store,
        private readonly LoginAccounts $accounts,
    ) {
    }

    public function assess(LoginProtectionContext $context, DateTimeImmutable $now): LoginProtectionDecision
    {
        if (! $this->policy->settings->enabled) {
            return new LoginProtectionDecision(false, 'disabled');
        }

        $lockout = $this->store->latestLockout($this->policy->keys($context), $now);

        if ($lockout !== null) {
            return new LoginProtectionDecision(true, 'active_lockout', $lockout->lockedUntil);
        }

        return new LoginProtectionDecision(false, 'allowed');
    }

    public function recordFailure(LoginProtectionContext $context, DateTimeImmutable $now): LoginProtectionDecision
    {
        if (! $this->policy->settings->enabled) {
            return new LoginProtectionDecision(false, 'disabled');
        }

        $active = $this->assess($context, $now);
        if ($active->locked) {
            return $active;
        }

        $keys = $this->policy->keys($context);
        $recent = $this->store->failures(
            $keys,
            $now->modify('-' . $this->policy->settings->windowSeconds . ' seconds'),
        );

        if (count($recent) + 1 >= $this->policy->settings->threshold) {
            $lockedUntil = $now->modify('+' . $this->policy->settings->lockoutSeconds . ' seconds');
            $lockout = new LoginAttemptRecord(
                identityHash: $keys->identityHash,
                networkHash: $keys->networkHash,
                outcome: LoginAttemptRecord::LOCKED,
                reasonCode: 'threshold_exceeded',
                userId: $this->accounts->idFor($context->identity),
                occurredAt: $now,
                retentionUntil: $this->policy->retentionUntil($now),
                lockedUntil: $lockedUntil,
            );
            $this->store->record($lockout);

            return new LoginProtectionDecision(true, 'threshold_exceeded', $lockedUntil, new LoginLockoutEvent(
                $lockout->userId,
                $this->policy->identityFingerprint($lockout->identityHash),
                $this->policy->networkFingerprint($lockout->networkHash),
                $lockedUntil,
            ));
        }

        $this->store->record(new LoginAttemptRecord(
            identityHash: $keys->identityHash,
            networkHash: $keys->networkHash,
            outcome: LoginAttemptRecord::FAILED,
            reasonCode: 'invalid_credentials',
            userId: null,
            occurredAt: $now,
            retentionUntil: $this->policy->retentionUntil($now),
        ));

        return new LoginProtectionDecision(false, 'failed');
    }

    public function recordSuccess(LoginProtectionContext $context, int $userId, DateTimeImmutable $now): LoginProtectionDecision
    {
        if (! $this->policy->settings->enabled || ! $this->policy->settings->successfulLoginLogging) {
            return new LoginProtectionDecision(false, 'success_not_logged');
        }

        $this->store->record(new LoginAttemptRecord(
            identityHash: $this->policy->identityHash($context),
            networkHash: $this->policy->networkHash($context),
            outcome: LoginAttemptRecord::SUCCESS,
            reasonCode: 'authenticated',
            userId: $userId,
            occurredAt: $now,
            retentionUntil: $this->policy->retentionUntil($now),
        ));

        return new LoginProtectionDecision(false, 'success');
    }
}
