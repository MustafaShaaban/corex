<?php

/**
 * Unit tests for the pure Access & Abilities model (spec 067, spec 117). No WordPress.
 * Contract: every cell resolves the way WordPress will answer for the role's members; a role that
 * administers the site holds every CoreX ability; counts and "Your permissions" use CoreX abilities.
 *
 * @package Corex\Tests\Unit\Access
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Access\AccessPolicy;
use Corex\Access\CorexAbility;
use Corex\Access\CorexAbilityCatalog;
use Corex\Config\Access\AccessMatrix;

beforeEach(function () {
    Functions\when('__')->returnArg();
    $this->matrix = new AccessMatrix();
    $this->total  = count(CorexAbilityCatalog::defaults()->all());
});

/**
 * @return list<array{key:string,name:string,caps:array<string,bool>}>
 */
function accessRoles(): array
{
    return [
        ['key' => 'administrator', 'name' => 'Administrator', 'caps' => ['manage_options' => true, 'edit_posts' => true]],
        ['key' => 'editor', 'name' => 'Editor', 'caps' => ['edit_posts' => true, 'edit_others_posts' => true]],
        ['key' => 'subscriber', 'name' => 'Subscriber', 'caps' => ['read' => true]],
    ];
}

/** @return array<string,array<string,mixed>> the grid's rows by ability key */
function gridRows(AccessMatrix $matrix, array $effects): array
{
    return array_column($matrix->editableCorexMatrix(accessRoles(), $effects, [])['rows'], null, 'key');
}

it('resolves each cell to one of the five states', function () {
    $rows = gridRows($this->matrix, [
        'editor'     => [CorexAbility::MANAGE_FORMS => AccessPolicy::EFFECT_ALLOW],
        'subscriber' => [CorexAbility::MANAGE_BLOG => AccessPolicy::EFFECT_DENY],
    ]);

    expect($rows[CorexAbility::MANAGE_FORMS]['cells']['editor']['state'])->toBe(AccessMatrix::STATE_ALLOWED_SET)
        ->and($rows[CorexAbility::MANAGE_BLOG]['cells']['subscriber']['state'])->toBe(AccessMatrix::STATE_DENIED_SET)
        ->and($rows[CorexAbility::MANAGE_EMAIL]['cells']['editor']['state'])->toBe(AccessMatrix::STATE_NOT_GRANTED)
        ->and($rows[CorexAbility::MANAGE_ADMIN]['cells']['editor']['state'])->toBe(AccessMatrix::STATE_LOCKED)
        ->and($rows[CorexAbility::MANAGE_ADMIN]['cells']['administrator']['state'])->toBe(AccessMatrix::STATE_ALLOWED_ADMIN);
});

it('shows a role that administers the site as allowed everywhere, offers no edit, and flags a Deny stored there', function () {
    $rows = gridRows($this->matrix, ['administrator' => [CorexAbility::MANAGE_EMAIL => AccessPolicy::EFFECT_DENY]]);
    $cell = $rows[CorexAbility::MANAGE_EMAIL]['cells']['administrator'];

    expect($cell['state'])->toBe(AccessMatrix::STATE_ALLOWED_ADMIN)
        ->and($cell['editable'])->toBeFalse()
        ->and($cell['reason'])->toBe('site_administrator')
        ->and($cell['ignoredDeny'])->toBeTrue()
        ->and($rows[CorexAbility::MANAGE_FORMS]['cells']['administrator']['ignoredDeny'])->toBeFalse();
});

it('keeps an ordinary role editable except for the locked ability', function () {
    $rows = gridRows($this->matrix, []);

    expect($rows[CorexAbility::MANAGE_FORMS]['cells']['editor']['editable'])->toBeTrue()
        ->and($rows[CorexAbility::MANAGE_FORMS]['cells']['editor']['reason'])->toBeNull()
        ->and($rows[CorexAbility::MANAGE_ADMIN]['cells']['editor']['editable'])->toBeFalse()
        ->and($rows[CorexAbility::MANAGE_ADMIN]['cells']['editor']['reason'])->toBe('locked_definition');
});

it('names each row and its area for people, with the key kept for developers', function () {
    $row = gridRows($this->matrix, [])[CorexAbility::MANAGE_EMAIL];

    expect($row['label'])->toBe('Manage Email Studio')
        ->and($row['groupLabel'])->toBe('Email Studio')
        ->and($row['riskLabel'])->toBe('Sensitive')
        ->and($row['key'])->toBe(CorexAbility::MANAGE_EMAIL);
});

it('marks which roles administer the site', function () {
    $roles = array_column($this->matrix->editableCorexMatrix(accessRoles(), [], [])['roles'], 'administers', 'key');

    expect($roles)->toBe(['administrator' => true, 'editor' => false, 'subscriber' => false]);
});

it('counts the CoreX abilities each role holds, as the matrix resolves them', function () {
    $summaries = $this->matrix->roleSummaries(
        accessRoles(),
        ['administrator' => 2, 'editor' => 5],
        ['editor' => [CorexAbility::MANAGE_FORMS => AccessPolicy::EFFECT_ALLOW, CorexAbility::MANAGE_BLOG => AccessPolicy::EFFECT_ALLOW]],
    );

    expect($summaries[0]['granted'])->toBe($this->total)
        ->and($summaries[0]['total'])->toBe($this->total)
        ->and($summaries[0]['users'])->toBe(2)
        ->and($summaries[1]['granted'])->toBe(2)
        ->and($summaries[2]['granted'])->toBe(0)
        ->and($summaries[2]['users'])->toBe(0);
});

