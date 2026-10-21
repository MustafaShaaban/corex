<?php

/**
 * PHP translation lint (spec 116, US-NFR-06; Definition of Done item 9).
 *
 *     php scripts/i18n-lint.php            # fails on a finding that is not in the baseline
 *     php scripts/i18n-lint.php --record   # writes every current finding to the baseline
 *
 * Two kinds of finding, read from PHP's own tokenizer:
 *
 * - **A translation call without the CoreX text domain**: `__()`, `esc_html_e()`, `_n()` and the
 *   rest, with the domain missing, not a literal, or not `corex`.
 * - **Words printed without a translation**: a literal with words echoed or printed directly,
 *   passed first to `printf()`, `esc_html()`, `esc_attr()` or `wp_kses_post()`, or written as
 *   HTML outside the PHP tags of a template. Markup, placeholders and single lowercase words such
 *   as `checked` are not words a person reads.
 *
 * JavaScript strings are linted by `npm run lint:js` (the WordPress ESLint plugin's i18n rules).
 *
 * The baseline, `tests/Fixtures/i18n-lint-baseline.json`, holds what was found when the lint was
 * written, keyed by file, rule and text rather than line so that an edit above a finding does not
 * move it. It only shrinks: an entry that no longer matches fails too, so the change that fixes a
 * string also takes it out of the list.
 *
 * @package Corex\Scripts
 */

declare(strict_types=1);

const COREX_I18N_DOMAIN = 'corex';

/** Each translation function and the position of its text-domain argument. */
const COREX_I18N_DOMAIN_POSITION = [
    '__'             => 1,
    '_e'             => 1,
    'esc_html__'     => 1,
    'esc_html_e'     => 1,
    'esc_attr__'     => 1,
    'esc_attr_e'     => 1,
    '_x'             => 2,
    '_ex'            => 2,
    'esc_html_x'     => 2,
    'esc_attr_x'     => 2,
    '_n'             => 3,
    '_n_noop'        => 2,
    '_nx'            => 4,
    '_nx_noop'       => 3,
];

/** Functions whose first argument is printed as it is. */
const COREX_I18N_OUTPUT_FUNCTIONS = ['printf', 'esc_html', 'esc_attr', 'wp_kses_post'];

/**
 * Whether a literal's text is something a person reads.
 *
 * Tags, entities, printf placeholders and whitespace are removed first. What is left counts when it
 * has a word of two letters or more and either a capital letter or two words: `Save`, `save
 * changes`. A lone lowercase token (`checked`, `corex-admin__title`) is an attribute or a class.
 */
function corex_i18n_is_words(string $text): bool
{
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = (string) preg_replace('/%(?:\d+\$)?[-+ 0#]*\d*(?:\.\d+)?[bcdeEfFgGosuxX%]/', ' ', $text);
    $text = trim((string) preg_replace('/\s+/u', ' ', $text));

    if (preg_match('/\p{L}{2,}/u', $text) !== 1) {
        return false;
    }

    return preg_match('/\p{Lu}/u', $text) === 1 || preg_match('/\p{L}{2,}\s+\p{L}/u', $text) === 1;
}

/** The value of a single- or double-quoted PHP literal, as written. */
function corex_i18n_literal(string $token): string
{
    return stripcslashes(substr($token, 1, -1));
}

/**
 * The findings in one file's source.
 *
 * @return list<array{file: string, line: int, rule: string, text: string}>
 */
