<?php

/**
 * Unit tests for the self-contained CoreX interstitial renderer (Spec 068): the branded,
 * external-stylesheet-free document used by Maintenance mode (503) and the menu-level
 * access-denied / request-access page (403).
 *
 * @package Corex\Tests\Unit\Admin
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Admin\StandalonePage;

beforeEach(function () {
    // Only the always-called escaping helpers are stubbed. get_bloginfo/is_rtl/apply_filters
    // are intentionally left undefined so StandalonePage takes its documented headless
    // fallbacks (en-US / UTF-8 / ltr / system) — and, crucially, so this unit test never
    // defines those functions, which would flip function_exists() guards for later tests.
    Functions\when('esc_attr')->returnArg();
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_url')->returnArg();
});

function standalonePage(): StandalonePage
{
    // Bind to the real corex-core asset directory so the inlined CSS is the shipped source.
    $assets = dirname(__DIR__, 3) . '/plugins/corex-core/assets';

    return new StandalonePage($assets, 'http://example.test/plugins/corex-core/assets');
}

it('renders a complete self-contained HTML document', function () {
    $html = standalonePage()->document('We are away', '<main>Body copy</main>');

    expect($html)->toStartWith('<!DOCTYPE html>')
        ->and($html)->toContain('lang="en-US"')
        ->and($html)->toContain('dir="ltr"')
        ->and($html)->toContain('<meta name="robots" content="noindex, nofollow"')
        ->and($html)->toContain('<title>We are away</title>')
        ->and($html)->toContain('<main>Body copy</main>')
        ->and($html)->toContain('</body></html>');
});

it('asks crawlers to stay away unless the page is one meant to be found', function () {
    // Every page this class rendered before spec 101 was an interstitial — a 503, a 403 — and
    // noindex was right for all of them. The coming-soon fallback is a public launch page served
    // with a 200, and a noindex on it would contradict the reason the mode exists (FR-010).
    $interstitial = standalonePage()->document('Away', '<main></main>');
    $launchPage   = standalonePage()->document('Coming soon', '<main></main>', 'coming-soon', indexable: true);

    expect($interstitial)->toContain('<meta name="robots" content="noindex, nofollow"')
        ->and($launchPage)->not->toContain('name="robots"')
        ->and($launchPage)->not->toContain('noindex');
});

it('inlines the token adapter and the standalone stylesheet', function () {
    $html = standalonePage()->document('Away', '<main></main>');

    // A token from corex-admin-tokens.css and a rule from corex-admin-standalone.css.
    expect($html)->toContain('--corex-admin-shell:')
        ->and($html)->toContain('.corex-standalone__card')
        // The body carries the token-bearing classes so the inlined adapter resolves.
        ->and($html)->toContain('class="corex-standalone corex-admin-screen');
});

it('absolutizes the adapter font URLs so brand fonts resolve when inlined', function () {
    $html = standalonePage()->document('Away', '<main></main>');

    expect($html)->toContain('url(http://example.test/plugins/corex-core/assets/fonts/')
        ->and($html)->not->toContain('url(../fonts/');
});

it('adds a sanitized variant modifier class', function () {
    $html = standalonePage()->document('Away', '<main></main>', 'maintenance');

    expect($html)->toContain('corex-standalone--maintenance');
});

it('strips unsafe characters from the variant modifier', function () {
    $html = standalonePage()->document('Away', '<main></main>', 'den"ied<x');

    expect($html)->toContain('corex-standalone--deniedx')
        ->and($html)->not->toContain('den"ied<x');
});

it('renders a branded notice page with a back action', function () {
    $html = standalonePage()->notice(
        'Access denied',
        'You are not allowed to do that.',
        'http://example.test/wp-admin/admin.php?page=corex-data',
        'Back to Data',
    );

    // Assert on markup, not bare class names — the inlined <style> block mentions every class.
    expect($html)->toStartWith('<!DOCTYPE html>')
        ->and($html)->toContain('corex-standalone--notice')
        ->and($html)->toContain('<h1 class="corex-standalone__title">Access denied</h1>')
        ->and($html)->toContain('You are not allowed to do that.')
        ->and($html)->toContain('<div class="corex-standalone__actions">')
        ->and($html)->toContain('href="http://example.test/wp-admin/admin.php?page=corex-data"')
        ->and($html)->toContain('Back to Data');
});

it('omits the back action when no url is given', function () {
    $html = standalonePage()->notice('Not found', 'That resource is missing.');

    // The actions <div> (body markup) is absent even though the CSS class is inlined in <style>.
    expect($html)->toContain('That resource is missing.')
        ->and($html)->not->toContain('<div class="corex-standalone__actions">');
});

it('exposes the shared brand mark as an inline currentColor SVG', function () {
    expect(StandalonePage::brandMark())->toContain('<svg')
        ->and(StandalonePage::brandMark())->toContain('fill="currentColor"');
});

/**
 * The copy field (spec 101, FR-011a). The escaping helpers escape here as WordPress's do, because
 * what is under test is which of them each value goes through.
 */
