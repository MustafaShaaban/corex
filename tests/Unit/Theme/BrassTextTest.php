<?php

/**
 * Brass is a ground, a ring or an edge, never the colour of text on a page (spec 134, US-BRD-01,
 * FR-007; the hand-over's "Accessibility expectations").
 *
 * The light brass is 3.35:1 on white: enough for a focus ring or a button's edge, not for words or
 * for a mark that carries meaning. Text in brass is drawn in `accent-dark`. A stylesheet that gives
 * text the `accent` colour fails here, naming the file and the line.
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Tests\Support\ThemeContract;
use Corex\Ui\Patterns\PatternLibrary;

/**
 * Every rule of a stylesheet with its own declarations, the ones written directly inside its braces.
 * A SCSS rule that also holds nested rules (`&:hover { ... }`) keeps the declarations around them.
 *
 * @return list<array{selector: string, declarations: string}>
 */
function brass_text_rules(string $css): array
{
    $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
    $rules = [];
    $stack = [];
    $text = '';

    foreach (str_split($css) as $character) {
        if ($character === '{') {
            // The text since the last `;` is this rule's selector; what came before it is the parent's.
            $cut = strrpos($text, ';');
            $selector = $cut === false ? $text : substr($text, $cut + 1);
            if ($stack !== []) {
                $stack[count($stack) - 1]['declarations'] .= $cut === false ? '' : substr($text, 0, $cut + 1);
            }
            $stack[] = ['selector' => trim($selector), 'declarations' => ''];
            $text = '';
        } elseif ($character === '}') {
            $rule = array_pop($stack);
            if ($rule !== null) {
                $rule['declarations'] .= $text;
                $rules[] = $rule;
            }
            $text = '';
        } else {
            $text .= $character;
        }
    }

    return $rules;
}

/** The files whose block markup may name a text colour: theme patterns, templates and parts. */
function brass_text_markup_files(): array
{
    $root = ThemeContract::root();

    return array_merge(
        (array) glob($root . '/theme/patterns/*.php'),
        (array) glob($root . '/theme/templates/*.html'),
        (array) glob($root . '/theme/parts/*.html'),
    );
}

/** The blocks of a piece of markup whose text colour is brass, as their attribute JSON. */
function brass_text_blocks(string $markup): array
{
    preg_match_all('/<!-- wp:[a-z\/-]+ (\{.*?\}) \/?-->/', $markup, $blocks);

    return array_values(array_filter(
        $blocks[1],
        static fn (string $json): bool => (((array) json_decode($json, true))['textColor'] ?? '') === 'accent',
    ));
}

/**
 * corex-ui's patterns as they render: their buttons are built from a template whose colours are
 * placeholders in the source, so the source alone cannot show a brass label (two-model review, F1).
 *
 * @return array<string,string> Pattern name to markup.
 */
function brass_text_rendered_patterns(): array
{
    Functions\when('__')->returnArg();
    Functions\when('esc_html__')->returnArg();

    return array_column((new PatternLibrary())->patterns(), 'content', 'name');
}

it('draws no text in brass in any CoreX stylesheet', function () {
    $found = [];

    foreach (ThemeContract::sourceFiles() as $path) {
        if (! preg_match('/\.(?:css|scss)$/', $path)) {
            continue;
        }
        $lines = explode("\n", (string) file_get_contents(ThemeContract::root() . '/' . $path));
        foreach ($lines as $number => $line) {
            if (preg_match('/(?<![\w-])color\s*:\s*var\(\s*--wp--preset--color--accent\s*[,)]/', $line) === 1) {
                $found[] = sprintf('%s:%d', $path, $number + 1);
            }
        }
    }

    expect($found)->toBe([]);
});

// The darker brass, and the brass where a client theme has no `accent-dark` (Perego has `accent` only).
it('draws the form\'s required mark and the share links\' hover in the darker brass, with a fallback', function () {
    $darker = 'color: var(--wp--preset--color--accent-dark, var(--wp--preset--color--accent));';
    $form = (string) file_get_contents(ThemeContract::root() . '/plugins/corex-forms/src/Block/blocks/corex-form/style.scss');
    $share = (string) file_get_contents(ThemeContract::root() . '/plugins/corex-blocks/src/blocks/social-share/style.scss');

    expect($form)->toMatch('/\.corex-form__required\s*\{\s*' . preg_quote($darker, '/') . '/')
        ->and($share)->toContain($darker);
});

