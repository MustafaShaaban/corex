<?php

/**
 * The build check for shipped patterns (spec 115, US-SAFE-14, FR-016 and FR-017): no pattern of the
 * corex-ui library or of the CoreX theme may say something for the site that the site did not write,
 * or link nowhere. Outside a section marked as sample content, it fails on an invented figure (a
 * percentage, "100+"), a known invented phrase, an example address, a 555 telephone number, and,
 * anywhere, on `href="#"`. Each failure names the pattern.
 *
 * The theme's patterns are PHP that WordPress runs; their words are read from their translatable
 * strings, which is where a pattern's copy lives.
 *
 * @package Corex\Tests\Unit\Patterns
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Ui\Patterns\PatternLibrary;

/**
 * What in a piece of pattern copy reads as a claim made for the site, or a placeholder contact.
 *
 * @return list<string> the reasons, empty when there is none
 */
function inventedClaims(string $text): array
{
    $rules = [
        'a percentage'              => '/\d+(?:[.,]\d+)?\s*%/',
        'a figure such as "100+"'   => '/\d+\s*\+/',
        'an example address'        => '/\bexample\.(?:com|org|net)\b|\bExample (?:Street|Road|Avenue)\b/i',
        'a 555 telephone number'    => '/\b555\b/',
        'invented social proof'     => '/trusted by|happy client|client satisfaction|years of experience/i',
    ];

    $found = [];
    foreach ($rules as $reason => $pattern) {
        if (preg_match($pattern, $text) === 1) {
            $found[] = $reason;
        }
    }

    return $found;
}

/** A link that goes nowhere, in markup or in a block's attributes. */
function linksNowhere(string $source): bool
{
    return preg_match('/href=(["\'])#\1|"url"\s*:\s*"#"/', $source) === 1;
}

/** The words of rendered block markup: no comments, no tags, no attributes. */
function wordsOf(string $markup): string
{
    return (string) preg_replace('/<!--.*?-->|<[^>]+>/s', ' ', $markup);
}

/** The translatable strings of a theme pattern file. */
function translatableStrings(string $php): string
{
    preg_match_all('/\b(?:__|_e|_x|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1/s', $php, $matches);

    return implode("\n", $matches[2]);
}

function isSampleSection(string $source): bool
{
    return str_contains($source, PatternLibrary::SAMPLE);
}

/**
 * A pattern without its sample section: what a visitor is shown. A sample is the outermost group that
 * carries the marker, from its opening comment to the closing one that matches it.
 */
function outsideSamples(string $source): string
{
    $offset = 0;
    while (($start = strpos($source, '<!-- wp:group {', $offset)) !== false) {
        $opening = substr($source, $start, (int) strpos($source, '-->', $start) - $start);
        if (! str_contains($opening, PatternLibrary::SAMPLE)) {
            $offset = $start + 1;
            continue;
        }

        // Walk to the closing comment that matches this group, counting the groups inside it.
        $depth = 0;
        $at    = $start;
        while (preg_match('~<!-- (/?)wp:group[ \-]~', $source, $tag, PREG_OFFSET_CAPTURE, $at) === 1) {
            $depth += $tag[1][0] === '/' ? -1 : 1;
            $at     = $tag[0][1] + strlen($tag[0][0]);
            if ($depth === 0) {
                break;
            }
        }
        $close  = (int) strpos($source, '-->', $at);
        $source = substr($source, 0, $start) . substr($source, $close + 3);
        $offset = $start;
    }

    return $source;
}

/** Placeholder contacts in markup itself: a `mailto:` or a block attribute is not a word. */
function placeholderContacts(string $markup): array
{
    return array_values(array_intersect(inventedClaims($markup), ['an example address', 'a 555 telephone number']));
}

it('finds a claim, a placeholder contact and a dead link, and names them', function () {
    expect(inventedClaims('98% Client satisfaction'))->toContain('a percentage', 'invented social proof')
        ->and(inventedClaims('100+ projects'))->toBe(['a figure such as "100+"'])
        ->and(inventedClaims('hello@example.com, +1 (555) 010-0100'))->toBe(['an example address', 'a 555 telephone number'])
        ->and(inventedClaims('Our process, step 01'))->toBe([])
        ->and(linksNowhere('<a href="#">About</a>'))->toBeTrue()
        ->and(linksNowhere('<!-- wp:social-link {"url":"#","service":"x"} /-->'))->toBeTrue()
        ->and(linksNowhere('<a href="#main">Skip</a>'))->toBeFalse()
        ->and(placeholderContacts('<a href="mailto:hello@example.com">Mail</a>'))->toBe(['an example address']);
});

it('looks past a sample section, and only past it', function () {
    $pattern = '<!-- wp:group {"className":"is-corex-sample"} --><section><!-- wp:group --><div>98%</div><!-- /wp:group --></section><!-- /wp:group -->'
        . '<!-- wp:group --><div>99% of clients</div><!-- /wp:group -->';

    expect(inventedClaims(wordsOf(outsideSamples($pattern))))->toBe(['a percentage'])
        ->and(outsideSamples($pattern))->not->toContain('98%');
});

it('ships corex-ui patterns that claim nothing outside a sample and link somewhere', function () {
    Functions\when('__')->returnArg();
    Functions\when('esc_html__')->returnArg();

    $offenders = [];
    foreach ((new PatternLibrary())->patterns() as $pattern) {
        $visible = outsideSamples($pattern['content']);
        $reasons = array_values(array_unique([...inventedClaims(wordsOf($visible)), ...placeholderContacts($visible)]));
        if (linksNowhere($pattern['content'])) {
            $reasons[] = 'a link to "#"';
        }
        if ($reasons !== []) {
            $offenders[] = $pattern['name'] . ': ' . implode(', ', $reasons);
        }
    }

    expect($offenders)->toBe([], implode("\n", $offenders));
});

it('ships theme patterns that claim nothing outside a sample and link somewhere', function () {
    $offenders = [];
    foreach (glob(dirname(__DIR__, 3) . '/theme/patterns/*.php') ?: [] as $file) {
        $source  = (string) file_get_contents($file);
        $visible = outsideSamples($source);
        $reasons = array_values(array_unique([...inventedClaims(translatableStrings($visible)), ...placeholderContacts($visible)]));
        if (linksNowhere($source)) {
            $reasons[] = 'a link to "#"';
        }
        if ($reasons !== []) {
            $offenders[] = basename($file) . ': ' . implode(', ', $reasons);
        }
    }

    expect($offenders)->toBe([], implode("\n", $offenders));
});

it('marks the patterns that need example content as samples', function () {
    Functions\when('__')->returnArg();
    Functions\when('esc_html__')->returnArg();

    $byName = array_column((new PatternLibrary())->patterns(), 'content', 'name');

    expect(isSampleSection($byName['corex/stats']))->toBeTrue()
        ->and(isSampleSection($byName['corex/testimonial']))->toBeTrue()
        ->and(isSampleSection((string) file_get_contents(dirname(__DIR__, 3) . '/theme/patterns/section-logo-cloud.php')))->toBeTrue();
});
