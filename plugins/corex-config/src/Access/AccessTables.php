<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Access;

defined('ABSPATH') || exit;

use Corex\Database\Schema\ManagedTable;
use Corex\Database\Schema\Table;

/**
 * Managed persistence definitions for role grants and access requests.
 */
final class AccessTables
{
    public const ROLE_GRANTS = 'role_ability_grants';
    public const REQUESTS    = 'access_requests';

    /** @return list<Table> */
    public function schemas(): array
    {
        return [
            (new Table(self::ROLE_GRANTS))
                ->id()
                ->string('role_key', 64)
                ->string('ability_key', 128)
                ->string('effect', 20)
                ->string('source', 20)
                ->integer('updated_by')
                ->datetime('updated_at')
                ->index('role_ability_source', ['role_key', 'ability_key', 'source'], unique: true)
                ->index('role_ability_role', ['role_key'])
                ->index('role_ability_key', ['ability_key']),
            (new Table(self::REQUESTS))
                ->id()
                ->integer('requester_id')
                ->string('ability_key', 128, nullable: true)
                ->string('area_key', 64, nullable: true)
                ->text('reason')
                ->string('state', 20)
                ->integer('reviewer_id', nullable: true)
                ->text('review_note', nullable: true)
                ->datetime('reviewed_at', nullable: true)
                ->integer('notification_attempt_id', nullable: true)
                ->datetime('created_at')
                ->datetime('expires_at')
                ->index('access_request_state', ['state'])
                ->index('access_request_requester', ['requester_id'])
                ->index('access_request_expiry', ['expires_at']),
        ];
    }

    /** @return list<ManagedTable> */
    public function managed(): array
    {
        return [
            new ManagedTable(self::ROLE_GRANTS, 'Role ability grants', [
                ['id' => 'role_key', 'label' => 'Role'],
                ['id' => 'ability_key', 'label' => 'Ability'],
                ['id' => 'effect', 'label' => 'Effect'],
                ['id' => 'source', 'label' => 'Source'],
                ['id' => 'updated_at', 'label' => 'Updated'],
            ], 'corex'),
            new ManagedTable(self::REQUESTS, 'Access requests', [
                ['id' => 'requester_id', 'label' => 'Requester'],
                ['id' => 'ability_key', 'label' => 'Ability'],
                ['id' => 'area_key', 'label' => 'Area'],
                ['id' => 'state', 'label' => 'State'],
                ['id' => 'created_at', 'label' => 'Created'],
                ['id' => 'expires_at', 'label' => 'Expires'],
            ], 'corex'),
        ];
    }
}
