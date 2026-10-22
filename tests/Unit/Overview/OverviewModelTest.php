<?php

/**
 * Unit tests for the pure Overview readiness model (spec 064). No WordPress.
 * Contract: compose the dashboard from real facts only — truthful readiness, integrations, tiles,
 * data sources; never fabricate a count, an integration, or a score.
 *
 * @package Corex\Tests\Unit\Overview
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Overview\OverviewModel;
use Corex\Config\Security\LoginProtection\LoginAddressState;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('_n')->alias(static fn (string $single, string $plural, int $number): string => $number === 1 ? $single : $plural);
});

/** A site with every state fact set, overridden per test. */
function stateFacts(array $overrides = []): array
{
    return array_merge([
        'brand'    => ['name' => true, 'logo' => true],
        'mailFrom' => true,
        'insights' => ['key' => true, 'latest' => ['status' => 'good', 'score' => 95, 'summary' => 'Fast.']],
        'captcha'  => ['active' => true, 'driver' => 'turnstile', 'keys' => true],
        'login'    => LoginAddressState::HIDDEN,
    ], $overrides);
}

function overviewFacts(array $overrides = []): array
{
    return array_merge([
        'counts'            => ['posts' => 4, 'pages' => 6, 'submissions' => 12, 'addonsActive' => 3, 'addonsTotal' => 9],
        'facts'             => stateFacts(),
        'frontPageSet'      => true,
        'kitApplied'        => true,
        'hardeningWarnings' => 0,
        'dataSources'       => [['label' => 'Submissions', 'key' => 'submissions', 'total' => 12]],
        'formsCount'        => 1,
        'flowsCount'        => 3,
    ], $overrides);
}

it('builds the five dashboard regions from real facts', function () {
    $data = (new OverviewModel())->build(overviewFacts());

    expect(array_keys($data))->toBe(['tiles', 'readiness', 'integrations', 'dataSources', 'forms'])
        ->and($data['tiles'])->toHaveCount(4)
        ->and($data['dataSources'][0]['count'])->toBe(12)
        ->and($data['forms']['count'])->toBe(1);
});

it('projects real registered forms and versioned flows without a planned/read-only claim', function () {
    $forms = (new OverviewModel())->build(overviewFacts())['forms'];

    expect($forms['count'])->toBe(1)
        ->and($forms['flows'])->toBe(3)
        ->and($forms['note'])->not->toContain('planned')
        ->and($forms['note'])->not->toContain('future')
        ->and($forms['flows'])->toBe(max(0, $forms['flows']));

    // Negative or absent flow counts never render as a fabricated negative.
    $clamped = (new OverviewModel())->build(overviewFacts(['flowsCount' => -5]))['forms'];
    expect($clamped['flows'])->toBe(0);
});

it('reports submissions as an em dash when the forms source is unavailable — never a fabricated zero', function () {
    $data = (new OverviewModel())->build(overviewFacts(['counts' => ['posts' => 0, 'pages' => 0, 'submissions' => null, 'addonsActive' => 0, 'addonsTotal' => 0]]));
    $submissionTile = $data['tiles'][2];

    expect($submissionTile['value'])->toBe('—')
        ->and($submissionTile['detail'])->toContain('inactive');
});

it('counts readiness from real signals and never overstates it', function () {
    $done = (new OverviewModel())->build(overviewFacts())['readiness'];
    expect($done['total'])->toBe(6)
        ->and($done['done'])->toBe(6);

    $blank = (new OverviewModel())->build(overviewFacts([
        'facts' => stateFacts(['brand' => ['name' => false, 'logo' => false], 'mailFrom' => false]),
        'frontPageSet' => false,
        'kitApplied' => false,
        'hardeningWarnings' => 2,
    ]))['readiness'];
    // Spam protection stays done: every CoreX form carries a honeypot.
    expect($blank['done'])->toBe(1);
});

/** The rows of one region, by label. */
function rowsOf(array $facts, string $region): array
{
    $rows = (new OverviewModel())->build(overviewFacts($facts))[$region];
    $rows = $region === 'readiness' ? $rows['rows'] : $rows;

    return array_column($rows, null, 'label');
}

/*
 * Spec 114, US-SAFE-10: every "Done", "Configured" and "Connected" is true. A fresh install read
 * "Brand configured: Done", "Insights / PageSpeed: Provider connected" and "Captcha: Configured",
 * and its Login protection row counted hardening warnings.
 */
it('says a fresh install has set nothing, row by row', function () {
    $fresh = ['facts' => stateFacts([
        'brand'    => ['name' => false, 'logo' => false],
        'insights' => ['key' => false, 'latest' => null],
        'captcha'  => ['active' => true, 'driver' => 'none', 'keys' => false],
        'login'    => LoginAddressState::OFF,
    ]), 'hardeningWarnings' => 3];
    $readiness    = rowsOf($fresh, 'readiness');
    $integrations = rowsOf($fresh, 'integrations');

    expect($readiness['Brand configured']['note'])->toBe('Not set')
        ->and($readiness['Brand configured']['done'])->toBeFalse()
        ->and($readiness['Brand configured']['href'])->toBe(OverviewModel::BRAND_SETTINGS)
        ->and($readiness['Spam protection']['note'])->toBe('Honeypot only')
        ->and($readiness['Hardening checks']['note'])->toBe('3 checks to review')
        ->and($integrations['Insights / PageSpeed']['note'])->toBe('Keyless (shared quota)')
        ->and($integrations['Captcha']['note'])->toBe('Not configured')
        ->and($integrations['Login protection']['note'])->toBe('Off');
});

