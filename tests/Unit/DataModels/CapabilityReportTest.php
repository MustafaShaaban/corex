<?php

/**
 * The capability summary (spec 074, FR-5).
 *
 * One place that answers "what is registered, what can it do, and what is missing" — so an
 * unavailable capability is explained where somebody can act on it, instead of being discovered as
 * an empty workspace tab. Pure: the boundary hands it already-resolved facts, so what it says can
 * be tested without WordPress and cannot drift into inventing state.
 *
 * @package Corex\Tests\Unit\DataModels
 */

declare(strict_types=1);

use Corex\Config\DataModels\CapabilityReport;

function capabilityFacts(array $overrides = []): array
{
    return array_merge([
        'forms' => [
            ['slug' => 'contact', 'label' => 'Contact', 'source' => 'code_form', 'active' => true],
            ['slug' => 'quote', 'label' => 'Quote request', 'source' => 'visual_flow', 'active' => false],
        ],
        'models' => [
            [
                'key' => 'table-subscribers',
                'label' => 'Newsletter subscribers',
                'capabilities' => [
                    'read' => true, 'create' => true, 'import_dry_run' => true,
                    'export_csv' => true, 'migrations' => true, 'rollback' => true,
                ],
            ],
            [
                'key' => 'submissions',
                'label' => 'Form submissions',
                'capabilities' => ['read' => true, 'export_csv' => true],
            ],
        ],
        'addons' => [
            ['slug' => 'corex-newsletter', 'label' => 'Newsletter', 'state' => 'active'],
            ['slug' => 'corex-bookings', 'label' => 'Bookings', 'state' => 'inactive'],
            ['slug' => 'corex-woo', 'label' => 'Woo kit', 'state' => 'not_installed'],
        ],
        'producers' => ['submission.new', 'readiness.blocker'],
        'gaps' => [],
    ], $overrides);
}

it('reports every registered form with the source it came from', function () {
    $report = (new CapabilityReport())->build(capabilityFacts());

    expect($report['forms']['total'])->toBe(2)
        ->and($report['forms']['items'][0])->toMatchArray([
            'slug' => 'contact',
            'source' => 'code_form',
        ]);
});

it('summarises what each model can do, and what it cannot', function () {
    $report = (new CapabilityReport())->build(capabilityFacts());
    $subscribers = $report['models']['items'][0];
    $submissions = $report['models']['items'][1];

    expect($subscribers['can'])->toBe(['read', 'write', 'import', 'export', 'migrations', 'rollback'])
        ->and($submissions['can'])->toBe(['read', 'export'])
        ->and($submissions['cannot'])->toContain('import');
});

it('counts how many models support each capability', function () {
    $report = (new CapabilityReport())->build(capabilityFacts());

    expect($report['models']['supporting'])->toMatchArray([
        'import' => 1,
        'migrations' => 1,
        'export' => 2,
        'write' => 1,
    ]);
});

it('reports add-on state without inventing one', function () {
    $report = (new CapabilityReport())->build(capabilityFacts());

    expect($report['addons']['active'])->toBe(1)
        ->and($report['addons']['inactive'])->toBe(1)
        ->and($report['addons']['not_installed'])->toBe(1);
});

it('lists the notification producers that are actually wired', function () {
    $report = (new CapabilityReport())->build(capabilityFacts());

    expect($report['producers']['total'])->toBe(2)
        ->and($report['producers']['keys'])->toBe(['submission.new', 'readiness.blocker']);
});

it('carries each gap with something to do about it', function () {
    $report = (new CapabilityReport())->build(capabilityFacts([
        'gaps' => [[
            'key' => 'captcha.keys',
            'summary' => 'reCAPTCHA is selected but has no keys, so it is not protecting anything.',
            'action_label' => 'Open Settings',
            'action_url' => 'https://example.test/wp-admin/admin.php?page=corex-settings-config',
        ]],
    ]));

    expect($report['gaps'])->toHaveCount(1)
        ->and($report['gaps'][0]['action_url'])->toContain('corex-settings-config')
        ->and($report['hasGaps'])->toBeTrue();
});

it('says so plainly when nothing is missing', function () {
    $report = (new CapabilityReport())->build(capabilityFacts());

    expect($report['gaps'])->toBe([])
        ->and($report['hasGaps'])->toBeFalse();
});

it('drops any fact carrying a secret-bearing key', function () {
    // Diagnostics are read by whoever can open the screen and are the sort of thing people
    // screenshot into a support thread. A gap that names a credential is not shown at all.
    $report = (new CapabilityReport())->build(capabilityFacts([
        'gaps' => [
            ['key' => 'smtp', 'summary' => 'Broken', 'password' => 'hunter2'],
            ['key' => 'api', 'summary' => 'Broken', 'api_key' => 'sk-live-1'],
            ['key' => 'fine', 'summary' => 'A gap with nothing secret in it'],
        ],
    ]));

    expect($report['gaps'])->toHaveCount(1)
        ->and($report['gaps'][0]['key'])->toBe('fine');
});

it('never emits a stack trace or a file path from a summary', function () {
    $report = (new CapabilityReport())->build(capabilityFacts([
        'gaps' => [[
            'key' => 'crash',
            'summary' => "Fatal error in C:\\wamp64\\www\\corex\\plugins\\thing.php:42\n#0 {main}",
        ]],
    ]));

    expect($report['gaps'][0]['summary'])->not->toContain('#0 ')
        ->and($report['gaps'][0]['summary'])->not->toContain('.php:');
});

it('survives a site with nothing registered at all', function () {
    $report = (new CapabilityReport())->build([
        'forms' => [], 'models' => [], 'addons' => [], 'producers' => [], 'gaps' => [],
    ]);

    expect($report['forms']['total'])->toBe(0)
        ->and($report['models']['items'])->toBe([])
        ->and($report['producers']['total'])->toBe(0)
        ->and($report['hasGaps'])->toBeFalse();
});
