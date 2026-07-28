<?php

/**
 * Manual replies are branded, and the brand logo is reachable — issue #138, items 3 and 5.
 *
 * `EmailStudioSubmissionGateway::reply()` sent the operator's raw textarea HTML as the entire
 * message body with `replyTo` hard-coded to null, while `resend()` immediately below it rendered
 * through the template version and layout. Every automated email a site sent was branded; every
 * manual reply arrived unstyled on the client's default background.
 *
 * Separately, `Layout::wrap()` has always rendered an `<img>` when `$brand['logo']` is set — and
 * nothing ever set it, so the branch could not execute and every framework-rendered email was
 * text-branded only.
 *
 * @package Corex\Tests\Unit\Email
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Email\Template\Layout;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('esc_html')->alias(static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES));
});

it('renders the brand logo when one is configured', function () {
    // The branch that could never run before. It is reachable now because MailServiceProvider::brand()
    // supplies a `logo` key — the theme's custom logo, falling back to the site icon.
    $html = (new Layout([
        'name' => 'Acme',
        'logo' => 'https://example.test/wp-content/uploads/logo.png',
    ]))->wrap('Subject', '<p>Body</p>');

    expect($html)->toContain('<img')
        ->and($html)->toContain('https://example.test/wp-content/uploads/logo.png')
        ->and($html)->toContain('alt="Acme"');
});

it('falls back to the site name when there is no logo', function () {
    $html = (new Layout(['name' => 'Acme', 'logo' => '']))->wrap('Subject', '<p>Body</p>');

    expect($html)->not->toContain('<img')
        ->and($html)->toContain('<strong>Acme</strong>');
});

it('escapes a logo URL rather than trusting it into an attribute', function () {
    $html = (new Layout([
        'name' => 'Acme',
        'logo' => 'https://example.test/a.png" onerror="alert(1)',
    ]))->wrap('Subject', '<p>Body</p>');

    expect($html)->not->toContain('onerror="alert(1)"');
});

it('wraps a reply body in the shell rather than sending it bare', function () {
    // What reply() now does with the operator's text. The assertion is that the body arrives
    // *inside* a document — before this, the operator's fragment was the whole message.
    $body = '<p>Thanks for getting in touch.</p>';
    $html = (new Layout(['name' => 'Acme', 'dir' => 'ltr']))->wrap('Re: your enquiry', $body);

    expect($html)->toContain('<!DOCTYPE html>')
        ->and($html)->toContain($body)
        ->and(strpos($html, $body))->toBeGreaterThan(strpos($html, '<body'));
});

it('carries the brand direction into the document, so an RTL site sends RTL email', function () {
    $html = (new Layout(['name' => 'Acme', 'dir' => 'rtl']))->wrap('Subject', '<p>Body</p>');

    expect($html)->toContain('<html dir="rtl">');
});