it('calls the brand done only with a company name and a logo', function (bool $name, bool $logo, bool $done) {
    $row = rowsOf(['facts' => stateFacts(['brand' => ['name' => $name, 'logo' => $logo]])], 'readiness')['Brand configured'];

    expect($row['done'])->toBe($done)
        ->and($row['note'])->toBe($done ? 'Done' : 'Not set');
})->with([
    'both' => [true, true, true],
    'a name, no logo' => [true, false, false],
    'a logo, no name' => [false, true, false],
]);

it('says what the PageSpeed row is, in the words of what is so', function (bool $key, ?array $latest, string $note, string $tone) {
    $row = rowsOf(['facts' => stateFacts(['insights' => ['key' => $key, 'latest' => $latest]])], 'integrations')['Insights / PageSpeed'];

    expect($row['note'])->toBe($note)
        ->and($row['tone'])->toBe($tone);
})->with([
    'a key and a completed run' => [true, ['status' => 'good', 'score' => 95], 'Connected', OverviewModel::TONE_SUCCESS],
    'a key, nothing run yet' => [true, null, 'Key saved, not checked yet', OverviewModel::TONE_NEUTRAL],
    'no key' => [false, ['status' => 'good', 'score' => 95], 'Keyless (shared quota)', OverviewModel::TONE_NEUTRAL],
    'a failed run' => [false, ['status' => 'failed', 'summary' => 'PageSpeed quota was exceeded.'], 'Error: PageSpeed quota was exceeded.', OverviewModel::TONE_WARNING],
    'a key, then a failed run' => [true, ['status' => 'failed', 'summary' => 'The key was rejected.'], 'Error: The key was rejected.', OverviewModel::TONE_WARNING],
]);

it('says the captcha is configured only with its add-on on, a provider and both keys', function (array $captcha, string $integration, string $spam) {
    $facts = ['facts' => stateFacts(['captcha' => $captcha])];

    expect(rowsOf($facts, 'integrations')['Captcha']['note'])->toBe($integration)
        ->and(rowsOf($facts, 'readiness')['Spam protection']['note'])->toBe($spam);
})->with([
    'a provider with its keys' => [['active' => true, 'driver' => 'hcaptcha', 'keys' => true], 'hCaptcha configured', 'Honeypot and hCaptcha'],
    'keys saved, add-on off' => [['active' => false, 'driver' => 'recaptcha', 'keys' => true], 'Not configured (add-on inactive)', 'Honeypot only'],
    'driver None' => [['active' => true, 'driver' => 'none', 'keys' => false], 'Not configured', 'Honeypot only'],
    'driver Honeypot' => [['active' => true, 'driver' => 'honeypot', 'keys' => false], 'Not configured', 'Honeypot only'],
    'a provider with a key missing' => [['active' => true, 'driver' => 'recaptcha', 'keys' => false], 'Keys missing', 'Honeypot only'],
]);

it('reads the login protection row from the Security Center, and hardening in its own row', function (string $state, string $note) {
    $facts = ['facts' => stateFacts(['login' => $state]), 'hardeningWarnings' => 0];

    expect(rowsOf($facts, 'integrations')['Login protection']['note'])->toBe($note)
        ->and(rowsOf($facts, 'readiness')['Hardening checks']['note'])->toBe('Done');
})->with([
    'protection off' => [LoginAddressState::OFF, 'Off'],
    'on, hidden' => [LoginAddressState::HIDDEN, 'On, login address hidden'],
    'on, not hidden' => [LoginAddressState::REACHABLE, 'On, login address reachable'],
    'on, recovery' => [LoginAddressState::RECOVERY, 'On, recovery active: login address reachable'],
]);

it('says "Could not check" for a fact that could not be read, never a success', function () {
    $facts = ['facts' => stateFacts(['insights' => null, 'captcha' => null, 'login' => null]), 'hardeningWarnings' => null];
    $readiness    = rowsOf($facts, 'readiness');
    $integrations = rowsOf($facts, 'integrations');

    foreach ([$readiness['Spam protection'], $readiness['Hardening checks']] as $row) {
        expect($row['note'])->toBe('Could not check')
            ->and($row['done'])->toBeFalse();
    }
    foreach (['Insights / PageSpeed', 'Captcha', 'Login protection'] as $label) {
        expect($integrations[$label]['note'])->toBe('Could not check')
            ->and($integrations[$label]['tone'])->toBe(OverviewModel::TONE_WARNING);
    }
});

it('clamps negative counts and never renders a negative data-source total', function () {
    $data = (new OverviewModel())->build(overviewFacts(['dataSources' => [['label' => 'X', 'key' => 'x', 'total' => -3]]]));

    expect($data['dataSources'][0]['count'])->toBe(0);
});
