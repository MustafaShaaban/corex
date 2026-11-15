<?php

/**
 * Every name a managed table translates at run time is in the translation template's source
 * (spec 121, US-NFR-02, DECISIONS #467).
 *
 * @package Corex\Tests\Unit\I18n
 */

declare(strict_types=1);

use Corex\Tests\Support\ThemeContract;

it('lists every managed table name and column name for the translation template', function () {
    $listed = (string) file_get_contents(ThemeContract::root() . '/plugins/corex-core/languages/runtime-strings.php');
    $sources = array_merge(
        glob(ThemeContract::root() . '/plugins/*/src/*/*.php') ?: [],
        glob(ThemeContract::root() . '/plugins/*/src/*/*/*.php') ?: [],
        glob(ThemeContract::root() . '/addons/*/src/*/*.php') ?: [],
    );
    $missing = [];

    foreach ($sources as $file) {
        $code = (string) file_get_contents($file);
        if (! str_contains($code, 'new ManagedTable(')) {
            continue;
        }

        preg_match_all("/new ManagedTable\\(\\s*[^,]+,\\s*'([^']+)'/", $code, $tables);
        preg_match_all("/'label'\\s*=>\\s*'([^']+)'/", $code, $columns);

        foreach (array_merge($tables[1], $columns[1]) as $name) {
            if (! str_contains($listed, "__('{$name}', 'corex');")) {
                $missing[] = $name . ' (' . basename($file) . ')';
            }
        }
    }

    expect($missing)->toBe([]);
});
