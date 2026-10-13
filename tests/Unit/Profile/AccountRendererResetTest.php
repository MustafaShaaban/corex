<?php

/**
 * Unit tests for what the account block shows of a password reset: the mark the
 * forgot-password form carries, the new-password form a reset key opens, and the two things
 * said to somebody who arrives without a usable key (spec 068, FR-158a to FR-158c).
 *
 * @package Corex\Tests\Unit\Profile
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Profile\Block\AccountRenderer;
use Corex\Profile\Reset\ResetState;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_html')->alias(fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES));
    Functions\when('esc_attr')->alias(fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES));
    Functions\when('esc_url')->alias(fn ($v) => (string) $v);
});

it('sends the page mark with a request for a reset link, and with nothing else', function () {
    $html = (new AccountRenderer())->render([
        'loggedIn'         => false,
        'registrationOpen' => true,
        'pageMark'         => '12.abc"def',
    ]);

    preg_match('~<form[^>]*data-corex-account-form="reset-request".*?</form>~s', $html, $forgot);

    expect($forgot[0])->toContain('<input type="hidden" name="page" value="12.abc&quot;def" />')
        ->and(substr_count($html, 'name="page"'))->toBe(1);
});

it('leaves the mark out where the block is on no page', function () {
    $html = (new AccountRenderer())->render(['loggedIn' => false]);

    expect($html)->not->toContain('name="page"');
});

it('shows the new-password form, and no other, to somebody who holds a reset key', function (bool $loggedIn) {
    $html = (new AccountRenderer())->render([
        'loggedIn' => $loggedIn,
        'reset'    => ResetState::choose('sal"ma', 'k3yFromTheEmail'),
    ]);

    expect(substr_count($html, '<form'))->toBe(1)
        ->and($html)->toContain('data-corex-account-form="reset"')
        ->and($html)->toContain('<input type="hidden" name="login" value="sal&quot;ma" />')
        ->and($html)->toContain('<input type="hidden" name="key" value="k3yFromTheEmail" />')
        ->and($html)->toContain('type="password" id="corex-account-reset-password" name="password"')
        ->and($html)->toContain('type="password" id="corex-account-reset-password_confirm" name="password_confirm"');
})->with(['signed out' => [false], 'signed in as somebody' => [true]]);

it('says a link has expired above the forms, where a new one can be asked for', function () {
    $html = (new AccountRenderer())->render(['loggedIn' => false, 'reset' => ResetState::expired()]);

    expect($html)->toMatch('~^<div[^>]*><p class="corex-account__status" role="status" aria-live="polite" data-state="error">[^<]*expired[^<]*</p>~')
        ->and($html)->toContain('data-corex-account-form="reset-request"')
        ->and($html)->not->toContain('data-corex-account-form="reset"');
});

it('says the password was changed above the sign-in form', function () {
    $html = (new AccountRenderer())->render(['loggedIn' => false, 'reset' => ResetState::done()]);

    expect($html)->toMatch('~^<div[^>]*><p class="corex-account__status" role="status" aria-live="polite" data-state="ok">[^<]*updated[^<]*</p>~')
        ->and($html)->toContain('data-corex-account-form="login"');
});

it('puts no line above the forms when a reset has nothing to say', function () {
    $html = (new AccountRenderer())->render(['loggedIn' => false, 'reset' => ResetState::none()]);

    expect($html)->toMatch('~^<div[^>]*data-corex-account[^>]*><div class="corex-account__grid">~');
});