it('reads the declarations of a rule that also holds nested rules', function () {
    $rules = array_column(brass_text_rules(
        ".a {\n\tcolor: red;\n\t&:hover { color: blue; }\n\tbackground: green;\n}\n.b { margin: 0; }",
    ), 'declarations', 'selector');

    expect(array_keys($rules))->toBe(['&:hover', '.a', '.b'])
        ->and($rules['.a'])->toContain('color: red;')->toContain('background: green;')->not->toContain('blue')
        ->and($rules['&:hover'])->toContain('color: blue;');
});

// Spec 134 (DECISIONS #635): white on the light brass is 3.35:1, so text on a brass ground is `onAccent`.
it('draws the text of every rule with a brass ground in the on-accent colour', function () {
    $found = [];

    foreach (ThemeContract::sourceFiles() as $path) {
        if (! preg_match('/\.(?:css|scss)$/', $path)) {
            continue;
        }

        foreach (brass_text_rules((string) file_get_contents(ThemeContract::root() . '/' . $path)) as $rule) {
            $body = $rule['declarations'];
            if (preg_match('/(?<![\w-])background(?:-color)?\s*:\s*var\(\s*--wp--preset--color--accent\s*[,)]/', $body) !== 1) {
                continue;
            }
            if (preg_match('/(?<![\w-])color\s*:\s*([^;]+)/', $body, $colour) === 1
                && ! str_starts_with(trim($colour[1]), 'var(--wp--custom--color--on-accent,')
            ) {
                $found[] = sprintf('%s: %s draws %s on brass', $path, $rule['selector'], trim($colour[1]));
            }
        }
    }

    expect($found)->toBe([]);
});

it('draws the newsletter button\'s label and the mega menu badge in the on-accent colour', function () {
    $onAccent = 'color: var(--wp--custom--color--on-accent, var(--wp--preset--color--inverse));';

    expect((string) file_get_contents(ThemeContract::root() . '/plugins/corex-blocks/src/blocks/newsletter-signup/style.scss'))->toContain($onAccent)
        ->and((string) file_get_contents(ThemeContract::root() . '/theme/assets/css/corex-navigation.css'))->toContain($onAccent);
});

// FR-007: block markup gives text the darker brass at every size. The type scale is fluid, so a size
// that is large text at desktop width (the process steps' `xl`) is under it at 375px, where brass fails AA.
it('draws no text in brass in a theme pattern, template or part, or a corex-ui pattern', function () {
    $files = brass_text_markup_files();
    $patterns = brass_text_rendered_patterns();
    $found = [];

    foreach ($files as $file) {
        foreach (brass_text_blocks((string) file_get_contents((string) $file)) as $json) {
            $found[] = basename((string) $file) . ': ' . $json;
        }
    }
    foreach ($patterns as $name => $markup) {
        foreach (brass_text_blocks((string) $markup) as $json) {
            $found[] = $name . ': ' . $json;
        }
    }

    expect(array_map('basename', $files))->toContain('front-page.html', 'header.html')
        ->and($patterns)->toHaveKey('corex/cta')
        ->and($found)->toBe([]);
});

it('finds a brass label in a rendered button whose colours are placeholders in its source', function () {
    $template = '<!-- wp:button {"backgroundColor":"%1$s","textColor":"%2$s"} -->';

    expect(brass_text_blocks($template))->toBe([])
        ->and(brass_text_blocks(sprintf($template, 'surface', 'accent')))->toHaveCount(1);
});

// The corporate header's top bar is `surface-strong`, where brass is 2.65:1 in light: its links ring in
// `primary` (tests/Fixtures/Theme/contrast-focus-matrix.json, `light-focus-topbar`).
it('rings the links of the corporate header\'s top bar in primary', function () {
    $rules = brass_text_rules((string) file_get_contents(ThemeContract::root() . '/theme/assets/css/corex-navigation.css'));
    $ring = array_values(array_filter(
        $rules,
        static fn (array $rule): bool => str_contains($rule['selector'], '.corex-header__topbar a:focus-visible'),
    ));

    expect($ring)->toHaveCount(1)
        ->and($ring[0]['declarations'])->toContain('outline-color: var(--wp--preset--color--primary);');
});
