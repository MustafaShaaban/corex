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
use Corex\Access\RequestableAbilities;
use Corex\Access\RoleAbilityStore;
use Corex\Access\SiteAdministration;
use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityRepository;
use Corex\Activity\ActivityService;
use Corex\Config\Access\AccessService;
use Corex\Operations\Confirmation;
use Corex\Operations\OperationResult;
use Corex\Mail\MailResult;
use Corex\Mail\RoutedMailer;

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

        public function createUnlessPending(int $requesterId, ?string $abilityKey, ?string $areaKey, string $reason, DateTimeImmutable $createdAt, DateTimeImmutable $expiresAt): ?int
        {
            if ($this->pendingFor($requesterId, $abilityKey, $areaKey) !== null) {
                return null;
            }

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

        public function pendingFor(int $requesterId, ?string $abilityKey, ?string $areaKey): ?array
        {
            foreach (array_reverse($this->rows, true) as $row) {
                if (
                    $row['state'] === 'pending'
                    && $row['requesterId'] === $requesterId
                    && $row['abilityKey'] === $abilityKey
                    && $row['areaKey'] === $areaKey
                ) {
                    return $row;
                }
            }

            return null;
        }

        public function approve(int $id, int $reviewerId, string $note, DateTimeImmutable $reviewedAt, DateTimeImmutable $grantExpiresAt): bool
        {
            if (! $this->transition($id, 'approved', $reviewerId, $note, $reviewedAt)) {
                return false;
            }

            $this->rows[$id] += ['reviewNote' => $note, 'grantExpiresAt' => $grantExpiresAt, 'endedAt' => null];

            return true;
        }

        public function grants(): array
        {
            return array_values(array_filter($this->rows, static fn (array $row): bool => $row['state'] === 'approved' && ($row['endedAt'] ?? null) === null));
        }

        public function endGrant(int $id, string $reason, ?int $endedBy, DateTimeImmutable $endedAt): bool
        {
            return false;
        }

        public function expiredGrants(DateTimeImmutable $now): array
        {
            return [];
        }

        public function cancel(int $id, string $reason, DateTimeImmutable $cancelledAt): bool
        {
            if (($this->rows[$id]['state'] ?? null) !== 'pending') {
                return false;
            }

            $this->rows[$id]['state']        = 'cancelled';
            $this->rows[$id]['cancelReason'] = $reason;

            return true;
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

        public function revokeUserAbility(int $userId, string $abilityKey): void
        {
            $this->granted[$userId] = array_values(array_diff($this->granted[$userId] ?? [], [$abilityKey]));
        }

        public function holdsOwnAbility(int $userId, string $abilityKey): bool
        {
            return in_array($abilityKey, $this->granted[$userId] ?? [], true);
        }

        public function displayName(int $userId): string
        {
            return 'User ' . $userId;
        }

        public function emailAddress(int $userId): string
        {
            return sprintf('user-%d@example.com', $userId);
        }

        public function exists(int $userId): bool
        {
            return true;
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

    $this->notifications = new class implements RoutedMailer {
        /** @var list<array{trigger:string,context:array<string,mixed>}> */
        public array $calls = [];

        public function dispatch(string $trigger, array $context): ?MailResult
        {
            $this->calls[] = compact('trigger', 'context');

            return null;
        }
    };
    // Users 7 and 9 administer the site, as do the members of the role `administrator`.
    $this->siteAdmins = new class implements SiteAdministration {
        /** @var list<int> */
        public array $userIds = [7, 9];

        public function roleAdministersSite(string $roleKey): bool
        {
            return $roleKey === 'administrator';
        }

        public function userAdministersSite(int $userId): bool
        {
            return in_array($userId, $this->userIds, true);
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
        requestable: new RequestableAbilities($catalog),
        notifications: $this->notifications,
        siteAdministration: $this->siteAdmins,
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
    $approved = $this->service->approveRequest(7, 1, 'Approved for form ownership.', 30, approvalConfirmation($this->service, 1, $now), $now->modify('+1 minute'));
    $replayed = $this->service->denyRequest(7, 1, 'Changed mind.', $now->modify('+2 minutes'));

    expect($created->state)->toBe(OperationResult::STATE_COMPLETED)
        ->and($approved->state)->toBe(OperationResult::STATE_COMPLETED)
        ->and($this->users->granted[12])->toBe([CorexAbility::MANAGE_FORMS])
        ->and($replayed->state)->toBe(OperationResult::STATE_BLOCKED)
        ->and(array_column($this->notifications->calls, 'trigger'))->toBe([
            'access.request.created',
            'access.request.approved',
        ])
        ->and($this->notifications->calls[1]['context']['requester']['email'])->toBe('user-12@example.com');
});

it('denies a request without granting an ability', function () {
    $now = new DateTimeImmutable('2026-07-03T10:00:00+00:00');
    $this->service->requestAccess(12, CorexAbility::MANAGE_DATA, null, 'Need exports.', $now, $now->modify('+7 days'));

    $result = $this->service->denyRequest(7, 1, 'Exports remain restricted.', $now->modify('+1 minute'));

    expect($result->state)->toBe(OperationResult::STATE_COMPLETED)
        ->and($this->users->granted)->toBe([])
        ->and($this->requests->rows[1]['state'])->toBe('denied');
});

it('refuses a second request for what the person is already waiting on, and tells nobody', function () {
    $now = new DateTimeImmutable('2026-07-03T10:00:00+00:00');

    $first  = $this->service->requestAccess(12, CorexAbility::MANAGE_FORMS, null, 'I maintain the registration flow.', $now, $now->modify('+7 days'));
    $second = $this->service->requestAccess(12, CorexAbility::MANAGE_FORMS, null, 'Asking again.', $now->modify('+1 minute'), $now->modify('+7 days'));

    expect($first->state)->toBe(OperationResult::STATE_COMPLETED)
        ->and($second->state)->toBe(OperationResult::STATE_BLOCKED)
        ->and(array_column($second->errors, 'code'))->toBe(['request_already_pending'])
        ->and($second->affectedIds)->toBe([])
        // The audit log does not say a request was created, and the administrators are not
        // written to a second time.
        ->and($this->activityRepository->events)->toHaveCount(1)
        ->and($this->notifications->calls)->toHaveCount(1);
});


/**
 * The confirmation the approval dialog builds from a preview: for this approver, bound to the
 * request and what it grants, carrying what was typed.
 */
function approvalConfirmation(AccessService $service, int $requestId, DateTimeImmutable $now, ?string $typed = null, int $actorId = 7): Confirmation
{
    $preview = $service->previewApproval($actorId, $requestId);

    return new Confirmation(
        operationKind: AccessService::APPROVAL_OPERATION,
        targetHash: $preview->targetHash,
        actorId: $actorId,
        expiresAt: $now->modify('+10 minutes'),
        requiredPhrase: $typed,
    );
}

it('shows what an approval grants: each ability with its risk, and whether its name must be typed', function () {
    $now = new DateTimeImmutable('2026-07-03T10:00:00+00:00');
    $this->service->requestAccess(12, null, CorexAbility::GROUP_DATA, 'I run the exports.', $now, $now->modify('+7 days'));

    $preview = $this->service->previewApproval(7, 1);

    expect($preview->requesterId)->toBe(12)
        ->and($preview->reason)->toBe('I run the exports.')
        ->and(array_map(static fn (CorexAbility $ability): array => [$ability->key, $ability->risk], $preview->abilities))->toBe([
            [CorexAbility::MANAGE_DATA, CorexAbility::RISK_DANGEROUS],
            [CorexAbility::EXPORT_PERSONAL_DATA, CorexAbility::RISK_SENSITIVE],
        ])
        ->and($preview->blockers)->toBe([])
        ->and($preview->typedName)->toBe(CorexAbility::MANAGE_DATA);
});

it('asks no typed name when nothing granted is dangerous', function () {
    $now = new DateTimeImmutable('2026-07-03T10:00:00+00:00');
    $this->service->requestAccess(12, CorexAbility::MANAGE_FORMS, null, 'Forms.', $now, $now->modify('+7 days'));

    expect($this->service->previewApproval(7, 1)->typedName)->toBeNull();
});

it('refuses an approval without a note or with an expiry it does not offer, and grants nothing', function (string $note, int $days, string $code) {
    $now = new DateTimeImmutable('2026-07-03T10:00:00+00:00');
    $this->service->requestAccess(12, CorexAbility::MANAGE_FORMS, null, 'Forms.', $now, $now->modify('+7 days'));

    $result = $this->service->approveRequest(7, 1, $note, $days, approvalConfirmation($this->service, 1, $now), $now);

    expect($result->state)->toBe(OperationResult::STATE_BLOCKED)
        ->and(array_column($result->errors, 'code'))->toBe([$code])
        ->and($this->users->granted)->toBe([])
        ->and($this->requests->rows[1]['state'])->toBe('pending');
})->with([
    'no note'            => ['  ', 30, 'note_required'],
    'a 14-day expiry'    => ['Covering forms.', 14, 'expiry_invalid'],
]);

it('approves with each expiry it offers, and keeps when the grant ends', function (int $days) {
    $now = new DateTimeImmutable('2026-07-03T10:00:00+00:00');
    $this->service->requestAccess(12, CorexAbility::MANAGE_FORMS, null, 'Forms.', $now, $now->modify('+7 days'));

    $result = $this->service->approveRequest(7, 1, 'Covering forms.', $days, approvalConfirmation($this->service, 1, $now), $now);

    expect($result->state)->toBe(OperationResult::STATE_COMPLETED)
        ->and($this->requests->rows[1]['grantExpiresAt'])->toEqual($now->modify(sprintf('+%d days', $days)));
})->with(AccessService::GRANT_EXPIRY_DAYS);

it('approves a dangerous ability only when its name is typed, as the server checks it', function (?string $typed, string $state) {
    $now = new DateTimeImmutable('2026-07-03T10:00:00+00:00');
    $this->service->requestAccess(12, CorexAbility::MANAGE_DATA, null, 'Exports.', $now, $now->modify('+7 days'));

    $result = $this->service->approveRequest(7, 1, 'Exports this month.', 7, approvalConfirmation($this->service, 1, $now, $typed), $now);

    expect($result->state)->toBe($state)
        ->and($this->users->granted !== [])->toBe($state === OperationResult::STATE_COMPLETED);
})->with([
    'nothing typed'   => [null, OperationResult::STATE_BLOCKED],
    'the wrong name'  => [CorexAbility::MANAGE_FORMS, OperationResult::STATE_BLOCKED],
    'its name'        => [CorexAbility::MANAGE_DATA, OperationResult::STATE_COMPLETED],
]);

it('refuses an approval confirmed for another request', function () {
    $now = new DateTimeImmutable('2026-07-03T10:00:00+00:00');
    $this->service->requestAccess(12, CorexAbility::MANAGE_FORMS, null, 'Forms.', $now, $now->modify('+7 days'));
    $this->service->requestAccess(14, CorexAbility::MANAGE_EMAIL, null, 'Email.', $now, $now->modify('+7 days'));

    $result = $this->service->approveRequest(7, 1, 'Covering forms.', 30, approvalConfirmation($this->service, 2, $now), $now);

    expect(array_column($result->errors, 'code'))->toBe(['confirmation_invalid'])
        ->and($this->users->granted)->toBe([]);
});

it('cancels instead of approving a request for an ability a request can no longer ask for', function () {
    // Filed before spec 112, when a critical ability could still be asked for.
    $now = new DateTimeImmutable('2026-07-03T10:00:00+00:00');
    $this->requests->rows[1] = [
        'id' => 1, 'requesterId' => 12, 'abilityKey' => CorexAbility::MANAGE_ACCESS, 'areaKey' => null,
        'reason' => 'Old request.', 'state' => 'pending', 'createdAt' => $now, 'expiresAt' => $now->modify('+7 days'),
    ];

    $result = $this->service->approveRequest(7, 1, 'Yes.', 30, approvalConfirmation($this->service, 1, $now), $now);

    expect(array_column($result->errors, 'code'))->toBe(['request_not_requestable'])
        ->and($this->requests->rows[1]['state'])->toBe('cancelled')
        ->and($this->users->granted)->toBe([]);
});

it('refuses a Deny on a role that administers the site, names the ability and records the refusal', function () {
    $service = $this->service;
    $now     = new DateTimeImmutable('2026-10-23T10:00:00+00:00');
    $changes = [CorexAbility::MANAGE_EMAIL => AccessPolicy::EFFECT_DENY];
    $confirm = new Confirmation(
        operationKind: AccessService::ROLE_CHANGE_OPERATION,
        targetHash: $service->roleChangeHash('administrator', $changes),
        actorId: 7,
        expiresAt: $now->modify('+5 minutes'),
    );

    $result = $service->changeRoleAbilities(7, 'administrator', $changes, $confirm, $now);
    $event  = $this->activityRepository->events[0] ?? null;

    expect($result->state)->toBe(OperationResult::STATE_BLOCKED)
        ->and($result->errors[0]['code'])->toBe('site_administrator_role')
        ->and($result->errors[0]['message'])->toContain('“Manage Email Studio”')
        ->and($result->errors[0]['message'])->not->toContain('corex_')
        ->and($this->roles->effects)->toBe([])
        ->and($event?->kind)->toBe('access.role.refused')
        ->and($event?->context['blockers'])->toBe([['code' => 'site_administrator_role', 'ability' => CorexAbility::MANAGE_EMAIL]]);
});

it('says why a locked ability cannot be changed in words', function () {
    expect($this->service->refusalReason(['code' => 'ability_locked', 'ability' => CorexAbility::MANAGE_ADMIN]))
        ->toBe('“Access CoreX admin” cannot be changed: it comes only with site administration.');
});

it('records a request that ran out when somebody tried to decide it', function () {
    $now = new DateTimeImmutable('2026-10-23T10:00:00+00:00');
    $this->service->requestAccess(12, CorexAbility::MANAGE_FORMS, null, 'Need it.', $now->modify('-8 days'), $now->modify('-1 day'));

    $result = $this->service->denyRequest(7, 1, 'Too late', $now);
    $kinds  = array_map(static fn (ActivityEvent $event): string => $event->kind, $this->activityRepository->events);

    expect($result->errors[0]['code'])->toBe('request_expired')
        ->and($kinds)->toBe(['access.request.created', 'access.request.expired'])
        ->and($this->activityRepository->events[1]->context['requester_id'])->toBe(12);
});

/*
 * DECISIONS #428. User 20 holds "Manage access" and does not administer the site; user 12 is an
 * editor; users 7 and 9 administer the site.
 */
function roleChangeConfirmation(AccessService $service, string $role, array $changes, int $actorId, DateTimeImmutable $now): Confirmation
{
    return new Confirmation(
        operationKind: AccessService::ROLE_CHANGE_OPERATION,
        targetHash: $service->roleChangeHash($role, $changes),
        actorId: $actorId,
        expiresAt: $now->modify('+5 minutes'),
    );
}

it('refuses a Critical Allow by someone who does not administer the site, says why and records it', function () {
    $now     = new DateTimeImmutable('2026-10-23T10:00:00+00:00');
    $changes = [CorexAbility::MANAGE_ACCESS => AccessPolicy::EFFECT_ALLOW];

    $result = $this->service->changeRoleAbilities(20, 'editor', $changes, roleChangeConfirmation($this->service, 'editor', $changes, 20, $now), $now);
    $event  = $this->activityRepository->events[0] ?? null;

    expect($result->state)->toBe(OperationResult::STATE_BLOCKED)
        ->and($result->errors[0]['code'])->toBe(AccessPolicy::CRITICAL_ALLOW)
        ->and($result->errors[0]['message'])->toBe('“Manage access” is a Critical ability: only a site administrator can allow it or set it to inherit.')
        ->and($this->roles->effects)->toBe([])
        ->and($event?->kind)->toBe('access.role.refused')
        ->and($event?->actorId)->toBe(20);
});

it('refuses a change to a role the person making it holds, when they do not administer the site', function () {
    $now     = new DateTimeImmutable('2026-10-23T10:00:00+00:00');
    $changes = [CorexAbility::MANAGE_FORMS => AccessPolicy::EFFECT_ALLOW];

    $result = $this->service->changeRoleAbilities(12, 'editor', $changes, roleChangeConfirmation($this->service, 'editor', $changes, 12, $now), $now);

    expect($result->state)->toBe(OperationResult::STATE_BLOCKED)
        ->and(array_column($result->errors, 'code'))->toBe([AccessPolicy::OWN_ACCESS])
        ->and($this->roles->effects)->toBe([])
        ->and($this->activityRepository->events[0]->kind)->toBe('access.role.refused');
});

it('lets someone who does not administer the site change abilities that are not Critical, and deny a Critical one, on a role they do not hold', function () {
    $now     = new DateTimeImmutable('2026-10-23T10:00:00+00:00');
    $changes = [CorexAbility::MANAGE_DATA => AccessPolicy::EFFECT_ALLOW, CorexAbility::MANAGE_OPERATIONS => AccessPolicy::EFFECT_DENY];

    $result = $this->service->changeRoleAbilities(20, 'editor', $changes, roleChangeConfirmation($this->service, 'editor', $changes, 20, $now), $now);

    expect($result->state)->toBe(OperationResult::STATE_COMPLETED)
        ->and($this->roles->effects['editor'])->toBe($changes);
});

it('lets a site administrator allow a Critical ability on a role they hold', function () {
    $now = new DateTimeImmutable('2026-10-23T10:00:00+00:00');
    $this->users->roles['editor'][] = 7;
    $changes = [CorexAbility::MANAGE_OPERATIONS => AccessPolicy::EFFECT_ALLOW];

    $result = $this->service->changeRoleAbilities(7, 'editor', $changes, roleChangeConfirmation($this->service, 'editor', $changes, 7, $now), $now);

    expect($result->state)->toBe(OperationResult::STATE_COMPLETED)
        ->and($this->roles->effects['editor'])->toBe($changes);
});

it('refuses a person who does not administer the site to approve or deny their own request, and records each', function () {
    $now = new DateTimeImmutable('2026-10-23T10:00:00+00:00');
    $this->service->requestAccess(20, CorexAbility::MANAGE_FORMS, null, 'Mine.', $now, $now->modify('+7 days'));

    $approved = $this->service->approveRequest(20, 1, 'Self.', 30, approvalConfirmation($this->service, 1, $now, actorId: 20), $now);
    $denied   = $this->service->denyRequest(20, 1, 'Self.', $now);
    $refusals = array_values(array_filter(
        $this->activityRepository->events,
        static fn (ActivityEvent $event): bool => $event->kind === 'access.request.refused',
    ));

    expect(array_column($approved->errors, 'code'))->toBe([AccessService::OWN_REQUEST])
        ->and(array_column($denied->errors, 'code'))->toBe([AccessService::OWN_REQUEST])
        ->and($this->requests->rows[1]['state'])->toBe('pending')
        ->and($this->users->granted)->toBe([])
        ->and($refusals)->toHaveCount(2)
        ->and($refusals[0]->actorId)->toBe(20)
        ->and($refusals[0]->context['requester_id'])->toBe(20);
});

it('shows an approver who does not administer the site why their own request cannot be approved', function () {
    $now = new DateTimeImmutable('2026-10-23T10:00:00+00:00');
    $this->service->requestAccess(20, CorexAbility::MANAGE_FORMS, null, 'Mine.', $now, $now->modify('+7 days'));

    expect($this->service->previewApproval(20, 1)?->blockers)
        ->toBe([['code' => AccessPolicy::OWN_ACCESS, 'ability' => CorexAbility::MANAGE_FORMS]]);
});

it('lets a person who does not administer the site decide a request somebody else made', function () {
    $now = new DateTimeImmutable('2026-10-23T10:00:00+00:00');
    $this->service->requestAccess(12, CorexAbility::MANAGE_FORMS, null, 'I run the forms.', $now, $now->modify('+7 days'));

    $result = $this->service->approveRequest(20, 1, 'Yes.', 30, approvalConfirmation($this->service, 1, $now, actorId: 20), $now);

    expect($result->state)->toBe(OperationResult::STATE_COMPLETED)
        ->and($this->users->granted[12])->toBe([CorexAbility::MANAGE_FORMS]);
});

it('lets a site administrator decide their own request', function () {
    $now = new DateTimeImmutable('2026-10-23T10:00:00+00:00');
    $this->service->requestAccess(7, CorexAbility::MANAGE_FORMS, null, 'Mine.', $now, $now->modify('+7 days'));

    $result = $this->service->approveRequest(7, 1, 'Yes.', 30, approvalConfirmation($this->service, 1, $now), $now);

    expect($result->state)->toBe(OperationResult::STATE_COMPLETED);
});

it('shows a Critical ability in an approval as one only a site administrator grants', function () {
    $now = new DateTimeImmutable('2026-10-23T10:00:00+00:00');
    // Stored before requests stopped asking for it (spec 112, FR-008).
    $this->requests->rows[1] = [
        'id' => 1, 'requesterId' => 12, 'abilityKey' => CorexAbility::MANAGE_ACCESS, 'areaKey' => null,
        'reason' => 'Old request.', 'state' => 'pending', 'createdAt' => $now, 'expiresAt' => $now->modify('+7 days'),
    ];

    expect($this->service->previewApproval(20, 1)?->blockers)
        ->toBe([['code' => AccessPolicy::CRITICAL_ALLOW, 'ability' => CorexAbility::MANAGE_ACCESS]])
        ->and($this->service->previewApproval(7, 1)?->blockers)->toBe([]);
});

it('treats the person making a change as not administering the site when it has nobody to ask', function () {
    $catalog = CorexAbilityCatalog::defaults();
    $service = new AccessService(
        catalog: $catalog,
        policy: new AccessPolicy($catalog),
        roleAbilities: $this->roles,
        requests: $this->requests,
        users: $this->users,
        activity: new ActivityService($this->activityRepository),
        requestable: new RequestableAbilities($catalog),
    );

    expect($service->previewRoleChanges(7, 'editor', [CorexAbility::MANAGE_ACCESS => AccessPolicy::EFFECT_ALLOW])->allowed)->toBeFalse();
});
