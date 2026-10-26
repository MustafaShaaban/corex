<?php

/**
 * The one launch checklist (spec 118: US-OVR-01, US-OVR-02, US-OVR-03, US-SEC-09). No WordPress.
 *
 * Contract: two groups, Security and hosting first; site and content checks advise and never block;
 * a check that does not apply is in neither number; a check that cannot be read is never done and
 * never blocks; the same state reads Done/Blocked on the Overview and Ready/Blocks going live where
 * going live is decided; only an open row links to its fix.
 *
 * @package Corex\Tests\Unit\Operations
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Operations\ReadinessSnapshot;
use Corex\Config\Readiness\LaunchChecklist;
use Corex\Config\Readiness\ReadinessWords;
use Corex\Config\Readiness\SiteChecks;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('_n')->alias(static fn (string $single, string $plural, int $number): string => $number === 1 ? $single : $plural);
    Functions\when('number_format_i18n')->alias(static fn ($number): string => (string) $number);
});

/** Every site fact met, overridden per test. */
function siteFacts(array $overrides = []): array
{
    return array_merge([
        'brand'     => ['name' => true, 'logo' => true],
        'kit'       => ['present' => true, 'applied' => true],
        'frontPage' => ['static' => true, 'template' => false],
        'privacy'   => ['page' => 3, 'published' => true],
        'terms'     => ['page' => 4, 'published' => true],
        'testEmail' => ['mail' => true, 'sent' => true],
        'login'     => true,
    ], $overrides);
}

/** @return array<string,array{key:string,label:string,state:string,summary:string,resolution_url:string}> */
function siteChecksByKey(array $overrides = []): array
{
    return array_column((new SiteChecks())->checks(siteFacts($overrides)), null, 'key');
}

/** A snapshot of the given checks, `key => [group, state]`. */
function checklistSnapshot(array $checks): ReadinessSnapshot
{
    $rows = [];
    foreach ($checks as $key => [$group, $state]) {
        $rows[] = [
            'key' => $key, 'group' => $group, 'label' => ucfirst($key), 'state' => $state,
            'summary' => 'What ' . $key . ' needs', 'resolution_url' => 'https://example.test/fix/' . $key,
            'checked_at' => '2026-10-23T10:00:00+00:00', 'evidence_hash' => hash('sha256', $key),
        ];
    }

    return new ReadinessSnapshot($rows);
}

it('holds the seven site and content checks, all done on a finished site', function () {
    $checks = siteChecksByKey();

    expect(array_keys($checks))->toBe(['brand', 'kit', 'front_page', 'privacy_page', 'terms_page', 'test_email', 'login_protection'])
        ->and(array_unique(array_column($checks, 'state')))->toBe([ReadinessSnapshot::PASS]);
});

it('leaves the kit check out while no kit add-on is active', function () {
    expect(siteChecksByKey(['kit' => ['present' => false, 'applied' => false]]))->not->toHaveKey('kit');
});

it('passes the front page with a theme template and no static page', function () {
    $front = siteChecksByKey(['frontPage' => ['static' => false, 'template' => true]])['front_page'];

    expect($front['state'])->toBe(ReadinessSnapshot::PASS);
});

it('says what is missing on an open check, never the passing condition', function (array $overrides, string $key, string $missing, string $fix) {
    $check = siteChecksByKey($overrides)[$key];

    expect($check['state'])->toBe(ReadinessSnapshot::WARNING)
        ->and($check['summary'])->toBe($missing)
        ->and($check['resolution_url'])->toBe($fix);
})->with([
    'no front page' => [['frontPage' => ['static' => false, 'template' => false]], 'front_page', 'No static front page or front-page template', 'options-reading.php'],
    'privacy page a draft' => [['privacy' => ['page' => 3, 'published' => false]], 'privacy_page', 'The privacy policy page is not published', 'post.php?post=3&action=edit'],
    'no terms page' => [['terms' => ['page' => 0, 'published' => false]], 'terms_page', 'Choose a terms page', SiteChecks::BRAND_SETTINGS],
    'no test email' => [['testEmail' => ['mail' => true, 'sent' => false]], 'test_email', 'No test email sent in the last 90 days', 'admin.php?page=corex-email-studio'],
    'login protection off' => [['login' => false], 'login_protection', 'Login protection is off', 'admin.php?page=corex-operations-security&tab=login'],
    'no logo' => [['brand' => ['name' => true, 'logo' => false]], 'brand', 'No admin logo', SiteChecks::BRAND_SETTINGS],
]);

it('says "Needs Corex Mail" and leaves the test email out of the count without Corex Mail', function () {
    $email = siteChecksByKey(['testEmail' => ['mail' => false, 'sent' => false]])['test_email'];

    expect($email['state'])->toBe(ReadinessSnapshot::NOT_APPLICABLE)
        ->and($email['summary'])->toBe('Needs Corex Mail');
});

