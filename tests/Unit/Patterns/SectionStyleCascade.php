<?php

/**
 * The colours a section style of corex-ui draws, worked out from its stylesheet the way a browser
 * does (spec 134, FR-013 to FR-016): custom properties inherit, a `var()` takes its fallback when the
 * property is not defined, and a property that refers to itself is invalid. The dark and the brass
 * band re-point the palette for what is inside them, so a test that read only the slugs a pattern
 * names would no longer see what is drawn.
 *
 * It reads corex-ui's two block style sheets and the theme's own CSS (`styles.css` of `theme.json`),
 * and models the page, the section (`.is-style-corex-band-*`), a child of the section (`… > *`, where
 * the palette is re-pointed) and what sits in it. A later declaration wins over an earlier one, as in
 * CSS; specificity is not modelled, so a test names the rule it reads. The browser check of the same
 * is `tests/e2e/section-bands.spec.js`.
 *
 * @package Corex\Tests\Unit\Patterns
 */

declare(strict_types=1);

namespace Corex\Tests\Unit\Patterns;

final class SectionStyleCascade
{
    /** @var array<string,array<string,string>> selector => property => value, in source order */
    private array $rules = [];

    public function __construct(string $css)
    {
        $css = (string) preg_replace('~/\*.*?\*/~s', '', $css);
        preg_match_all('~([^{}]+)\{([^{}]*)\}~', $css, $blocks, PREG_SET_ORDER);

        foreach ($blocks as [, $selector, $body]) {
            $selector = (string) preg_replace('/\s+/', ' ', trim($selector));
            foreach (array_filter(array_map('trim', explode(';', $body))) as $declaration) {
                [$property, $value] = array_map('trim', explode(':', $declaration, 2)) + [1 => ''];
                $this->rules[$selector][$property] = (string) preg_replace('/\s+/', ' ', $value);
            }
        }
    }

    public static function fromStylesheet(): self
    {
        $root = dirname(__DIR__, 3);
        $theme = json_decode((string) file_get_contents($root . '/theme/theme.json'), true);

        return new self(
            (string) ($theme['styles']['css'] ?? '')
            . file_get_contents($root . '/addons/corex-ui/assets/block-styles.css')
            . file_get_contents($root . '/addons/corex-ui/assets/section-bands.css'),
        );
    }

    /**
     * The declarations of every rule that lists exactly one of these selectors, in source order, so the
     * rules of an element matched twice (a band that is another band's child) merge as in CSS.
     *
     * @return array<string,string>
     */
    public function rule(string ...$selectors): array
    {
        $found = [];

        foreach ($this->rules as $list => $declarations) {
            if (array_intersect($selectors, array_map('trim', self::selectorList($list))) !== []) {
                $found = array_merge($found, $declarations);
            }
        }

        return $found;
    }

    /**
     * The custom properties a page defines for a palette as {@see themePalette()} reads it: each slug as
     * `--wp--preset--color--<slug>`, each custom colour as `--wp--custom--color--<kebab>`, and the
     * theme's focus colour, which `theme.json` defines as `accent` and the page resolves there.
     *
     * @param array<string,string> $palette slug or `custom.<key>` => colour
     * @return array<string,string>
     */
    public static function page(array $palette): array
    {
        $page = [];

        foreach ($palette as $key => $colour) {
            $page[str_starts_with($key, 'custom.')
                ? '--wp--custom--color--' . strtolower((string) preg_replace('/(?<=[a-z0-9])([A-Z])/', '-$1', substr($key, 7)))
                : '--wp--preset--color--' . $key] = $colour;
        }
        $page['--wp--custom--focus--color'] = $page['--wp--preset--color--accent'] ?? '';
        $page['color'] = $page['--wp--preset--color--ink'] ?? '';

        return $page;
    }

