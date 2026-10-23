<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Access;

defined('ABSPATH') || exit;

use Corex\Access\AbilityNames;
use Corex\Access\AccessUserDirectory;
use Corex\Access\CorexAbility;
use Corex\Access\CorexAbilityCatalog;

/**
 * What the Access screen is sent about an approval it is about to confirm, and about the grants
 * that stand (spec 112, FR-002, FR-004, FR-005, FR-007): people by name, abilities by label with
 * their risk, dates as canonical UTC for the spec 076 date contract.
 */
final class AccessGrantPresenter
{
    public function __construct(
        private readonly AccessGrantService $grants,
        private readonly AccessUserDirectory $users,
        private readonly CorexAbilityCatalog $catalog,
    ) {
    }

    /** @return array<string,mixed> */
    public function preview(AccessApprovalPreview $preview): array
    {
        return [
            'request_id'          => $preview->requestId,
            'requester'           => $this->person($preview->requesterId),
            'reason'              => $preview->reason,
            'area'                => $preview->areaKey,
            'area_label'          => $preview->areaKey !== null ? AbilityNames::area($preview->areaKey) : null,
            'abilities'           => array_map($this->ability(...), $preview->abilities),
            'blockers'            => $preview->blockers,
            'requires_typed_name' => $preview->typedName !== null,
            'typed_name'          => $preview->typedName,
            'expiry_choices'      => AccessService::GRANT_EXPIRY_DAYS,
            'expiry_default'      => 30,
            'target_hash'         => $preview->targetHash,
        ];
    }

    /** @return list<array<string,mixed>> */
    public function grants(): array
    {
        return array_map(function (array $standing): array {
            $grant = $standing['grant'];

            return [
                'id'          => (int) $grant['id'],
                'person'      => $this->person((int) $grant['requesterId']),
                'area'        => $grant['areaKey'],
                'area_label'  => $grant['areaKey'] !== null ? AbilityNames::area((string) $grant['areaKey']) : null,
                'abilities'   => array_map(
                    fn (string $key): array => $this->ability($this->catalog->find($key)),
                    $standing['abilities'],
                ),
                'approver'    => isset($grant['reviewerId']) ? $this->person((int) $grant['reviewerId']) : null,
                'approved_at' => $grant['reviewedAt']?->format(DATE_ATOM),
                'note'        => (string) ($grant['reviewNote'] ?? ''),
                // Null for a grant approved before expiries existed: it stands until revoked.
                'expires_at'  => $grant['grantExpiresAt']?->format(DATE_ATOM),
                'in_effect'   => $standing['inEffect'],
                'state_note'  => $standing['inEffect'] ? null : AccessGrantService::NOT_HELD,
            ];
        }, $this->grants->grants());
    }

    /** @return array{id:int,name:string} */
    private function person(int $userId): array
    {
        return ['id' => $userId, 'name' => $this->users->displayName($userId)];
    }

    /** @return array{key:string,label:string,risk:string,risk_label:string} */
    private function ability(CorexAbility $ability): array
    {
        return [
            'key'        => $ability->key,
            'label'      => AbilityNames::labelOf($ability),
            'risk'       => $ability->risk,
            'risk_label' => AbilityNames::risk($ability->risk),
        ];
    }
}
