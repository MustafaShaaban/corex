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
