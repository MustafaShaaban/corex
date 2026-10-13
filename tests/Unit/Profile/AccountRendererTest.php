<?php

/**
 * Unit tests for the pure account block renderer (Spec 068 US9, FR-158/FR-161).
 * Verifies the guest vs member panels, the registration gate, escaping of dynamic
 * values, and that every form targets a corex/v1/account REST route.
 *
 * @package Corex\Tests\Unit\Profile
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Profile\Block\AccountRenderer;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_attr__')->returnArg();
    Functions\when('esc_html')->alias(fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES));
    Functions\when('esc_attr')->alias(fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES));
    Functions\when('esc_url')->alias(fn ($v) => (string) $v);
});

it('renders the guest login, register, and recovery forms when registration is open', function () {
    $html = (new AccountRenderer())->render([
        'loggedIn'         => false,
        'registrationOpen' => true,
        'nonce'            => 'abc123',
    ]);

    expect($html)->toContain('data-corex-account-form="login"')
        ->and($html)->toContain('data-corex-account-form="register"')
        ->and($html)->toContain('data-corex-account-form="reset-request"')
        ->and($html)->toContain('name="corex_hp"')       // spam honeypot present
        ->and($html)->toContain('data-nonce="abc123"');
});

it('hides the register form and explains when registration is closed', function () {
    $html = (new AccountRenderer())->render([
        'loggedIn'         => false,
        'registrationOpen' => false,
    ]);

    expect($html)->not->toContain('data-corex-account-form="register"')
        ->and($html)->toContain('Registration is currently closed.');
});

it('renders the member profile, sessions, and notifications panels', function () {
    $html = (new AccountRenderer())->render([
        'loggedIn'    => true,
        'displayName' => 'Jane Doe',
        'email'       => 'jane@example.com',
        'logoutUrl'   => 'https://example.com/logout',
    ]);

    expect($html)->toContain('data-corex-account-form="profile"')
        ->and($html)->toContain('data-corex-account-sessions')
        ->and($html)->toContain('data-corex-account-notifications')
        ->and($html)->toContain('value="jane@example.com"')   // profile prefilled
        ->and($html)->toContain('https://example.com/logout')
        ->and($html)->not->toContain('data-corex-account-form="login"');
});

it('escapes a hostile display name', function () {
    $html = (new AccountRenderer())->render([
        'loggedIn'    => true,
        'displayName' => '<script>alert(1)</script>',
    ]);

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->toContain('&lt;script&gt;');
});

/**
 * The message lines of a rendered panel, each with the form or section that holds it.
 *
 * @return list<array{holder:string,afterButton:bool,hidden:bool,live:string}>
 */
function accountMessageLines(string $html): array
{
    $document = new DOMDocument();
    $document->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);

    $lines = [];
    foreach ((new DOMXPath($document))->query('//*[@role="status"]') as $line) {
        $holder   = $line->parentNode;
        $previous = $line->previousSibling;
        $lines[]  = [
            'holder'      => $holder->getAttribute('data-corex-account-form')
                ?: ($holder->hasAttribute('data-corex-account-sessions') ? 'sessions' : $holder->nodeName),
            'afterButton' => $previous !== null
                && (new DOMXPath($document))->query('descendant-or-self::button', $previous)->length > 0,
            'hidden'      => $line->hasAttribute('hidden'),
            'live'        => $line->getAttribute('aria-live'),
        ];
    }

    return $lines;
}

it('gives each guest form a message line of its own, straight after its button', function () {
    $lines = accountMessageLines((new AccountRenderer())->render([
        'loggedIn'         => false,
        'registrationOpen' => true,
    ]));

    expect(array_column($lines, 'holder'))->toBe(['login', 'register', 'reset-request'])
        ->and(array_column($lines, 'afterButton'))->toBe([true, true, true]);
});

it('gives the profile form and the session buttons a message line each', function () {
    $lines = accountMessageLines((new AccountRenderer())->render(['loggedIn' => true]));

    expect(array_column($lines, 'holder'))->toBe(['profile', 'sessions'])
        ->and(array_column($lines, 'afterButton'))->toBe([true, true]);
});

it('keeps an empty message line in the page, so a screen reader is told when a message arrives', function () {
    $lines = accountMessageLines((new AccountRenderer())->render([
        'loggedIn'         => false,
        'registrationOpen' => true,
    ]));

    expect(array_column($lines, 'hidden'))->toBe([false, false, false])
        ->and(array_column($lines, 'live'))->toBe(['polite', 'polite', 'polite']);
});
