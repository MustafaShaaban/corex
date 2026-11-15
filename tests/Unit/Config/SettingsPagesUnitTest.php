<?php

/**
 * The Settings pages (spec 119, US-SET-01 and US-SET-07): every section is on one page, a page is
 * found by its own address or by the older `corex_tab` link, and an add-on's section is a page of
 * its own.
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Settings\SettingsPages;
use Corex\Config\Settings\SettingsRegistry;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

it('puts every registry section on exactly one page', function () {
    $pages    = new SettingsPages();
    $sections = [];
    foreach ($pages->all() as $page) {
        $sections = [...$sections, ...$page->sections];
    }

    sort($sections);
    $declared = array_keys((new SettingsRegistry())->sections());
    sort($declared);

    expect($sections)->toBe($declared);
});

it('lists the pages in the order the design groups them', function () {
    expect(array_keys((new SettingsPages())->all()))
        ->toBe(['general', 'email', 'captcha', 'media', 'insights', 'guides', 'advanced']);
});

it('finds the page an address asks for', function (string $page, string $tab, ?string $found) {
    expect((new SettingsPages())->requested($page, $tab)?->key)->toBe($found);
})->with([
    'nothing asked: the first page'       => ['', '', 'general'],
    'a page by its key'                   => ['captcha', '', 'captcha'],
    'an older link to a section'          => ['', 'brand', 'general'],
    'an older link to Mail'               => ['', 'mail', 'email'],
    'an older link to the Dashboard'      => ['', 'dashboard', 'general'],
    'a page that does not exist'          => ['nowhere', '', null],
    'an older link that names no section' => ['', 'nowhere', null],
]);

it('gives each add-on its own page, named and linked to its card', function () {
    $pages = new SettingsPages();

    foreach (['captcha' => 'corex-captcha', 'media' => 'corex-media', 'guides' => 'corex-guides'] as $key => $slug) {
        $page = $pages->find($key);

        expect($page?->sections)->toBe([$key])
            ->and($page?->addon?->slug)->toBe($slug)
            ->and($page?->addon?->pluginFile)->toBe($slug . '/' . $slug . '.php')
            ->and($page?->addon?->cardAnchor())->toBe('corex-addon-' . $slug);
    }

    expect($pages->find('general')?->addon)->toBeNull()
        ->and($pages->find('insights')?->addon)->toBeNull();
});
