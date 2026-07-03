<?php

/**
 * Unit tests for access grant/request orchestration (spec 068: FR-084–FR-092).
 *
 * @package Corex\Tests\Unit\Access
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Access\AccessPolicy;
use Corex\Access\AccessRequestStore;
use Corex\Access\AccessUserDirectory;
use Corex\Access\CorexAbility;
use Corex\Access\CorexAbilityCatalog;
use Corex\Access\RoleAbilityStore;
use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityRepository;
use Corex\Activity\ActivityService;
use Corex\Config\Access\AccessService;
use Corex\Operations\Confirmation;
use Corex\Operations\OperationResult;

beforeEach(function () {
    Functions\when('__')->returnArg();

    $this->roles = new class implements RoleAbilityStore {
        /** @var array<string,array<string,string>> */
        public array $effects = [];

        public function effectsForRole(string $roleKey): array
        {
            return $this->effects[$roleKey] ?? [];
        }

        public function apply(string $roleKey, array $changes, int $actorId, DateTimeImmutable $updatedAt): void
        {
            foreach ($changes as $ability => $effect) {
                if ($effect === AccessPolicy::EFFECT_INHERIT) {
                    unset($this->effects[$roleKey][$ability]);
                } else {
                    $this->effects[$roleKey][$ability] = $effect;
                }
            }
        }
    };

    $this->requests = new class implements AccessRequestStore {
        /** @var array<int,array<string,mixed>> */
        public array $rows = [];

        public function create(int $requesterId, ?string $abilityKey, ?string $areaKey, string $reason, DateTimeImmutable $createdAt, DateTimeImmutable $expiresAt): int
        {
            $id = count($this->rows) + 1;
            $this->rows[$id] = compact('id', 'requesterId', 'abilityKey', 'areaKey', 'reason', 'createdAt', 'expiresAt') + [
                'state' => 'pending',
            ];

            return $id;
        }

        public function find(int $id): ?array
        {
            return $this->rows[$id] ?? null;
        }

        public function transition(int $id, string $state, int $reviewerId, string $note, DateTimeImmutable $reviewedAt): bool
        {
            if (($this->rows[$id]['state'] ?? null) !== 'pending') {
                return false;
            }

            $this->rows[$id]['state'] = $state;

            return true;
        }

        public function pending(): array
        {
            return array_values(array_filter($this->rows, static fn (array $row): bool => $row['state'] === 'pending'));
        }
    };

    $this->users = new class implements AccessUserDirectory {
        /** @var array<string,list<int>> */
        public array $roles = ['editor' => [12, 14]];
        /** @var list<int> */
        public array $admins = [7, 9];
        /** @var array<int,list<string>> */
        public array $granted = [];

        public function userIdsForRole(string $roleKey): array
        {
            return $this->roles[$roleKey] ?? [];
        }

        public function fullAccessAdministratorIds(): array
        {
            return $this->admins;
        }

        public function grantUserAbility(int $userId, string $abilityKey): void
        {
            $this->granted[$userId][] = $abilityKey;
        }

        public function displayName(int $userId): string
        {
            return 'User ' . $userId;
        }
    };

    $this->activityRepository = new class implements ActivityRepository {
        /** @var list<ActivityEvent> */
        public array $events = [];

        public function append(ActivityEvent $event): ActivityEvent
        {
            $event = $event->withId(count($this->events) + 1);
            $this->events[] = $event;

            return $event;
        }

        public function find(int $id): ?ActivityEvent
        {
            return $this->events[$id - 1] ?? null;
        }

        public function query(array $filters = [], int $page = 1, int $perPage = 20): array
        {
            return $this->events;
        }

        public function pruneExpired(DateTimeImmutable $now, int $limit = 500): int
        {
            return 0;
        }
    };

    $catalog = CorexAbilityCatalog::defaults();
    $this->service = new AccessService(
        catalog: $catalog,
        policy: new AccessPolicy($catalog),
        roleAbilities: $this->roles,
        requests: $this->requests,
        users: $this->users,
        activity: new ActivityService($this->activityRepository),
    );
});

it('applies a confirmed role ability change and records activity', function () {
    $now     = new DateTimeImmutable('2026-07-03T10:00:00+00:00');
    $changes = [CorexAbility::MANAGE_FORMS => AccessPolicy::EFFECT_ALLOW];
    $confirm = new Confirmation(
        operationKind: AccessService::ROLE_CHANGE_OPERATION,
        targetHash: $this->service->roleChangeHash('editor', $changes),
        actorId: 7,
        expiresAt: $now->modify('+5 minutes'),
    );

    $result = $this->service->changeRoleAbilities(7, 'editor', $changes, $confirm, $now);

    expect($result->state)->toBe(OperationResult::STATE_COMPLETED)
        ->and($this->roles->effects['editor'])->toBe($changes)
        ->and($this->activityRepository->events)->toHaveCount(1)
        ->and($this->activityRepository->events[0]->kind)->toBe('access.role.changed');
});

it('leaves grants unchanged when confirmation does not match', function () {
    $now     = new DateTimeImmutable('2026-07-03T10:00:00+00:00');
    $changes = [CorexAbility::MANAGE_FORMS => AccessPolicy::EFFECT_ALLOW];
    $confirm = new Confirmation(
        operationKind: AccessService::ROLE_CHANGE_OPERATION,
        targetHash: hash('sha256', 'different-target'),
        actorId: 7,
        expiresAt: $now->modify('+5 minutes'),
    );

    $result = $this->service->changeRoleAbilities(7, 'editor', $changes, $confirm, $now);

    expect($result->state)->toBe(OperationResult::STATE_BLOCKED)
        ->and($this->roles->effects)->toBe([]);
});

it('creates and approves an ability request exactly once', function () {
    $now = new DateTimeImmutable('2026-07-03T10:00:00+00:00');

    $created = $this->service->requestAccess(
        requesterId: 12,
        abilityKey: CorexAbility::MANAGE_FORMS,
        areaKey: null,
        reason: 'I maintain the registration flow.',
        now: $now,
        expiresAt: $now->modify('+7 days'),
    );
    $approved = $this->service->decideRequest(7, 1, true, 'Approved for form ownership.', $now->modify('+1 minute'));
    $replayed = $this->service->decideRequest(7, 1, false, 'Changed mind.', $now->modify('+2 minutes'));

    expect($created->state)->toBe(OperationResult::STATE_COMPLETED)
        ->and($approved->state)->toBe(OperationResult::STATE_COMPLETED)
        ->and($this->users->granted[12])->toBe([CorexAbility::MANAGE_FORMS])
        ->and($replayed->state)->toBe(OperationResult::STATE_BLOCKED);
});

it('denies a request without granting an ability', function () {
    $now = new DateTimeImmutable('2026-07-03T10:00:00+00:00');
    $this->service->requestAccess(12, CorexAbility::MANAGE_DATA, null, 'Need exports.', $now, $now->modify('+7 days'));

    $result = $this->service->decideRequest(7, 1, false, 'Exports remain restricted.', $now->modify('+1 minute'));

    expect($result->state)->toBe(OperationResult::STATE_COMPLETED)
        ->and($this->users->granted)->toBe([])
        ->and($this->requests->rows[1]['state'])->toBe('denied');
});
