<?php

/**
 * Where a language control may be (spec 137, US-NAV-05; FR-033, FR-034; DECISIONS #694).
 *
 * - FR-034: no shipped pattern, part or template holds a language control that goes nowhere: a link
 *   labelled with a language (a code such as "EN", or a language's name) or carrying `hreflang`,
 *   whose address is empty or `#`. `main` held none when slice 2 was built, so this keeps it so.
 * - FR-033: the switcher sits beside a Navigation block, never inside one: its `block.json` names no
 *   Navigation parent, and nothing in CoreX adds it to the blocks a Navigation block accepts.
 * - FR-033, constitution VI: its script and style are the block's own, declared in `block.json`, so
 *   WordPress loads them only where the block renders.
 *
 * @package Corex\Tests\Unit\Blocks
 */

declare(strict_types=1);

/** The repository root. */
function languageControlsRoot(): string
{
    return dirname(__DIR__, 3);
}

/**
 * Every shipped pattern, part and template, by its path: the CoreX theme's files and the corex-ui
 * pattern library, read as source.
 *
 * @return array<string, string>
 */
function shippedSwitcherLayouts(): array
{
    $root = languageControlsRoot();
    $files = array_merge(
        glob($root . '/theme/patterns/*.php') ?: [],
        glob($root . '/theme/parts/*.html') ?: [],
        glob($root . '/theme/templates/*.html') ?: [],
        [$root . '/addons/corex-ui/src/Patterns/PatternLibrary.php'],
    );

    $layouts = [];
    foreach ($files as $file) {
        $layouts[substr($file, strlen($root) + 1)] = (string) file_get_contents($file);
    }

    return $layouts;
}

/** A label that names a language: a two-letter code, or a language's name in English or its own. */
const COREX_TEST_LANGUAGE_LABEL = '/^\s*(?:[A-Z]{2}|[a-z]{2}(?:-[A-Za-z]{2})?|English|Arabic|French|German|Spanish|'
    . 'العربية|عربي|Français|Deutsch|Español|Language|Languages|اللغة)\s*$/u';

/**
 * The language controls in `$markup` that go nowhere, each described.
 *
 * @return list<string>
 */
function deadLanguageControls(string $markup): array
{
    $dead = [];

    // A link written as HTML.
    preg_match_all('#<a\b([^>]*)>(.*?)</a>#su', $markup, $links, PREG_SET_ORDER);
    foreach ($links as [$whole, $attributes, $text]) {
        $label = trim(strip_tags($text));
        $isLanguage = preg_match(COREX_TEST_LANGUAGE_LABEL, $label) === 1 || stripos($attributes, 'hreflang') !== false;
        $href = preg_match('/\bhref\s*=\s*"([^"]*)"/', $attributes, $match) === 1 ? trim($match[1]) : null;

        if ($isLanguage && ($href === null || $href === '' || $href === '#')) {
            $dead[] = $whole;
        }
    }

    // A menu link written as a block, with its label and address in its attributes.
    preg_match_all('#<!--\s*wp:navigation-link\s+(\{.*?\})\s*/?-->#su', $markup, $blocks, PREG_SET_ORDER);
    foreach ($blocks as [$whole, $json]) {
        $attributes = json_decode($json, true);
        $label = is_array($attributes) ? (string) ($attributes['label'] ?? '') : '';
        $url = is_array($attributes) ? trim((string) ($attributes['url'] ?? '')) : '';

        if (preg_match(COREX_TEST_LANGUAGE_LABEL, $label) === 1 && ($url === '' || $url === '#')) {
            $dead[] = $whole;
        }
    }

    return $dead;
}

it('FR-034: finds a language control that goes nowhere, so the check below can fail', function () {
    expect(deadLanguageControls('<a class="lang" href="#">EN</a>'))->toHaveCount(1)
        ->and(deadLanguageControls('<a href="">العربية</a>'))->toHaveCount(1)
        ->and(deadLanguageControls('<a hreflang="ar">Read in Arabic</a>'))->toHaveCount(1)
        ->and(deadLanguageControls('<!-- wp:navigation-link {"label":"AR","url":"#"} /-->'))->toHaveCount(1)
        ->and(deadLanguageControls('<a href="/ar/">العربية</a>'))->toBe([])
        ->and(deadLanguageControls('<a href="#">Home</a>'))->toBe([]);
});

it('FR-034: no shipped pattern, part or template holds a language control that goes nowhere', function () {
    $layouts = shippedSwitcherLayouts();
    expect(count($layouts))->toBeGreaterThan(10);

    $dead = [];
    foreach ($layouts as $path => $markup) {
        foreach (deadLanguageControls($markup) as $control) {
            $dead[] = $path . ': ' . $control;
        }
    }

    expect($dead)->toBe([]);
});

it('FR-033: the switcher names no Navigation parent, and nothing adds it to a Navigation block', function () {
    $root = languageControlsRoot();
    $metadata = json_decode(
        (string) file_get_contents($root . '/plugins/corex-blocks/src/blocks/language-switcher/block.json'),
        true,
    );

    expect($metadata['name'])->toBe('corex/language-switcher')
        ->and($metadata)->not->toHaveKey('parent')
        ->and($metadata)->not->toHaveKey('ancestor');

    $adds = [];
    foreach (['plugins', 'addons', 'theme'] as $folder) {
        $files = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root . '/' . $folder, FilesystemIterator::SKIP_DOTS),
            static fn (SplFileInfo $file): bool => ! in_array($file->getFilename(), ['node_modules', 'vendor', 'build'], true),
        ));
        foreach ($files as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if (preg_match('/\.(?:php|js|json)$/', $path) !== 1 || str_contains($path, '/blocks/language-switcher/')) {
                continue;
            }
            $text = (string) file_get_contents($path);
            // A header pattern may hold the switcher beside its menu (slice 5); only code that widens what a
            // block accepts, or names a parent, can put it inside one.
            if (str_contains($text, 'corex/language-switcher') && preg_match('/allowedBlocks|allowed_blocks|[\'"](?:parent|ancestor)[\'"]/', $text) === 1) {
                $adds[] = substr($path, strlen(str_replace('\\', '/', $root)) + 1);
            }
        }
    }

    expect($adds)->toBe([]);
});

it('FR-033, constitution VI: its script and style are its own, declared in block.json', function () {
    $metadata = json_decode(
        (string) file_get_contents(languageControlsRoot() . '/plugins/corex-blocks/src/blocks/language-switcher/block.json'),
        true,
    );

    expect($metadata['title'])->toBe('Language switcher')
        ->and($metadata['textdomain'])->toBe('corex')
        ->and($metadata['viewScript'])->toBe('file:./view.js')
        ->and($metadata['style'])->toBe('file:./style-index.css')
        ->and($metadata)->not->toHaveKey('script')
        ->and($metadata['attributes']['form'])->toBe(['type' => 'string', 'enum' => ['menu', 'list', 'toggle'], 'default' => 'menu'])
        ->and($metadata['corex'])->toBe([
            'renderer' => 'Corex\\Blocks\\LanguageSwitcherRenderer',
            'editor' => 'records',
        ]);
});
