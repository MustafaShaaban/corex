<?php

/**
 * The sentences a refused login settings save answers with (spec 124, FR-015, User Story 7
 * scenario 3): each names its field's range, or the proxy line that is wrong.
 *
 * @package Corex\Tests\Unit\Security
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Security\LoginProtection\LoginSettingsInput;
use Corex\Config\Security\LoginProtection\LoginSettingsProblem;
use Corex\Config\Security\LoginProtection\LoginSlug;
use Corex\Config\Security\LoginProtection\LoginSlugAvailability;
use Corex\Config\Security\LoginSettingsMessages;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('number_format_i18n')->alias(static fn (int $number): string => number_format($number));
    Functions\when('sanitize_text_field')->alias(static fn (string $text): string => trim(strip_tags($text)));
    $this->messages = new LoginSettingsMessages();
});

it('names the range of every number it refuses (FR-015, scenario 3)', function (string $field) {
    [$min, $max] = LoginSettingsInput::RANGES[$field];
    $sentence = $this->messages->sentence(new LoginSettingsProblem($field, LoginSettingsProblem::REASON_OUT_OF_RANGE, min: $min, max: $max));

    expect($sentence)->toContain(number_format($min))->toContain(number_format($max));
})->with(array_keys(LoginSettingsInput::RANGES));

it('says a lockout runs from 1 to 10,080 minutes', function () {
    expect($this->messages->sentence(new LoginSettingsProblem('lockout_minutes', LoginSettingsProblem::REASON_OUT_OF_RANGE, min: 1, max: 10080)))
        ->toBe('The lockout length must be a whole number of minutes from 1 to 10,080.');
});

it('names the proxy line that is wrong (scenario 3)', function () {
    $sentence = $this->messages->sentence(new LoginSettingsProblem(
        'trusted_proxies',
        LoginSettingsProblem::REASON_NOT_A_PROXY,
        line: 2,
        value: '10.0.0.0/33',
    ));

    expect($sentence)->toContain('2')->toContain('10.0.0.0/33');
});

it('says a range that trusts every address would let anyone choose their counted address, naming the line', function () {
    $sentence = $this->messages->sentence(new LoginSettingsProblem(
        'trusted_proxies',
        LoginSettingsProblem::REASON_TRUSTS_EVERY_ADDRESS,
        line: 3,
        value: '0.0.0.0/0',
    ));

    expect($sentence)->toContain('3')->toContain('0.0.0.0/0')
        ->toContain('would let anyone choose the address their sign-in attempts are counted by');
});

it('writes no em dash in any sentence', function () {
    $problems = [
        new LoginSettingsProblem('custom_slug', LoginSettingsProblem::REASON_ADDRESS, detail: LoginSlugAvailability::REASON_TAKEN_BY_ROUTE),
        new LoginSettingsProblem('custom_slug', LoginSettingsProblem::REASON_ADDRESS, detail: LoginSlugAvailability::REASON_TAKEN_BY_PAGE),
        new LoginSettingsProblem('custom_slug', LoginSettingsProblem::REASON_ADDRESS, detail: LoginSlug::REASON_RESERVED),
        new LoginSettingsProblem('custom_slug', LoginSettingsProblem::REASON_ADDRESS_NEEDED_TO_HIDE, detail: LoginSlug::REASON_FORMAT),
        new LoginSettingsProblem('trusted_proxies', LoginSettingsProblem::REASON_TRUSTS_EVERY_ADDRESS, line: 1, value: '::/0'),
        new LoginSettingsProblem('trusted_proxies', LoginSettingsProblem::REASON_NOT_A_PROXY, line: 1, value: 'x'),
    ];

    foreach ($problems as $problem) {
        expect($this->messages->sentence($problem))->not->toContain(mb_chr(0x2014));
    }
});

it('does not repeat markup from a proxy line back', function () {
    $sentence = $this->messages->sentence(new LoginSettingsProblem(
        'trusted_proxies',
        LoginSettingsProblem::REASON_NOT_A_PROXY,
        line: 1,
        value: '<script>x</script>',
    ));

    expect($sentence)->not->toContain('<script>');
});

it('says hiding wp-login.php needs a usable address (FR-013)', function () {
    $sentence = $this->messages->sentence(new LoginSettingsProblem(
        'custom_slug',
        LoginSettingsProblem::REASON_ADDRESS_NEEDED_TO_HIDE,
        detail: LoginSlug::REASON_EMPTY,
    ));

    expect($sentence)->toContain('wp-login.php');
});

it('explains each kind of unusable address differently', function () {
    $sentences = array_map(
        fn (string $reason): string => $this->messages->sentence(new LoginSettingsProblem('custom_slug', LoginSettingsProblem::REASON_ADDRESS, detail: $reason)),
        [LoginSlug::REASON_FORMAT, LoginSlug::REASON_RESERVED, LoginSlugAvailability::REASON_TAKEN_BY_PAGE, LoginSlugAvailability::REASON_TAKEN_BY_ROUTE],
    );

    expect(array_unique($sentences))->toHaveCount(4);
});

it('keys each sentence by its field', function () {
    $errors = $this->messages->errors([
        'lockout_minutes' => new LoginSettingsProblem('lockout_minutes', LoginSettingsProblem::REASON_OUT_OF_RANGE, min: 1, max: 10080),
        'trusted_proxies' => new LoginSettingsProblem('trusted_proxies', LoginSettingsProblem::REASON_NOT_A_PROXY, line: 1, value: 'x'),
    ]);

    expect(array_keys($errors))->toBe(['lockout_minutes', 'trusted_proxies']);
});

it('gives the range and the bad lines as data beside the sentences', function () {
    $details = $this->messages->details([
        'lockout_minutes' => new LoginSettingsProblem('lockout_minutes', LoginSettingsProblem::REASON_OUT_OF_RANGE, min: 1, max: 10080),
        'trusted_proxies' => new LoginSettingsProblem('trusted_proxies', LoginSettingsProblem::REASON_NOT_A_PROXY, line: 2, value: '10.0.0.0/33', lines: [2 => '10.0.0.0/33']),
    ]);

    expect($details)->toBe(['fields' => [
        'lockout_minutes' => ['reason' => 'out_of_range', 'min' => 1, 'max' => 10080],
        'trusted_proxies' => ['reason' => 'not_a_proxy', 'lines' => [['line' => 2, 'value' => '10.0.0.0/33']]],
    ]]);
});