    /**
     * The declarations of every rule with a selector that holds all of the given parts, each a whole
     * token (`.is-style-corex-band-dark` is not found in `.is-style-corex-band-darker`); a later one wins.
     *
     * @return array<string,string>
     */
    public function declarations(string ...$parts): array
    {
        $found = [];

        foreach ($this->rules as $selector => $declarations) {
            foreach (array_map('trim', self::selectorList($selector)) as $one) {
                if (self::holds($one, $parts)) {
                    $found = array_merge($found, $declarations);
                    break;
                }
            }
        }

        return $found;
    }

    /**
     * What a section with the style draws on a page with the palette: its ground and text, a heading
     * with no colour of its own, a link, a card and its text, a field, a default and a secondary button,
     * at rest, on hover and its ring, and a disabled button. With `$outer`, the section is placed
     * directly inside a section of that style.
     *
     * @param array<string,string> $palette
     * @return array<string,?string>
     */
    public function draw(string $style, array $palette, ?string $outer = null): array
    {
        $band = '.is-style-' . $style;
        $page = self::page($palette);
        $parent = $outer === null ? $page : self::element($page, $this->rule('.wp-block-group.is-style-' . $outer));
        $own = $outer === null
            ? $this->rule('.wp-block-group' . $band)
            : $this->rule('.wp-block-group.is-style-' . $outer . ' > *', '.wp-block-group' . $band);
        $section = self::element($parent, $own);
        $child = self::element($section, $this->rule('.wp-block-group' . $band . ' > *'));
        $secondary = array_merge(
            $this->declarations('.is-style-corex-secondary', '.wp-block-button__link', '!:hover', '!.is-style-corex-band-dark', '!.is-style-corex-band-brass'),
            $this->declarations($band, '.is-style-corex-secondary', '.wp-block-button__link', '!:hover'),
        );
        // A heading with no rule here takes the theme's heading colour, `primary`.
        $heading = $this->declarations($band, 'h1')['color'] ?? 'var(--wp--preset--color--primary)';
        $button = $this->declarations($band, '.wp-block-button__link', '!:hover', '!:focus-visible', '!.is-style-corex-secondary');
        $hover = $this->declarations($band, '.wp-block-button__link', ':hover', '!.is-style-corex-secondary');
        $ring = $this->declarations($band, '.wp-block-button__link', ':focus-visible');
        $card = self::element($child, $this->rule('.wp-block-group.is-style-corex-card'));
        // The edge colour is the last part of the card's `border` shorthand.
        $cardEdge = preg_replace('/^.*?\bsolid\s+/', '', (string) ($card['border'] ?? ''));
        $disabled = $this->rule(':where(.wp-element-button, .wp-block-button__link):is(:disabled, [aria-disabled="true"])');
        $text = self::value($section, $section['color'] ?? null);

        return [
            'section'     => self::value($section, $section['background-color'] ?? null),
            'text'        => $text,
            'heading'     => $heading === 'inherit' ? $text : self::value($child, $heading),
            'link'        => self::value($child, 'var(--wp--preset--color--accent-dark)'),
            'card'        => self::value($card, $card['background'] ?? null),
            'cardEdge'    => self::value($card, $cardEdge),
            // A card's own text is inherited; a field's is `ink` on `surface` with a `border` edge.
            'cardText'     => $text,
            'field'        => self::value($child, 'var(--wp--preset--color--surface)'),
            'fieldText'    => self::value($child, 'var(--wp--preset--color--ink)'),
            'fieldEdge'    => self::value($child, 'var(--wp--preset--color--border)'),
            'disabled'     => self::value($child, $disabled['background-color'] ?? null),
            'disabledText' => self::value($child, $disabled['color'] ?? null),
            'secondary'     => self::value($child, $secondary['background-color'] ?? $secondary['background'] ?? null),
            'secondaryText' => ($secondary['color'] ?? null) === 'inherit' ? $text : self::value($child, $secondary['color'] ?? null),
            'secondaryEdge' => self::value($child, $secondary['border-color'] ?? preg_replace('/^.*?\bsolid\s+/', '', (string) ($secondary['border'] ?? ''))),
            'button'      => self::value($child, $button['background-color'] ?? null),
            'label'       => self::value($child, $button['color'] ?? null),
            'buttonHover' => self::value($child, $hover['background-color'] ?? $button['background-color'] ?? null),
            'ring'        => self::value($child, $ring['outline-color'] ?? 'var(--wp--custom--focus--color)'),
        ];
    }

