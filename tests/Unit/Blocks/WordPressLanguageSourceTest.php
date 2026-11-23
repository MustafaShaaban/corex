<?php

/**
 * The language source CoreX ships (spec 137, FR-029, FR-030, FR-042; DECISIONS #694).
 *
 * It reads WordPress alone, which has one language: the request's locale, its direction, and the
 * address of the page asked for. No multilingual plugin is called or looked for, nothing is fetched
 * over the network, and nothing is found by its slug.
 *
 * @package Corex\Tests\Unit\Blocks
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Blocks\BlocksServiceProvider;
use Corex\Blocks\Languages\Language;
use Corex\Blocks\Languages\LanguageSource;
use Corex\Blocks\Languages\WordPressLanguageSource;
use Corex\Container\Container;

/**
 * The one language the shipped source returns for a request in `$locale`.
 *
 * @return list<Language>
 */
function wordPressLanguages(string $locale, bool $rtl = false, ?string $requestUri = '/about/?ref=1'): array
{
    Functions\when('determine_locale')->justReturn($locale);
    Functions\when('is_rtl')->justReturn($rtl);
    Functions\when('home_url')->alias(static fn (string $path = ''): string => 'https://site.test' . $path);
    Functions\when('get_self_link')->justReturn('https://site.test' . (string) $requestUri);

    $previous = $_SERVER['REQUEST_URI'] ?? null;
    if ($requestUri === null) {
        unset($_SERVER['REQUEST_URI']);
    } else {
        $_SERVER['REQUEST_URI'] = $requestUri;
    }

    try {
        return (new WordPressLanguageSource())->languages();
    } finally {
        if ($previous === null) {
            unset($_SERVER['REQUEST_URI']);
        } else {
            $_SERVER['REQUEST_URI'] = $previous;
        }
    }
}

it('FR-029, FR-030: returns the site\'s one language, current, with this page\'s address', function () {
    $languages = wordPressLanguages('en_US');

    expect($languages)->toHaveCount(1);

    $english = $languages[0];
    expect($english)->toBeInstanceOf(Language::class)
        ->and($english->code)->toBe('en-US')
        ->and($english->name)->toBe('English')
        ->and($english->direction)->toBe('ltr')
        ->and($english->url)->toBe('https://site.test/about/?ref=1')
        ->and($english->current)->toBeTrue();
});

it('FR-029: names a right-to-left language in its own script and gives its direction', function () {
    $arabic = wordPressLanguages('ar', rtl: true)[0];

    expect($arabic->code)->toBe('ar')
        ->and($arabic->name)->toBe('العربية')
        ->and($arabic->direction)->toBe('rtl');
});

it('FR-029: falls back to the home page when no page was asked for (the command line, a cron run)', function () {
    expect(wordPressLanguages('en_US', requestUri: null)[0]->url)->toBe('https://site.test/');
});

it('FR-030, FR-042: calls no multilingual plugin, fetches nothing and finds nothing by its slug', function () {
    $source = (string) file_get_contents(dirname(__DIR__, 3) . '/plugins/corex-blocks/src/Languages/WordPressLanguageSource.php');
    $code = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $source) ?? '';

    foreach ([
        'pll_', 'icl_', 'wpml', 'polylang', 'function_exists', 'class_exists',
        'wp_get_available_translations', 'translations_api', 'wp_remote_', 'download_url',
        'get_page_by_path', 'get_page_by_title', 'get_term_by', 'wp_get_nav_menu_object',
    ] as $forbidden) {
        expect(stripos($code, $forbidden))->toBeFalse("the shipped source must not use {$forbidden}");
    }
});

it('FR-029: is bound in the container, so a site or a later spec replaces it without touching the block', function () {
    $container = new Container();
    (new BlocksServiceProvider($container))->register();

    expect($container->make(LanguageSource::class))->toBeInstanceOf(WordPressLanguageSource::class);

    $replacement = new class () implements LanguageSource {
        public function languages(): array
        {
            return [];
        }
    };
    $container->instance(LanguageSource::class, $replacement);

    expect($container->make(LanguageSource::class))->toBe($replacement);
});

it('FR-029: a language reads any direction but right to left as left to right', function () {
    expect((new Language('fr', 'Français', 'sideways', 'https://site.test/fr/', false))->direction)->toBe('ltr')
        ->and((new Language('he', 'עברית', 'RTL', 'https://site.test/he/', false))->direction)->toBe('rtl');
});
