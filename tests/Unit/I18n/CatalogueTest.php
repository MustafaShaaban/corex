<?php

/**
 * CoreX's catalogue is loaded, and its script translations found, even where WordPress would not
 * (spec 121, US-NFR-02, DECISIONS #466).
 *
 * @package Corex\Tests\Unit\I18n
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\I18n\Catalogue;
use Corex\I18n\ScriptTranslations;
use Corex\Tests\Support\ThemeContract;

beforeEach(function () {
    $this->folder = sys_get_temp_dir() . '/corex-catalogue-' . bin2hex(random_bytes(4));
    mkdir($this->folder . '/packs', 0777, true);
    mkdir($this->folder . '/shipped', 0777, true);
    Functions\when('determine_locale')->justReturn('ar');
});

afterEach(function () {
    array_map('unlink', glob($this->folder . '/*/*') ?: []);
    array_map('rmdir', glob($this->folder . '/*') ?: []);
    rmdir($this->folder);
});

/** The catalogue under test; it always names CoreX's folder to WordPress first. */
function corex_catalogue(object $test): Catalogue
{
    Functions\expect('load_plugin_textdomain')->once()->with('corex', false, 'corex-core/languages');

    return new Catalogue($test->folder . '/shipped', 'corex-core/languages', $test->folder . '/packs');
}

it('leaves the catalogue to WordPress when WordPress loaded it', function () {
    Functions\when('is_textdomain_loaded')->justReturn(true);
    Functions\expect('load_textdomain')->never();

    corex_catalogue($this)->load();
});

it('loads the catalogue CoreX ships when WordPress kept an empty one for the domain', function () {
    touch($this->folder . '/shipped/corex-ar.mo');
    Functions\when('is_textdomain_loaded')->justReturn(false);
    Functions\expect('load_textdomain')->once()->with('corex', $this->folder . '/shipped/corex-ar.mo', 'ar')->andReturn(true);

    corex_catalogue($this)->load();
});

it('prefers a language pack the site installed over the one CoreX ships', function () {
    touch($this->folder . '/packs/corex-ar.mo');
    touch($this->folder . '/shipped/corex-ar.mo');
    Functions\when('is_textdomain_loaded')->justReturn(false);
    Functions\expect('load_textdomain')->once()->with('corex', $this->folder . '/packs/corex-ar.mo', 'ar')->andReturn(true);

    corex_catalogue($this)->load();
});

it('loads nothing for a language CoreX has no catalogue in', function () {
    Functions\when('is_textdomain_loaded')->justReturn(false);
    Functions\expect('load_textdomain')->never();

    corex_catalogue($this)->load();
});

it('answers a CoreX script with the translations CoreX ships when WordPress has none', function () {
    $name = 'corex-ar-' . md5('build/inbox.js') . '.json';
    touch($this->folder . '/shipped/' . $name);
    $translations = new ScriptTranslations($this->folder . '/shipped');

    expect($translations->locate('/wp-content/languages/plugins/' . $name, 'corex-inbox', 'corex'))
        ->toBe($this->folder . '/shipped/' . $name)
        ->and($translations->locate('/wp-content/languages/plugins/' . $name, 'other', 'other-domain'))
        ->toBe('/wp-content/languages/plugins/' . $name)
        ->and($translations->locate('/wp-content/languages/plugins/corex-ar-corex-inbox.json', 'corex-inbox', 'corex'))
        ->toBe('/wp-content/languages/plugins/corex-ar-corex-inbox.json');
});

it('keeps a translation file the site installed for a CoreX script', function () {
    $name = 'corex-ar-' . md5('build/inbox.js') . '.json';
    touch($this->folder . '/packs/' . $name);
    touch($this->folder . '/shipped/' . $name);

    expect((new ScriptTranslations($this->folder . '/shipped'))->locate($this->folder . '/packs/' . $name, 'corex-inbox', 'corex'))
        ->toBe($this->folder . '/packs/' . $name);
});

it('ships an Arabic catalogue compiled from its source', function () {
    $po = ThemeContract::root() . '/plugins/corex-core/languages/corex-ar.po';
    $mo = ThemeContract::root() . '/plugins/corex-core/languages/corex-ar.mo';

    // The .mo is checked byte for byte against the .po by `node scripts/i18n-arabic.mjs check`
    // (tests/i18n-arabic.test.js); here, that both are where corex-core loads them from.
    expect($po)->toBeFile()->and($mo)->toBeFile()
        ->and((string) file_get_contents($po))->toContain('"Language: ar\n"')->toContain('nplurals=6');
});
