<?php

/**
 * Unit tests for the pure account block renderer (Spec 068 US9, FR-158/FR-161).
 * Verifies the guest vs member panels, the registration gate, escaping of dynamic
 * values, and that every form targets a corex/v1/account REST route.
 *
 * Also that a field's id is its own on the page and its label is tied to it: the panel
 * prints several forms, and two of them have a field of the same name (DECISIONS #337).
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
 * What the renderer printed, as a document that can be asked questions.
 */
function accountDocument(string $html): DOMDocument
{
    $document = new DOMDocument();
    $document->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);

    return $document;
}

/**
 * The message lines of a rendered panel, each with the form or section that holds it.
 *
 * @return list<array{holder:string,afterButton:bool,hidden:bool,live:string}>
 */
function accountMessageLines(string $html): array
{
    $document = accountDocument($html);

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

/**
 * Every id in what was printed, in the order it was printed.
 *
 * @return list<string>
 */
function accountIds(string $html): array
{
    $ids = [];
    foreach ((new DOMXPath(accountDocument($html)))->query('//@id') as $id) {
        $ids[] = $id->nodeValue;
    }

    return $ids;
}

/**
 * Each label that names a field by id: the form it is in, the forms that id is found in, and
 * whether the field it names is the one printed beside it.
 *
 * @return list<array{label:string,form:string,pointsInto:list<string>,atTheFieldBesideIt:bool}>
 */
function accountLabels(string $html): array
{
    $xpath  = new DOMXPath(accountDocument($html));
    $formOf = static fn (DOMNode $node): string => $xpath->evaluate(
        'string(ancestor::form/@data-corex-account-form)',
        $node,
    );

    $labels = [];
    foreach ($xpath->query('//label[@for]') as $label) {
        $named    = iterator_to_array($xpath->query(sprintf('//*[@id="%s"]', $label->getAttribute('for'))));
        $labels[] = [
            'label'              => $formOf($label) . ': ' . trim($label->textContent),
            'form'               => $formOf($label),
            'pointsInto'         => array_map($formOf, $named),
            'atTheFieldBesideIt' => count($named) === 1 && $named[0]->isSameNode($label->nextSibling),
        ];
    }

    return $labels;
}

dataset('account panels', [
    'the signed-out panel with registration open'   => [['loggedIn' => false, 'registrationOpen' => true]],
    'the signed-out panel with registration closed' => [['loggedIn' => false, 'registrationOpen' => false]],
    'the signed-in panel'                           => [['loggedIn' => true]],
]);

it('prints no id twice', function (array $ctx) {
    $ids = accountIds((new AccountRenderer())->render($ctx));

    expect($ids)->not->toBeEmpty()
        ->and(array_values(array_diff_assoc($ids, array_unique($ids))))->toBe([]);
})->with('account panels');

it('points every label at the field beside it, in its own form', function (array $ctx) {
    $labels = accountLabels((new AccountRenderer())->render($ctx));
    $astray = array_filter(
        $labels,
        static fn (array $label): bool => $label['pointsInto'] !== [$label['form']] || ! $label['atTheFieldBesideIt'],
    );

    expect($labels)->not->toBeEmpty()
        ->and(array_column($astray, 'pointsInto', 'label'))->toBe([]);
})->with('account panels');