it('counts a role that administers the site as holding everything even with a Deny stored', function () {
    $summaries = $this->matrix->roleSummaries(
        [accessRoles()[0]],
        [],
        ['administrator' => [CorexAbility::MANAGE_EMAIL => AccessPolicy::EFFECT_DENY]],
    );

    expect($summaries[0]['granted'])->toBe($this->total);
});

it('flags a role WordPress does not ship', function () {
    $summaries = $this->matrix->roleSummaries(
        [['key' => 'shop_manager', 'name' => 'Shop manager', 'caps' => []]],
        [],
    );

    expect($summaries[0]['isCore'])->toBeFalse();
});

it('lists every CoreX ability by area with its risk in words', function () {
    $groups = $this->matrix->abilityGroups();
    $listed = array_merge(...array_values($groups));
    $admin  = array_column($listed, null, 'key')[CorexAbility::MANAGE_ADMIN];

    expect($listed)->toHaveCount($this->total)
        ->and(array_keys($groups))->toContain('Forms & Flows', 'Operations & Security')
        ->and($admin['riskLabel'])->toBe('Critical')
        ->and($admin['locked'])->toBeTrue();
});

it('lists each Deny stored for a role that administers the site, and only those', function () {
    $ignored = $this->matrix->ignoredDenies(accessRoles(), [
        'administrator' => [CorexAbility::MANAGE_EMAIL => AccessPolicy::EFFECT_DENY, CorexAbility::MANAGE_FORMS => AccessPolicy::EFFECT_ALLOW],
        'editor'        => [CorexAbility::MANAGE_BLOG => AccessPolicy::EFFECT_DENY],
    ]);

    expect($ignored)->toBe([['role' => 'Administrator', 'ability' => 'Manage Email Studio']]);
});

it('says how the person holds each CoreX ability they hold', function () {
    $held = [
        CorexAbility::MANAGE_FORMS  => true,
        CorexAbility::MANAGE_ACCESS => true,
        CorexAbility::MANAGE_BLOG   => true,
        CorexAbility::MANAGE_EMAIL  => false,
    ];

    $mine = array_column($this->matrix->forUser(
        $held,
        [accessRoles()[1]],
        ['editor' => [CorexAbility::MANAGE_FORMS => AccessPolicy::EFFECT_ALLOW]],
        [CorexAbility::MANAGE_ACCESS => '2026-11-20T00:00:00+00:00'],
    ), null, 'key');

    expect(array_keys($mine))->toBe([CorexAbility::MANAGE_ACCESS, CorexAbility::MANAGE_FORMS, CorexAbility::MANAGE_BLOG])
        ->and($mine[CorexAbility::MANAGE_FORMS]['how'])->toBe(AccessMatrix::HELD_ROLE)
        ->and($mine[CorexAbility::MANAGE_FORMS]['role'])->toBe('Editor')
        ->and($mine[CorexAbility::MANAGE_ACCESS]['how'])->toBe(AccessMatrix::HELD_GRANT)
        ->and($mine[CorexAbility::MANAGE_ACCESS]['until'])->toBe('2026-11-20T00:00:00+00:00')
        ->and($mine[CorexAbility::MANAGE_BLOG]['how'])->toBe(AccessMatrix::HELD_ACCOUNT);
});

it('says a site administrator holds every ability through site administration', function () {
    $held = array_fill_keys(array_map(static fn ($a) => $a->key, CorexAbilityCatalog::defaults()->all()), true);
    $mine = $this->matrix->forUser($held, [accessRoles()[0]], [], []);

    expect($mine)->toHaveCount($this->total)
        ->and(array_unique(array_column($mine, 'how')))->toBe([AccessMatrix::HELD_SITE_ADMINISTRATION]);
});

it('detects known permissions plugins and ignores everything else', function () {
    $conflicts = $this->matrix->conflicts([
        'members/members.php',
        'akismet/akismet.php',
        'user-role-editor/user-role-editor.php',
    ]);

    expect($conflicts)->toBe(['Members', 'User Role Editor']);
});

it('reports no conflicts when no permissions plugin is active', function () {
    expect($this->matrix->conflicts(['akismet/akismet.php']))->toBe([]);
});

it('keeps CoreX states editable when a role plugin is active', function () {
    $matrix = $this->matrix->editableCorexMatrix(
        roles: [['key' => 'editor', 'name' => 'Editor']],
        effectsByRole: ['editor' => [CorexAbility::MANAGE_FORMS => AccessPolicy::EFFECT_DENY]],
        activePlugins: ['members/members.php'],
    );
    $forms = array_column($matrix['rows'], null, 'key')[CorexAbility::MANAGE_FORMS];

    expect($matrix['conflicts'])->toBe(['Members'])
        ->and($matrix['nativeCapabilitiesEditable'])->toBeFalse()
        ->and($forms['cells']['editor']['effect'])->toBe(AccessPolicy::EFFECT_DENY)
        ->and($forms['cells']['editor']['editable'])->toBeTrue();
});