function corex_i18n_lint_source(string $file, string $source): array
{
    $tokens = array_values(array_filter(
        token_get_all($source),
        static fn ($token): bool => ! is_array($token) || ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true),
    ));
    $findings = [];
    $add = static function (int $line, string $rule, string $text) use (&$findings, $file): void {
        $findings[] = ['file' => $file, 'line' => $line, 'rule' => $rule, 'text' => trim(mb_substr($text, 0, 160))];
    };
    $count = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (is_array($token) && $token[0] === T_INLINE_HTML && corex_i18n_is_words($token[1])) {
            $add($token[2], 'untranslated-html', $token[1]);
            continue;
        }

        if (is_array($token) && in_array($token[0], [T_ECHO, T_PRINT], true)) {
            foreach (corex_i18n_direct_literals($tokens, $i + 1) as [$line, $text]) {
                $add($line, 'untranslated-output', $text);
            }
            continue;
        }

        if (! is_array($token) || $token[0] !== T_STRING || ($tokens[$i + 1] ?? null) !== '(') {
            continue;
        }

        $previous = $tokens[$i - 1] ?? null;
        $isMethod = is_array($previous) && in_array($previous[0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NULLSAFE_OBJECT_OPERATOR], true);

        if ($isMethod) {
            continue;
        }

        $name = strtolower($token[1]);
        $arguments = corex_i18n_arguments($tokens, $i + 1);

        if (isset(COREX_I18N_DOMAIN_POSITION[$name])) {
            $domain = $arguments[COREX_I18N_DOMAIN_POSITION[$name]] ?? null;
            $text = corex_i18n_argument_text($arguments[0] ?? []);

            if ($domain === null) {
                $add($token[2], 'missing-domain', $name . '(): ' . $text);
            } elseif (count($domain) !== 1 || ! is_array($domain[0]) || $domain[0][0] !== T_CONSTANT_ENCAPSED_STRING) {
                $add($token[2], 'domain-not-literal', $name . '(): ' . $text);
            } elseif (corex_i18n_literal($domain[0][1]) !== COREX_I18N_DOMAIN) {
                $add($token[2], 'wrong-domain', $name . '(' . corex_i18n_literal($domain[0][1]) . '): ' . $text);
            }
            continue;
        }

        if (in_array($name, COREX_I18N_OUTPUT_FUNCTIONS, true)) {
            $first = $arguments[0] ?? [];
            $first[] = ';';

            foreach (corex_i18n_direct_literals($first, 0) as [$line, $text]) {
                $add($line, 'untranslated-output', $text);
            }
        }
    }

    return $findings;
}

/**
 * The literals an echo or print statement outputs directly: operands of its concatenation and
 * ternaries at the statement's own depth, not arguments of a call inside it.
 *
 * @param list<array|string> $tokens
 * @return list<array{int, string}>
 */
function corex_i18n_direct_literals(array $tokens, int $start): array
{
    $depth = 0;
    $literals = [];

    for ($i = $start, $count = count($tokens); $i < $count; $i++) {
        $token = $tokens[$i];

        if ($depth === 0 && ($token === ';' || (is_array($token) && $token[0] === T_CLOSE_TAG))) {
            break;
        }

        if (in_array($token, ['(', '['], true)) {
            $depth++;
        } elseif (in_array($token, [')', ']'], true)) {
            $depth--;
        } elseif ($depth === 0 && is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
            $text = corex_i18n_literal($token[1]);

            if (corex_i18n_is_words($text)) {
                $literals[] = [$token[2], $text];
            }
        }
    }

    return $literals;
}

/**
 * A call's arguments, each a list of its tokens, from the opening parenthesis at `$open`.
 *
 * @param list<array|string> $tokens
 * @return list<list<array|string>>
 */
function corex_i18n_arguments(array $tokens, int $open): array
{
    $depth = 0;
    $arguments = [];
    $current = [];

    for ($i = $open, $count = count($tokens); $i < $count; $i++) {
        $token = $tokens[$i];

        if (in_array($token, ['(', '[', '{'], true) || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
            if ($depth++ === 0) {
                continue;
            }
        } elseif (in_array($token, [')', ']', '}'], true)) {
            if (--$depth === 0) {
                break;
            }
        } elseif ($depth === 1 && $token === ',') {
            $arguments[] = $current;
            $current = [];
            continue;
        }

        $current[] = $token;
    }

    if ($current !== []) {
        $arguments[] = $current;
    }

    return $arguments;
}

/** A short text for a finding: the argument's first literal, or its code. */
function corex_i18n_argument_text(array $argument): string
{
    foreach ($argument as $part) {
        if (is_array($part) && $part[0] === T_CONSTANT_ENCAPSED_STRING) {
            return corex_i18n_literal($part[1]);
        }
    }

    return implode('', array_map(static fn ($part): string => is_array($part) ? $part[1] : $part, $argument));
}

