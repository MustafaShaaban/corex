<?php

/**
 * Unit tests for the privacy-friendly Social Share block renderer (spec 063, Phase 7).
 * Contract: real permalink + share-intent links, accessible, no counts, no third-party scripts,
 * no fabricated output when there is no post.
 *
 * @package Corex\Tests\Unit\Blocks
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Blocks\SocialShareRenderer;

function renderSocialShare(array $attributes = [], string $permalink = 'https://acme.test/hello-world/', string $title = 'Hello World'): string
{
    Functions\when('__')->returnArg();
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_attr__')->returnArg();
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_attr')->alias(static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES));
    Functions\when('esc_url')->returnArg();
    Functions\when('get_permalink')->justReturn($permalink);
    Functions\when('get_the_title')->justReturn($title);

    return (new SocialShareRenderer())->render($attributes, '', new stdClass());
}

it('renders share-intent links for the real permalink with accessible names', function () {
    $html = renderSocialShare();

    expect($html)->toContain('twitter.com/intent/tweet')
        ->and($html)->toContain('facebook.com/sharer')
        ->and($html)->toContain('linkedin.com/sharing')
        ->and($html)->toContain('api.whatsapp.com/send')
        ->and($html)->toContain('mailto:?subject=')
        ->and($html)->toContain(rawurlencode('https://acme.test/hello-world/'))
        ->and($html)->toContain('Share on X')
        ->and($html)->toContain('role="group"');
});

it('renders nothing when there is no current post — never a fabricated bar', function () {
    expect(renderSocialShare([], ''))->toBe('');
});

it('honours the selected networks and drops unknown ones', function () {
    $html = renderSocialShare(['networks' => ['x', 'email', 'bogus']]);

    expect($html)->toContain('twitter.com/intent/tweet')
        ->and($html)->toContain('mailto:?subject=')
        ->and($html)->not->toContain('facebook.com/sharer')
        ->and($html)->not->toContain('linkedin.com/sharing');
});

it('renders the copy and native-share controls hidden for progressive enhancement (no dead no-JS buttons)', function () {
    $html = renderSocialShare();

    expect($html)->toContain('data-corex-share-copy=')
        ->and($html)->toContain('data-corex-share-native')
        ->and(substr_count($html, 'hidden'))->toBeGreaterThanOrEqual(2);
});

it('carries no fabricated share counts and no third-party tracking script', function () {
    $html = renderSocialShare();

    expect($html)->not->toContain('<script')
        ->and($html)->not->toContain('count')
        ->and($html)->toContain('rel="noopener noreferrer nofollow"');
});

it('opens email via mailto without a new-tab target', function () {
    $html = renderSocialShare(['networks' => ['email']]);

    // The email link must not carry target=_blank (mailto opens the mail client).
    expect($html)->toContain('mailto:?subject=')
        ->and($html)->not->toContain('target="_blank"');
});
