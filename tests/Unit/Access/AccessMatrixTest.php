<?php

/**
 * Unit tests for the pure Access & Abilities matrix (spec 065). No WordPress.
 * Contract: reflect the REAL role capabilities; invent no capability; CoreX admin maps to manage_options.
 *
 * @package Corex\Tests\Unit\Access
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Access\AccessMatrix;

beforeEach(function () {
    Functions\when('__')->returnArg();
    $this->matrix = new AccessMatrix();
});

/**
 * @return list<array{key:string,name:string,caps:array<string,bool>}>
 */
function accessRoles(): array
{
    return [
        ['key' => 'administrator', 'name' => 'Administrator', 'caps' => ['manage_options' => true, 'publish_posts' => true, 'upload_files' => true, 'edit_others_posts' => true, 'manage_categories' => true]],
        ['key' => 'author', 'name' => 'Author', 'caps' => ['publish_posts' => true, 'upload_files' => true]],
        ['key' => 'subscriber', 'name' => 'Subscriber', 'caps' => ['read' => true]],
    ];
}

it('maps CoreX admin access to the real manage_options capability', function () {
    $groups = $this->matrix->groups();
    $adminRow = array_values(array_filter($groups, static fn (array $g): bool => $g['key'] === 'corex_admin'))[0];

    expect($adminRow['cap'])->toBe('manage_options');
});

it('builds a truthful role x capability matrix from the real roles', function () {
    $built = $this->matrix->build(accessRoles());

    expect($built['roles'])->toHaveCount(3)
        ->and($built['rows'][0]['cells']['administrator'])->toBeTrue()   // corex admin
        ->and($built['rows'][0]['cells']['author'])->toBeFalse()          // author lacks manage_options
        ->and($built['rows'][0]['cells']['subscriber'])->toBeFalse();
});

it('reflects publish capability truthfully per role', function () {
    $built = $this->matrix->build(accessRoles());
    $content = array_values(array_filter($built['rows'], static fn (array $r): bool => $r['key'] === 'content'))[0];

    expect($content['cells']['author'])->toBeTrue()
        ->and($content['cells']['subscriber'])->toBeFalse();
});

it('reports the current user permissions against the tracked capabilities', function () {
    $abilities = $this->matrix->forUser(['manage_options' => true, 'upload_files' => true]);
    $byLabel = [];
    foreach ($abilities as $a) {
        $byLabel[$a['cap']] = $a['granted'];
    }

    expect($byLabel['manage_options'])->toBeTrue()
        ->and($byLabel['upload_files'])->toBeTrue()
        ->and($byLabel['publish_posts'])->toBeFalse();
});

it('marks the code-gated CoreX admin ability as high risk and locked', function () {
    $groups = $this->matrix->groups();
    $byKey = [];
    foreach ($groups as $g) {
        $byKey[$g['key']] = $g;
    }

    expect($byKey['corex_admin']['risk'])->toBe('high')
        ->and($byKey['corex_admin']['locked'])->toBeTrue()
        ->and($byKey['content']['risk'])->toBe('standard')
        ->and($byKey['content']['locked'])->toBeFalse();
});

it('carries risk and locked metadata through to matrix rows', function () {
    $built = $this->matrix->build(accessRoles());

    expect($built['rows'][0]['locked'])->toBeTrue()
        ->and($built['rows'][0]['risk'])->toBe('high')
        ->and($built['rows'][1]['locked'])->toBeFalse();
});

it('summarises roles with real user counts, origin, and granted ability counts', function () {
    $summaries = $this->matrix->roleSummaries(accessRoles(), ['administrator' => 2, 'author' => 5]);

    expect($summaries)->toHaveCount(3)
        ->and($summaries[0]['name'])->toBe('Administrator')
        ->and($summaries[0]['isCore'])->toBeTrue()
        ->and($summaries[0]['users'])->toBe(2)
        ->and($summaries[0]['granted'])->toBe(5)      // holds every tracked capability
        ->and($summaries[0]['total'])->toBe(5)
        ->and($summaries[1]['granted'])->toBe(2)      // author: publish_posts + upload_files
        ->and($summaries[1]['users'])->toBe(5)
        ->and($summaries[2]['users'])->toBe(0);       // subscriber count missing => 0
});

it('flags a custom role as non-core', function () {
    $summaries = $this->matrix->roleSummaries(
        [['key' => 'site_manager', 'name' => 'Site Manager', 'caps' => []]],
        [],
    );

    expect($summaries[0]['isCore'])->toBeFalse();
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