/** The key a finding is kept under in the baseline. */
function corex_i18n_key(array $finding): string
{
    return $finding['file'] . '|' . $finding['rule'] . '|' . $finding['text'];
}

/**
 * Every PHP file CoreX ships, relative to the repository root, with `/` separators.
 *
 * @return list<string>
 */
function corex_i18n_files(string $root): array
{
    $files = [];

    foreach (['plugins', 'addons', 'packages'] as $top) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $root . '/' . $top,
            FilesystemIterator::SKIP_DOTS,
        ));

        foreach ($iterator as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

            if ($file->getExtension() === 'php' && preg_match('#/(vendor|node_modules|build|tests?|__tests__)/#', $relative) !== 1) {
                $files[] = $relative;
            }
        }
    }

    sort($files);

    return $files;
}

/**
 * Compares the findings with the baseline.
 *
 * @param list<array{file: string, line: int, rule: string, text: string}> $findings
 * @param list<array{file: string, rule: string, text: string}>            $baseline
 * @return array{new: list<array>, stale: list<array>}
 */
function corex_i18n_compare(array $findings, array $baseline): array
{
    $known = [];

    foreach ($baseline as $entry) {
        $known[corex_i18n_key($entry)] = $entry;
    }

    $seen = [];
    $new = [];

    foreach ($findings as $finding) {
        $key = corex_i18n_key($finding);
        $seen[$key] = true;

        if (! isset($known[$key])) {
            $new[] = $finding;
        }
    }

    $stale = array_values(array_filter($baseline, static fn (array $entry): bool => ! isset($seen[corex_i18n_key($entry)])));

    return ['new' => $new, 'stale' => $stale];
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $root = dirname(__DIR__);
    $baselineFile = $root . '/tests/Fixtures/i18n-lint-baseline.json';
    $findings = [];

    foreach (corex_i18n_files($root) as $file) {
        array_push($findings, ...corex_i18n_lint_source($file, (string) file_get_contents($root . '/' . $file)));
    }

    if (in_array('--record', $argv, true)) {
        $entries = array_map(static fn (array $finding): array => [
            'file' => $finding['file'],
            'rule' => $finding['rule'],
            'text' => $finding['text'],
        ], $findings);
        $entries = array_values(array_unique($entries, SORT_REGULAR));
        file_put_contents($baselineFile, json_encode([
            'description' => 'PHP strings found untranslated or outside the corex text domain when the lint was written (spec 116). Only shrinks: fix a string and remove its entry.',
            'since'       => gmdate('Y-m-d'),
            'story'       => 'US-NFR-06',
            'entries'     => $entries,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
        fwrite(STDOUT, sprintf("Recorded %d finding(s) in %s\n", count($entries), $baselineFile));
        exit(0);
    }

    $baseline = is_file($baselineFile) ? (array) (json_decode((string) file_get_contents($baselineFile), true)['entries'] ?? []) : [];
    $result = corex_i18n_compare($findings, $baseline);

    foreach ($result['new'] as $finding) {
        fwrite(STDOUT, sprintf("%s:%d [%s] %s\n", $finding['file'], $finding['line'], $finding['rule'], $finding['text']));
    }

    foreach ($result['stale'] as $entry) {
        fwrite(STDOUT, sprintf("%s [%s] %s: fixed, remove it from the baseline\n", $entry['file'], $entry['rule'], $entry['text']));
    }

    if ($result['new'] !== [] || $result['stale'] !== []) {
        fwrite(STDOUT, sprintf(
            "\nPHP translation lint: %d new finding(s), %d baseline entr(ies) no longer found. Wrap the words in a translation function with the 'corex' domain.\n",
            count($result['new']),
            count($result['stale']),
        ));
        exit(1);
    }

    fwrite(STDOUT, sprintf("PHP translation lint: nothing new (%d known, in the baseline).\n", count($baseline)));
}
