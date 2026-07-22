<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Notifications;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * How a notification is targeted, and the single predicate that decides whether the current actor
 * may see it. Every read, count, and badge re-checks {@see canBeSeenBy()} (spec 072 FR-002/FR-003) —
 * knowing a notification's id grants nothing. Targeting is by user or ability, never by hard-coded
 * WordPress role (FR-006).
 */
final class NotificationRecipient
{
    public const KIND_USER            = 'user';
    public const KIND_USERS           = 'users';
    public const KIND_ABILITY         = 'ability';
    public const KIND_ASSIGNED        = 'assigned';
    public const KIND_CATEGORY_ADMINS = 'category_admins';

    /**
     * @param list<int>  $userIds     For user/users/assigned kinds — the explicitly targeted users.
     * @param string|null $ability    For ability/assigned/category_admins — the ability that grants sight.
     * @param string|null $sourceType For assigned — the source object type (e.g. `submission`).
     * @param string|null $sourceId   For assigned — the source object id.
     */
    private function __construct(
        public readonly string $kind,
        public readonly array $userIds,
        public readonly ?string $ability,
        public readonly ?string $sourceType = null,
        public readonly ?string $sourceId = null,
    ) {
        if (! in_array($kind, [self::KIND_USER, self::KIND_USERS, self::KIND_ABILITY, self::KIND_ASSIGNED, self::KIND_CATEGORY_ADMINS], true)) {
            throw new InvalidArgumentException('Unknown notification recipient kind: ' . $kind);
        }
    }

    public static function forUser(int $userId): self
    {
        return new self(self::KIND_USER, [$userId], null);
    }

    /** @param list<int> $userIds */
    public static function forUsers(array $userIds): self
    {
        return new self(self::KIND_USERS, array_values(array_unique(array_map('intval', $userIds))), null);
    }

    public static function forAbility(string $ability): self
    {
        return new self(self::KIND_ABILITY, [], $ability);
    }

    public static function forAssigned(string $sourceType, string $sourceId, int $assigneeId, string $managerAbility): self
    {
        return new self(self::KIND_ASSIGNED, [$assigneeId], $managerAbility, $sourceType, $sourceId);
    }

    public static function forCategoryAdmins(string $ability): self
    {
        return new self(self::KIND_CATEGORY_ADMINS, [], $ability);
    }

    /**
     * The one visibility decision. `$userCan` is the actor's capability check (e.g. a closure over
     * `current_user_can`), injected so this stays pure and testable.
     *
     * @param callable(string):bool $userCan
     */
    public function canBeSeenBy(int $userId, callable $userCan): bool
    {
        return match ($this->kind) {
            self::KIND_USER, self::KIND_USERS => in_array($userId, $this->userIds, true),
            self::KIND_ABILITY, self::KIND_CATEGORY_ADMINS => $this->ability !== null && $userCan($this->ability),
            self::KIND_ASSIGNED => in_array($userId, $this->userIds, true)
                || ($this->ability !== null && $userCan($this->ability)),
            default => false, // fail closed
        };
    }

    /**
     * Whether this notification names `$userId` personally, rather than reaching them through an
     * ability they happen to hold.
     *
     * Narrower than {@see canBeSeenBy()} on purpose: an ability-targeted notification is visible to
     * every holder of that ability but is nobody's personally, so the "assigned to me" view must not
     * use visibility as its filter or it would just repeat the inbox for any manager.
     */
    public function targetsUserDirectly(int $userId): bool
    {
        return match ($this->kind) {
            self::KIND_USER, self::KIND_USERS, self::KIND_ASSIGNED => in_array($userId, $this->userIds, true),
            default => false,
        };
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'kind'        => $this->kind,
            'user_ids'    => $this->userIds,
            'ability'     => $this->ability,
            'source_type' => $this->sourceType,
            'source_id'   => $this->sourceId,
        ];
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        $userIds = array_values(array_map('intval', (array) ($data['user_ids'] ?? [])));

        return new self(
            (string) ($data['kind'] ?? self::KIND_USER),
            $userIds,
            isset($data['ability']) ? (string) $data['ability'] : null,
            isset($data['source_type']) ? (string) $data['source_type'] : null,
            isset($data['source_id']) ? (string) $data['source_id'] : null,
        );
    }
}
