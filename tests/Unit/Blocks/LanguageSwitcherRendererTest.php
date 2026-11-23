<?php

/**
 * The language switcher block's render (spec 137, User Story 5 = US-NAV-05; FR-031, FR-032, FR-032a,
 * FR-042; DECISIONS #694, #697, #702).
 *
 * The block asks a language source for the request's languages. With fewer than two it prints
 * nothing; with two or more it prints the form an editor chose: the menu (a button naming the current
 * language over the kit's Dropdown, the list without script), the list (every language in a row) or
 * the toggle (one link naming the other language, the list with three or more).
 *
 * @package Corex\Tests\Unit\Blocks
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Blocks\Languages\Language;
use Corex\Blocks\Languages\LanguageSource;
use Corex\Blocks\LanguageSwitcherRenderer;

/** A source that returns the languages it was given. */
function languageSourceOf(Language ...$languages): LanguageSource
{
    return new class (array_values($languages)) implements LanguageSource {
        /** @param list<Language> $languages */
        public function __construct(private readonly array $languages)
        {
        }

        public function languages(): array
        {
            return $this->languages;
        }
    };
}

function switcherEnglish(bool $current = true): Language
{
    return new Language('en', 'English', 'ltr', 'https://site.test/en/about/', $current);
}

function switcherArabic(bool $current = false): Language
{
    return new Language('ar', 'العربية', 'rtl', 'https://site.test/ar/about/', $current);
}

function switcherFrench(): Language
{
    return new Language('fr', 'Français', 'ltr', 'https://site.test/fr/', false);
}

/** The switcher rendered with `$attributes` over `$languages`, WordPress's escaping stood in for. */
function renderLanguageSwitcher(array $attributes, Language ...$languages): string
{
    $html = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    Functions\when('__')->returnArg();
    Functions\when('esc_html__')->alias($html);
    Functions\when('esc_attr__')->alias($html);
    Functions\when('esc_html')->alias($html);
    Functions\when('esc_attr')->alias($html);
    Functions\when('esc_url')->alias(
        static fn (string $url): string => preg_match('#^https?://#i', $url) === 1 ? $html($url) : '',
    );
    // WordPress counts its ids over the whole request; so does this stand-in, across renders.
    Functions\when('wp_unique_id')->alias(static function (string $prefix = ''): string {
        $GLOBALS['corex_test_switcher_ids'] = ($GLOBALS['corex_test_switcher_ids'] ?? 0) + 1;

        return $prefix . (string) $GLOBALS['corex_test_switcher_ids'];
    });

    return (new LanguageSwitcherRenderer(languageSourceOf(...$languages)))->render($attributes, '', new stdClass());
}

function switcherDom(string $html): DOMXPath
{
    $document = new DOMDocument();
    libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8"?><body>' . $html . '</body>');
    libxml_clear_errors();

    return new DOMXPath($document);
}

function switcherHasClass(string $class): string
{
    return "contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')";
}

it('FR-031: prints nothing, not even its wrapper, with one language or none, in every form', function () {
    foreach (['menu', 'list', 'toggle'] as $form) {
        expect(renderLanguageSwitcher(['form' => $form], switcherEnglish()))->toBe('')
            ->and(renderLanguageSwitcher(['form' => $form]))->toBe('');
    }
});

it('FR-032: the menu form is a button naming the current language over a list of every language', function () {
    $xpath = switcherDom(renderLanguageSwitcher(['form' => 'menu'], switcherEnglish(), switcherArabic()));

    $root = $xpath->query('/html/body/*');
    expect($root->length)->toBe(1)
        ->and($root->item(0)->getAttribute('class'))->toBe('corex-language-switcher corex-language-switcher--menu');

    $button = $xpath->query('//div/button')->item(0);
    expect($button)->not->toBeNull()
        ->and($button->getAttribute('type'))->toBe('button')
        ->and($button->getAttribute('class'))->toContain('wp-element-button')
        ->and($button->getAttribute('aria-expanded'))->toBe('false');

    $name = $xpath->query('//button/span[@lang]')->item(0);
    expect($name->textContent)->toBe('English')
        ->and($name->getAttribute('lang'))->toBe('en')
        ->and($name->getAttribute('dir'))->toBe('ltr')
        // The chevron is drawn, and hidden from assistive technology.
        ->and($xpath->query('//button/svg[@aria-hidden="true"]')->length)->toBe(1);

    $list = $xpath->query('//ul')->item(0);
    expect($list->getAttribute('id'))->not->toBe('')
        ->and($button->getAttribute('aria-controls'))->toBe($list->getAttribute('id'))
        ->and($list->getAttribute('class'))->toContain('corex-language-switcher__list')
        ->and($list->getAttribute('class'))->toContain('corex-dropdown__menu')
        ->and($xpath->query('//ul/li')->length)->toBe(2);
});