function standaloneCopyField(string $value = 'http://example.test/?corex_preview=abc'): string
{
    Functions\when('esc_attr')->alias(static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES));
    Functions\when('esc_html')->alias(static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES));
    Functions\when('esc_attr__')->alias(static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES));
    Functions\when('wp_get_inline_script_tag')
        ->alias(static fn (string $script): string => '<script data-through-wordpress>' . $script . '</script>');

    return standalonePage()->copyField('corex-preview-link', 'Preview link', $value, 'Copy link');
}

it('holds a value to be copied in a labelled read-only field', function () {
    $field = standaloneCopyField();

    expect($field)->toContain('<label class="corex-standalone__copy-label" for="corex-preview-link">Preview link</label>')
        ->and($field)->toMatch('/<input[^>]* id="corex-preview-link"[^>]* readonly[^>]* value="http:\/\/example\.test\/\?corex_preview=abc"/')
        // An address reads left to right on a page written right to left.
        ->and($field)->toMatch('/<input[^>]* dir="ltr"/');
});

it('escapes the value as an attribute, since that is where it is written', function () {
    $field = standaloneCopyField('http://example.test/?a=1&b="><script>x</script>');

    expect($field)->toContain('value="http://example.test/?a=1&amp;b=&quot;&gt;&lt;script&gt;x&lt;/script&gt;"')
        ->and($field)->not->toContain('<script>x</script>');
});

it('writes the copy button hidden, for the script to show where it runs', function () {
    $field = standaloneCopyField();

    expect($field)->toMatch('/<button type="button"[^>]* data-corex-copy-button[^>]* hidden>/')
        ->and($field)->toContain('<span>Copy link</span>')
        // The icon says nothing a second time: the button's name is its words.
        ->and($field)->toMatch('/<svg[^>]* aria-hidden="true"[^>]* focusable="false"/');
});

it('has an empty polite status for what the button did, and the words for it', function () {
    $field = standaloneCopyField();

    expect($field)->toContain('role="status" aria-live="polite" data-corex-copy-status></p>')
        ->and($field)->toMatch('/data-copied="[^"]+"/')
        ->and($field)->toMatch('/data-manual="[^"]+"/');
});

it('carries its script inline, through the WordPress function a site adds a nonce to', function () {
    $field  = standaloneCopyField();
    $script = (string) file_get_contents(dirname(__DIR__, 3) . '/plugins/corex-core/assets/js/corex-copy-field.js');

    expect($script)->not->toBe('')
        ->and($field)->toContain('<script data-through-wordpress>' . $script . '</script>')
        // The value is in the field and nowhere else: not in the script, not in a data attribute.
        ->and(substr_count($field, 'corex_preview=abc'))->toBe(1);
});
