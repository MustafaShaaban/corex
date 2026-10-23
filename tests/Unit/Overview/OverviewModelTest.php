<?php

/**
 * Unit tests for the Overview's analytics and security rows (spec 064, spec 114). No WordPress.
 * Contract: each row is in the words of what is so, and a fact that could not be read is "Could not
 * check", never a success. The launch checklist and the tiles have their own tests since spec 118.
 *
 * @package Corex\Tests\Unit\Overview
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Overview\OverviewModel;
use Corex\Config\Security\LoginProtection\LoginAddressState;

beforeEach(function () {
    Functions\when('__')->returnArg();
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

/** @return array<string,array{label:string,note:string,tone:string}> */
function integrationRows(array $facts): array
{
    return array_column((new OverviewModel())->integrations($facts), null, 'label');
}

it('lists PageSpeed, the captcha, transactional email and login protection', function () {
    expect(array_keys(integrationRows(stateFacts())))
        ->toBe(['Insights / PageSpeed', 'Captcha', 'Transactional email', 'Login protection']);
});

it('says what the PageSpeed row is, in the words of what is so', function (bool $key, ?array $latest, string $note, string $tone) {
    $row = integrationRows(stateFacts(['insights' => ['key' => $key, 'latest' => $latest]]))['Insights / PageSpeed'];

    expect($row['note'])->toBe($note)
        ->and($row['tone'])->toBe($tone);
})->with([
    'a key and a completed run' => [true, ['status' => 'good', 'score' => 95], 'Connected', OverviewModel::TONE_SUCCESS],
    'a key, nothing run yet' => [true, null, 'Key saved, not checked yet', OverviewModel::TONE_NEUTRAL],
    'no key' => [false, ['status' => 'good', 'score' => 95], 'Keyless (shared quota)', OverviewModel::TONE_NEUTRAL],
    'a failed run' => [false, ['status' => 'failed', 'summary' => 'PageSpeed quota was exceeded.'], 'Error: PageSpeed quota was exceeded.', OverviewModel::TONE_WARNING],
    'a key, then a failed run' => [true, ['status' => 'failed', 'summary' => 'The key was rejected.'], 'Error: The key was rejected.', OverviewModel::TONE_WARNING],
]);

it('says the captcha is configured only with its add-on on, a provider and both keys', function (array $captcha, string $note) {
    expect(integrationRows(stateFacts(['captcha' => $captcha]))['Captcha']['note'])->toBe($note);
})->with([
    'a provider with its keys' => [['active' => true, 'driver' => 'hcaptcha', 'keys' => true], 'hCaptcha configured'],
    'keys saved, add-on off' => [['active' => false, 'driver' => 'recaptcha', 'keys' => true], 'Not configured (add-on inactive)'],
    'driver None' => [['active' => true, 'driver' => 'none', 'keys' => false], 'Not configured'],
    'driver Honeypot' => [['active' => true, 'driver' => 'honeypot', 'keys' => false], 'Not configured'],
    'a provider with a key missing' => [['active' => true, 'driver' => 'recaptcha', 'keys' => false], 'Keys missing'],
]);

it('reads the login protection row from the Security Center', function (string $state, string $note) {
    expect(integrationRows(stateFacts(['login' => $state]))['Login protection']['note'])->toBe($note);
})->with([
    'protection off' => [LoginAddressState::OFF, 'Off'],
    'on, hidden' => [LoginAddressState::HIDDEN, 'On, login address hidden'],
    'on, not hidden' => [LoginAddressState::REACHABLE, 'On, login address reachable'],
    'on, recovery' => [LoginAddressState::RECOVERY, 'On, recovery active: login address reachable'],
]);

it('says "Could not check" for a fact that could not be read, never a success', function () {
    $rows = integrationRows(stateFacts(['insights' => null, 'captcha' => null, 'login' => null]));

    foreach (['Insights / PageSpeed', 'Captcha', 'Login protection'] as $label) {
        expect($rows[$label]['note'])->toBe('Could not check')
            ->and($rows[$label]['tone'])->toBe(OverviewModel::TONE_WARNING);
    }
});