it('FR-032: the current language is ticked, marked aria-current and is not a link; the others are links', function () {
    foreach (['menu', 'list'] as $form) {
        $xpath = switcherDom(renderLanguageSwitcher(['form' => $form], switcherEnglish(), switcherArabic()));

        $current = $xpath->query('//li/*[@aria-current="true"]');
        expect($current->length)->toBe(1);
        expect($current->item(0)->nodeName)->toBe('span')
            ->and($current->item(0)->getAttribute('class'))->toContain('corex-language-switcher__item')
            ->and(trim($current->item(0)->textContent))->toBe('English')
            ->and($xpath->query('//li/span[@aria-current="true"]/svg[@aria-hidden="true"]')->length)->toBe(1);

        $links = $xpath->query('//li/a');
        expect($links->length)->toBe(1);
        expect($links->item(0)->getAttribute('href'))->toBe('https://site.test/ar/about/')
            ->and($links->item(0)->getAttribute('class'))->toContain('corex-language-switcher__item')
            ->and($links->item(0)->hasAttribute('aria-current'))->toBeFalse();
    }
});

it('FR-032: the list form is every language in a row, with no button', function () {
    $xpath = switcherDom(renderLanguageSwitcher(['form' => 'list'], switcherEnglish(), switcherArabic(), switcherFrench()));

    expect($xpath->query('/html/body/div')->item(0)->getAttribute('class'))
        ->toBe('corex-language-switcher corex-language-switcher--list')
        ->and($xpath->query('//button')->length)->toBe(0)
        ->and($xpath->query('//ul[' . switcherHasClass('corex-language-switcher__list') . ']/li')->length)->toBe(3)
        ->and($xpath->query('//ul[' . switcherHasClass('corex-dropdown__menu') . ']')->length)->toBe(0)
        ->and($xpath->query('//li/a')->length)->toBe(2);
});

it('FR-032: each name carries its own lang and dir, and a link its hreflang; a row keeps the page\'s direction', function () {
    foreach (['menu', 'list'] as $form) {
        $xpath = switcherDom(renderLanguageSwitcher(['form' => $form], switcherEnglish(), switcherArabic(), switcherFrench()));

        foreach ($xpath->query('//li/*') as $item) {
            // The row has no direction of its own, so the list's rows line up; its name has one.
            expect($item->hasAttribute('dir'))->toBeFalse();

            $names = $xpath->query('./span[@lang]', $item);
            expect($names->length)->toBe(1)
                ->and($names->item(0)->getAttribute('dir'))->toBeIn(['ltr', 'rtl']);
        }

        $arabic = $xpath->query('//li/a[@hreflang="ar"]')->item(0);
        $name = $xpath->query('./span', $arabic)->item(0);
        expect($name->getAttribute('lang'))->toBe('ar')
            ->and($name->getAttribute('dir'))->toBe('rtl')
            ->and($arabic->textContent)->toBe('العربية');
    }
});

it('FR-032a, SC-010: the toggle with English current prints one link, "العربية", whose name is its text', function () {
    $xpath = switcherDom(renderLanguageSwitcher(['form' => 'toggle'], switcherEnglish(), switcherArabic()));

    expect($xpath->query('/html/body/div')->item(0)->getAttribute('class'))
        ->toBe('corex-language-switcher corex-language-switcher--toggle');

    $links = $xpath->query('//a');
    expect($links->length)->toBe(1);

    $link = $links->item(0);
    expect($link->parentNode->nodeName)->toBe('div')
        ->and($link->getAttribute('class'))->toBe('corex-language-switcher__item')
        ->and($link->getAttribute('href'))->toBe('https://site.test/ar/about/')
        ->and($link->getAttribute('lang'))->toBe('ar')
        ->and($link->getAttribute('dir'))->toBe('rtl')
        ->and($link->getAttribute('hreflang'))->toBe('ar')
        ->and($link->textContent)->toBe('العربية')
        // Its visible text is its whole accessible name: nothing names it otherwise, nothing is hidden in it.
        ->and($link->hasAttribute('aria-label'))->toBeFalse()
        ->and($link->hasAttribute('title'))->toBeFalse()
        ->and($link->childNodes->length)->toBe(1)
        // No tint, no tick, no current item and no script hook.
        ->and($xpath->query('//*[@aria-current]')->length)->toBe(0)
        ->and($xpath->query('//svg')->length)->toBe(0)
        ->and($xpath->query('//button')->length)->toBe(0);
});

