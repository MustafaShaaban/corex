<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

use Corex\Database\Schema\ManagedTable;
use Corex\Database\Schema\Table;

/**
 * Managed table for privacy-preserving login attempt evidence.
 */
final class LoginAttemptTable
{
    public const NAME = 'login_attempts';

    public function schema(): Table
    {
        return (new Table(self::NAME))
            ->id()
            ->datetime('occurred_at')
            ->string('identity_hash', 64)
            ->string('network_hash', 64)
            ->string('outcome', 16)
            ->string('reason_code', 64)
            ->integer('user_id', nullable: true)
            ->datetime('locked_until', nullable: true)
            ->datetime('retention_until')
            ->index('login_attempt_identity_network', ['identity_hash', 'network_hash'])
            ->index('login_attempt_outcome', ['outcome'])
            ->index('login_attempt_lockout', ['identity_hash', 'network_hash', 'outcome', 'locked_until'])
            ->index('login_attempt_retention', ['retention_until']);
    }

    public function managed(): ManagedTable
    {
        return new ManagedTable(self::NAME, 'Login attempts', [
            ['id' => 'occurred_at', 'label' => 'Occurred'],
            ['id' => 'outcome', 'label' => 'Outcome'],
            ['id' => 'reason_code', 'label' => 'Reason'],
            ['id' => 'user_id', 'label' => 'User'],
            ['id' => 'retention_until', 'label' => 'Retention until'],
        ], 'corex');
    }
}
