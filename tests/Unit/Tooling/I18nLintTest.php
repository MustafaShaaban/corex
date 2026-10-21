<?php

/**
 * The PHP translation lint's rules (spec 116, US-NFR-06), read against source written here.
 *
 * @package Corex\Tests\Unit\Tooling
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/scripts/i18n-lint.php';

/** The rule of each finding in a piece of source. */
function lintRules(string $source): array
{
    return array_column(corex_i18n_lint_source('Example.php', "<?php\n" . $source), 'rule');
}

it('finds words echoed without a translation, with the file and line', function () {
    $findings = corex_i18n_lint_source('Screen.php', "<?php\n\necho '<p>Save changes</p>';\n");

    expect($findings)->toBe([
        ['file' => 'Screen.php', 'line' => 3, 'rule' => 'untranslated-output', 'text' => '<p>Save changes</p>'],
    ]);
});

it('finds words passed to an output function as they are', function () {
    expect(lintRules("echo esc_html('Not translated');"))->toBe(['untranslated-output'])
        ->and(lintRules("printf('%d items waiting', \$count);"))->toBe(['untranslated-output']);
});

it('finds words written as HTML outside the PHP tags', function () {
    expect(corex_i18n_lint_source('template.php', "<h1>Coming soon</h1>\n<?php echo 1;"))
        ->toHaveCount(1)
        ->and(corex_i18n_lint_source('template.php', "<h1>Coming soon</h1>")[0]['rule'])->toBe('untranslated-html');
});

it('accepts translated output, markup, placeholders and attribute words', function () {
    expect(lintRules(implode("\n", [
        "echo '<div class=\"corex-admin\">' . esc_html__('Saved.', 'corex') . '</div>';",
        "echo esc_html(sprintf(_n('%d person', '%d people', \$n, 'corex'), \$n));",
        "echo \$checked ? 'checked' : '';",
        "echo esc_html(\$context['displayName']);",
        "echo esc_attr(\$x ?: __('Reference', 'corex'));",
        "\$label = 'Kept in a variable';",
    ])))->toBe([]);
});

it('finds a translation call without the corex text domain', function () {
    expect(lintRules("__('Saved.');"))->toBe(['missing-domain'])
        ->and(lintRules("esc_html_e('Saved.', 'default');"))->toBe(['wrong-domain'])
        ->and(lintRules("_n('%d item', '%d items', \$n, \$domain);"))->toBe(['domain-not-literal'])
        ->and(lintRules("_x('Post', 'noun', 'corex'); _nx('a', 'b', 1, 'ctx', 'corex');"))->toBe([]);
});

it('does not read a method that shares a translation function name', function () {
    expect(lintRules("\$this->__('Saved.'); Foo::esc_html('Raw words here');"))->toBe([]);
});

it('compares by file, rule and text, so a moved line stays known and a fixed one is stale', function () {
    $known = ['file' => 'A.php', 'rule' => 'missing-domain', 'text' => '__(): Saved.'];
    $fixed = ['file' => 'B.php', 'rule' => 'missing-domain', 'text' => '__(): Gone.'];
    $moved = $known + ['line' => 90];
    $added = ['file' => 'C.php', 'line' => 4, 'rule' => 'untranslated-output', 'text' => 'New words'];

    expect(corex_i18n_compare([$moved, $added], [$known, $fixed]))->toBe([
        'new'   => [$added],
        'stale' => [$fixed],
    ]);
});

it('finds nothing new in the source CoreX ships today', function () {
    $root = dirname(__DIR__, 3);
    $findings = [];

    foreach (corex_i18n_files($root) as $file) {
        array_push($findings, ...corex_i18n_lint_source($file, (string) file_get_contents($root . '/' . $file)));
    }

    $baseline = json_decode((string) file_get_contents($root . '/tests/Fixtures/i18n-lint-baseline.json'), true)['entries'];

    expect(corex_i18n_compare($findings, $baseline))->toBe(['new' => [], 'stale' => []]);
});
