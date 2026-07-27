<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Access;

defined('ABSPATH') || exit;

use Corex\Access\AccessRequestStore;
use Corex\Access\AccessUserDirectory;
use Corex\Access\CorexAbilityCatalog;

/**
 * Open access requests, shaped for an administrator to act on (spec 079).
 *
 * Before this, `AccessRequestStore::pending()` had no production caller: the REST route returned a
 * hardcoded empty array and the Access screen handed the same empty array to its React app. A
 * request was created, audited and notified, and then no surface in the product ever read the
 * table — while the denied screen told the requester an administrator would review it. See
 * `specs/079-admin-errors-access-request/evidence/before/requests-go-nowhere.md`.
 *
 * One presenter for both callers, so the screen and the API cannot disagree about what a pending
 * request is.
 */
final class PendingAccessRequests
{
    public function __construct(
        private readonly AccessRequestStore $requests,
        private readonly AccessUserDirectory $users,
        private readonly CorexAbilityCatalog $catalog,
    ) {
    }

    /**
     * @return list<array{
     *     id:int,requester:string,ability:string,area:string,reason:string,requested_at:string
     * }>
     */
    public function all(): array
    {
        return array_map($this->present(...), $this->requests->pending());
    }

    /**
     * @param  array<string,mixed> $row
     * @return array{id:int,requester:string,ability:string,area:string,reason:string,requested_at:string}
     */
    private function present(array $row): array
    {
        $abilityKey = $row['abilityKey'] !== null ? (string) $row['abilityKey'] : '';
        $ability    = $abilityKey === '' ? null : $this->catalog->find($abilityKey);

        return [
            'id'        => (int) $row['id'],
            'requester' => $this->users->displayName((int) $row['requesterId']),
            // The label an administrator recognises, falling back to the key rather than to an
            // empty cell: an unknown ability still has to be decidable.
            'ability' => $ability?->label ?? $abilityKey,
            'area'    => $row['areaKey'] !== null ? (string) $row['areaKey'] : '',
            'reason'  => (string) $row['reason'],
            // Canonical UTC ISO 8601, formatted in the browser by the spec 076 contract. The screen
            // decides how a date reads; the transport does not.
            'requested_at' => $row['createdAt']->format(DATE_ATOM),
        ];
    }
}