it('FR-032a: the toggle on an Arabic page reads "English", in its own lang and dir', function () {
    $link = switcherDom(renderLanguageSwitcher(['form' => 'toggle'], switcherEnglish(current: false), switcherArabic(current: true)))
        ->query('//a')->item(0);

    expect($link->textContent)->toBe('English')
        ->and($link->getAttribute('lang'))->toBe('en')
        ->and($link->getAttribute('dir'))->toBe('ltr')
        ->and($link->getAttribute('hreflang'))->toBe('en')
        ->and($link->getAttribute('href'))->toBe('https://site.test/en/about/');
});

it('FR-032a, SC-010: the toggle with three languages prints the list form', function () {
    $xpath = switcherDom(renderLanguageSwitcher(['form' => 'toggle'], switcherEnglish(), switcherArabic(), switcherFrench()));

    expect($xpath->query('/html/body/div')->item(0)->getAttribute('class'))
        ->toBe('corex-language-switcher corex-language-switcher--list')
        ->and($xpath->query('//li')->length)->toBe(3)
        ->and($xpath->query('//li/span[@aria-current="true"]')->length)->toBe(1);
});

it('FR-032: the menu is the form when none is chosen, or an unknown one is', function () {
    foreach ([[], ['form' => 'dropdown'], ['form' => 3]] as $attributes) {
        expect(renderLanguageSwitcher($attributes, switcherEnglish(), switcherArabic()))
            ->toContain('corex-language-switcher--menu');
    }
});

it('FR-032: with no language marked current, the menu and the toggle print the list, every language a link', function () {
    foreach (['menu', 'toggle'] as $form) {
        $xpath = switcherDom(renderLanguageSwitcher(['form' => $form], switcherEnglish(current: false), switcherArabic()));

        expect($xpath->query('/html/body/div')->item(0)->getAttribute('class'))->toContain('--list')
            ->and($xpath->query('//li/a')->length)->toBe(2);
    }
});

it('FR-032: two menus on one page control their own lists', function () {
    $first = switcherDom(renderLanguageSwitcher([], switcherEnglish(), switcherArabic()))->query('//ul')->item(0)->getAttribute('id');
    $second = switcherDom(renderLanguageSwitcher([], switcherEnglish(), switcherArabic()))->query('//ul')->item(0)->getAttribute('id');

    expect($first)->not->toBe($second);
});

it('FR-042: escapes every name, code and address it prints', function () {
    $hostile = new Language(
        'x" onmouseover="alert(1)',
        '<img src=x onerror=alert(1)>',
        'ltr',
        'javascript:alert(1)',
        false,
    );
    $quoted = new Language('de', 'Deutsch "neu"', 'ltr', 'https://site.test/?a=1&b="2"', false);

    foreach (['menu', 'list', 'toggle'] as $form) {
        $html = renderLanguageSwitcher(['form' => $form], switcherEnglish(), $hostile, $quoted);

        expect($html)->not->toContain('<img')
            ->and($html)->not->toContain('onmouseover="')
            ->and($html)->not->toContain('javascript:')
            ->and($html)->toContain('&lt;img src=x onerror=alert(1)&gt;')
            ->and($html)->toContain('Deutsch &quot;neu&quot;')
            ->and($html)->toContain('https://site.test/?a=1&amp;b=&quot;2&quot;');
    }

    // The current name in the menu's button is escaped too.
    $current = new Language('en', '<b>English</b>', 'ltr', 'https://site.test/', true);
    expect(renderLanguageSwitcher(['form' => 'menu'], $current, switcherArabic()))
        ->not->toContain('<b>')
        ->toContain('&lt;b&gt;English&lt;/b&gt;');
});

it('FR-042: the strings a person reads go through the translation functions with the corex domain', function () {
    $source = (string) file_get_contents(dirname(__DIR__, 3) . '/plugins/corex-blocks/src/LanguageSwitcherRenderer.php');

    preg_match_all('/\b(?:esc_html__|esc_attr__|__)\(\s*\'[^\']+\',\s*\'([^\']+)\'\s*\)/', $source, $calls);

    expect($calls[1])->not->toBe([])
        ->and(array_unique($calls[1]))->toBe(['corex']);
});
