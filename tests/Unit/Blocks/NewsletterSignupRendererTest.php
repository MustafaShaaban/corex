<?php

/**
 * Unit tests for the Newsletter Signup block renderer (spec 063, Phase 7).
 * Contract: gated on the corex-newsletter add-on; a real double opt-in form wired to the real
 * subscribe endpoint; honeypot + consent present; honest unavailable state; no fabricated success.
 *
 * @package Corex\Tests\Unit\Blocks
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Blocks\NewsletterSignupRenderer;

function renderNewsletter(bool $active = true, array $attributes = []): string
{
    Functions\when('__')->returnArg();
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_attr__')->returnArg();
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_attr')->alias(static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES));
    Functions\when('esc_url')->returnArg();
    Functions\when('rest_url')->alias(static fn (string $path): string => 'https://acme.test/wp-json/' . $path);
    Functions\when('wp_create_nonce')->justReturn('rest-nonce');
    Functions\when('get_option')->alias(static fn (string $name, $default = false) => $name === 'active_plugins'
        ? ($active ? ['corex-newsletter/corex-newsletter.php'] : [])
        : $default);

    return (new NewsletterSignupRenderer())->render($attributes, '', new stdClass());
}

it('renders a real double opt-in form wired to the subscribe endpoint when the add-on is active', function () {
    $html = renderNewsletter();

    expect($html)->toContain('data-corex-newsletter="https://acme.test/wp-json/corex/v1/newsletter/subscribe"')
        ->and($html)->toContain('data-corex-newsletter-nonce="rest-nonce"')
        ->and($html)->toContain('type="email"')
        ->and($html)->toContain('name="consent"')
        ->and($html)->toContain('aria-live="polite"');
});

it('includes an accessible honeypot that must stay empty', function () {
    $html = renderNewsletter();

    expect($html)->toContain('name="corex_hp"')
        ->and($html)->toContain('aria-hidden="true"')
        ->and($html)->toContain('tabindex="-1"');
});

it('shows an honest unavailable state (no form) when the newsletter add-on is inactive', function () {
    $html = renderNewsletter(false);

    expect($html)->toContain('not available')
        ->and($html)->not->toContain('data-corex-newsletter=')
        ->and($html)->not->toContain('type="email"');
});

it('uses the custom copy when provided, else honest defaults — never fabricated content', function () {
    $custom = renderNewsletter(true, ['heading' => 'Join Acme', 'buttonLabel' => 'Sign up']);

    expect($custom)->toContain('Join Acme')->toContain('Sign up');
});

it('carries no third-party script and no fabricated success message in the initial markup', function () {
    $html = renderNewsletter();

    expect($html)->not->toContain('<script')
        // The status region starts empty; success text only appears after a real endpoint response.
        ->and($html)->toContain('corex-newsletter-signup__status" role="status" aria-live="polite">');
});