it('says "Could not check" for a fact that could not be read', function () {
    foreach (siteChecksByKey(['brand' => null, 'kit' => null, 'frontPage' => null, 'privacy' => null, 'terms' => null, 'testEmail' => null, 'login' => null]) as $check) {
        expect($check['state'])->toBe(ReadinessSnapshot::UNAVAILABLE);
    }
});

it('draws two groups, security first, and counts done over every check that applies', function () {
    $list = (new LaunchChecklist())->build(checklistSnapshot([
        'brand'      => [ReadinessSnapshot::GROUP_SITE, ReadinessSnapshot::PASS],
        'ssl'        => [ReadinessSnapshot::GROUP_SECURITY, ReadinessSnapshot::BLOCKING],
        'test_email' => [ReadinessSnapshot::GROUP_SITE, ReadinessSnapshot::NOT_APPLICABLE],
        'debug'      => [ReadinessSnapshot::GROUP_SECURITY, ReadinessSnapshot::UNAVAILABLE],
        'terms_page' => [ReadinessSnapshot::GROUP_SITE, ReadinessSnapshot::WARNING],
    ]), ReadinessWords::TODO);

    expect(array_column($list['groups'], 'key'))->toBe([ReadinessSnapshot::GROUP_SECURITY, ReadinessSnapshot::GROUP_SITE])
        ->and($list['done'])->toBe(1)
        ->and($list['total'])->toBe(4)
        ->and($list['count'])->toBe('1 of 4')
        ->and($list['blocking'])->toBe(1)
        ->and($list['groups'][1]['count'])->toBe('1 of 2');
});

it('words one state for the to-do list and for the gate', function (string $state, string $todo, string $gate, string $chip) {
    $snapshot = checklistSnapshot(['ssl' => [ReadinessSnapshot::GROUP_SECURITY, $state]]);

    $row     = (new LaunchChecklist())->build($snapshot, ReadinessWords::TODO)['groups'][0]['rows'][0];
    $gateRow = (new LaunchChecklist())->build($snapshot, ReadinessWords::GATE)['groups'][0]['rows'][0];

    expect($row['word'])->toBe($todo)
        ->and($gateRow['word'])->toBe($gate)
        ->and($row['chip'])->toBe($chip)
        ->and($gateRow['chip'])->toBe($chip)
        ->and($row['state'])->toBe($gateRow['state']);
})->with([
    'done' => [ReadinessSnapshot::PASS, 'Done', 'Ready', 'done'],
    'pending' => [ReadinessSnapshot::WARNING, 'Pending', 'Pending', 'pending'],
    'blocked' => [ReadinessSnapshot::BLOCKING, 'Blocked', 'Blocks going live', 'blocked'],
    'could not check' => [ReadinessSnapshot::UNAVAILABLE, 'Could not check', 'Could not check', 'unchecked'],
]);

it('links an open row to its fix and leaves a done row without a link', function () {
    $rows = (new LaunchChecklist())->build(checklistSnapshot([
        'ssl'   => [ReadinessSnapshot::GROUP_SECURITY, ReadinessSnapshot::BLOCKING],
        'debug' => [ReadinessSnapshot::GROUP_SECURITY, ReadinessSnapshot::PASS],
    ]), ReadinessWords::GATE)['groups'][0]['rows'];

    expect($rows[0]['href'])->toBe('https://example.test/fix/ssl')
        ->and($rows[1]['href'])->toBe('');
});

it('says "4 of 6 ready" where going live is decided, and what still blocks it', function () {
    $checks = [];
    foreach (['a', 'b', 'c', 'd'] as $key) {
        $checks[$key] = [ReadinessSnapshot::GROUP_SECURITY, ReadinessSnapshot::PASS];
    }
    $checks['e'] = [ReadinessSnapshot::GROUP_SECURITY, ReadinessSnapshot::BLOCKING];
    $checks['f'] = [ReadinessSnapshot::GROUP_SECURITY, ReadinessSnapshot::BLOCKING];

    $list = (new LaunchChecklist())->build(checklistSnapshot($checks), ReadinessWords::GATE);

    expect($list['count'])->toBe('4 of 6 ready')
        ->and($list['blockingText'])->toBe('2 checks still block going live');
});

it('blocks Production only on a blocked security check', function () {
    $snapshot = checklistSnapshot([
        'terms_page' => [ReadinessSnapshot::GROUP_SITE, ReadinessSnapshot::WARNING],
        'debug'      => [ReadinessSnapshot::GROUP_SECURITY, ReadinessSnapshot::UNAVAILABLE],
        'ssl'        => [ReadinessSnapshot::GROUP_SECURITY, ReadinessSnapshot::PASS],
    ]);

    expect($snapshot->hasBlockingChecks())->toBeFalse();
});

it('refuses a check in a group that does not exist', function () {
    checklistSnapshot(['x' => ['elsewhere', ReadinessSnapshot::PASS]]);
})->throws(InvalidArgumentException::class);
