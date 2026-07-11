<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * Applies login rate limiting without storing raw credentials or raw IP addresses.
 */
final class LoginProtectionService
{
    public function __construct(
        private readonly LoginProtectionPolicy $policy,
        private readonly LoginAttemptStore $store,
    ) {
    }

    public function assess(LoginProtectionContext $context, DateTimeImmutable $now): LoginProtectionDecision
    {
        if (! $this->policy->settings->enabled) {
            return new LoginProtectionDecision(false, 'disabled');
        }

        $lockout = $this->store->latestLockout(
            $this->policy->identityHash($context),
            $this->policy->networkHash($context),
            $now,
        );

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

        $identityHash = $this->policy->identityHash($context);
        $networkHash = $this->policy->networkHash($context);
        $recent = $this->store->failures(
            $identityHash,
            $networkHash,
            $now->modify('-' . $this->policy->settings->windowSeconds . ' seconds'),
        );

        if (count($recent) + 1 >= $this->policy->settings->threshold) {
            $lockedUntil = $now->modify('+' . $this->policy->settings->lockoutSeconds . ' seconds');
            $this->store->record(new LoginAttemptRecord(
                identityHash: $identityHash,
                networkHash: $networkHash,
                outcome: LoginAttemptRecord::LOCKED,
                reasonCode: 'threshold_exceeded',
                userId: null,
                occurredAt: $now,
                retentionUntil: $this->policy->retentionUntil($now),
                lockedUntil: $lockedUntil,
            ));

            return new LoginProtectionDecision(true, 'threshold_exceeded', $lockedUntil);
        }

        $this->store->record(new LoginAttemptRecord(
            identityHash: $identityHash,
            networkHash: $networkHash,
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