    /**
     * An element's custom properties: the ones it inherits, then its own, each own one resolved on the
     * element itself (a reference to another own property reads that, and a cycle makes both invalid).
     *
     * @param array<string,?string> $parent
     * @param array<string,string> $own
     * @return array<string,?string>
     */
    public static function element(array $parent, array $own): array
    {
        $element = $parent;
        $resolving = [];
        $resolve = static function (string $name) use (&$resolve, &$resolving, $own, $parent): ?string {
            if (! array_key_exists($name, $own)) {
                return $parent[$name] ?? null;
            }
            if (isset($resolving[$name])) {
                return null;
            }
            $resolving[$name] = true;
            $value = self::substitute($own[$name], $resolve);
            unset($resolving[$name]);

            return $value;
        };

        foreach (array_keys($own) as $name) {
            if (str_starts_with($name, '--')) {
                $element[$name] = $resolve($name);
            } else {
                $element[$name] = $own[$name];
            }
        }

        return $element;
    }

    /** @param array<string,?string> $element */
    public static function value(array $element, ?string $value): ?string
    {
        return $value === null ? null : self::substitute($value, static fn (string $name): ?string => $element[$name] ?? null);
    }

    /**
     * Replaces every `var(--name[, fallback])` in a value; null when one has no value and no fallback.
     *
     * @param callable(string):?string $lookup
     */
    public static function substitute(string $value, callable $lookup): ?string
    {
        $start = strpos($value, 'var(');

        if ($start === false) {
            return trim($value);
        }

        $depth = 0;
        $comma = null;
        for ($at = $start + 4; $at < strlen($value); $at++) {
            $char = $value[$at];
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')' && $depth-- === 0) {
                break;
            } elseif ($char === ',' && $depth === 0 && $comma === null) {
                $comma = $at;
            }
        }

        $inner = substr($value, $start + 4, $at - $start - 4);
        $name = trim($comma === null ? $inner : substr($value, $start + 4, $comma - $start - 4));
        $found = $lookup($name);
        if ($found === null && $comma !== null) {
            $found = self::substitute(substr($value, $comma + 1, $at - $comma - 1), $lookup);
        }
        if ($found === null) {
            return null;
        }

        return self::substitute(substr($value, 0, $start) . $found . substr($value, $at + 1), $lookup);
    }

    /** @return list<string> */
    private static function selectorList(string $selector): array
    {
        $parts = [];
        $depth = 0;
        $current = '';

        foreach (str_split($selector) as $char) {
            $depth += $char === '(' ? 1 : ($char === ')' ? -1 : 0);
            if ($char === ',' && $depth === 0) {
                $parts[] = $current;
                $current = '';
                continue;
            }
            $current .= $char;
        }
        $parts[] = $current;

        return $parts;
    }

    /** @param list<string> $parts each a whole token; a part starting `!` must be absent */
    private static function holds(string $selector, array $parts): bool
    {
        foreach ($parts as $part) {
            $absent = str_starts_with($part, '!');
            $part = ltrim($part, '!');
            // A token that starts or ends with a name character may not run on into a longer name.
            $token = '/' . (preg_match('/^[\w-]/', $part) === 1 ? '(?<![\w-])' : '')
                . preg_quote($part, '/')
                . (preg_match('/[\w-]$/', $part) === 1 ? '(?![\w-])' : '') . '/';
            if ((preg_match($token, $selector) === 1) === $absent) {
                return false;
            }
        }

        return true;
    }
}
