<?php

/**
 * Unit tests for reCAPTCHA action derivation (spec 071 US1: FR-004, FR-023, FR-024).
 *
 * The same function is called by the renderer and the verifier, so these tests pin the
 * one contract both sides rely on. If the browser and the server can compute different
 * actions from the same form, FR-004 is broken.
 *
 * @package Corex\Tests\Unit\Captcha
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Forms\Submission\CaptchaAction;

beforeEach(function () {
    // sanitize_key: lowercase, keep a-z0-9_- , strip the rest.
    Functions\when('sanitize_key')->alias(
        static fn (string $key): string => (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower($key))
    );
});

it('derives a deterministic, prefixed action from the flow slug when none is given', function () {
    $a = CaptchaAction::forFlow('contact-us', null);
    $b = CaptchaAction::forFlow('contact-us', null);

    expect($a)->toBe($b)
        ->and($a)->toStartWith('corex_form_')
        ->and($a)->toBe('corex_form_contact_us');
});

it('gives two different forms two different actions', function () {
    expect(CaptchaAction::forFlow('contact', null))
        ->not->toBe(CaptchaAction::forFlow('careers', null));
});

it('honours an explicit override', function () {
    expect(CaptchaAction::forFlow('contact', 'newsletter_signup'))->toBe('newsletter_signup');
});

it('normalises an override to the provider-safe charset', function () {
    // Google actions allow A-Za-z0-9/_-. Spaces and other punctuation must go.
    expect(CaptchaAction::normalise('Contact Form!!'))->toBe('ContactForm')
        ->and(CaptchaAction::normalise('a/b-c_d'))->toBe('a/b-c_d');
});

it('bounds action length to 100 characters', function () {
    $long = str_repeat('a', 250);
    expect(strlen((string) CaptchaAction::normalise($long)))->toBeLessThanOrEqual(100)
        ->and(strlen(CaptchaAction::forFlow($long, null)))->toBeLessThanOrEqual(100);
});

it('returns null for an override that normalises to nothing usable', function () {
    expect(CaptchaAction::normalise('!!!'))->toBeNull()
        ->and(CaptchaAction::normalise(null))->toBeNull()
        ->and(CaptchaAction::normalise(''))->toBeNull();
});

it('falls back to a derived action when the override is unusable', function () {
    // An unusable override must not produce an empty action the provider would reject.
    expect(CaptchaAction::forFlow('contact', '!!!'))->toBe('corex_form_contact');
});
