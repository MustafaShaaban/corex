<?php

/**
 * Unit tests for the spec 055 release metadata consistency check.
 *
 * @package Corex\Tests\Unit\Release
 */

declare(strict_types=1);

use Corex\Cli\Release\MetadataConsistencyCheck;

it('passes when every metadata surface matches the expected release', function () {
    $report = (new MetadataConsistencyCheck())->evaluate('0.26.1', [
        'plugins/corex-core/corex-core.php' => " * Version: 0.26.1\nUpdate URI: https://example.test/corex\n",
        'plugins/corex-core/src/Plugin.php' => "define('COREX_CORE_VERSION', '0.26.1');\n",
        'README.md' => 'Status: latest release **v0.26.1**.',
        'CHANGELOG.md' => "## [0.26.1] - 2026-06-15\n",
        'PROGRESS.md' => 'Verified release baseline is v0.26.1.',
    ]);

    expect($report['status'])->toBe('pass')
        ->and($report['expected'])->toBe('0.26.1')
        ->and($report['mismatches'])->toBe([]);
});

it('reports a plugin header mismatch with exact path and value', function () {
    $report = (new MetadataConsistencyCheck())->evaluate('0.26.1', [
        'plugins/corex-core/corex-core.php' => " * Plugin Name: Corex Core\n * Version: 0.1.0\n",
    ]);

    expect($report['status'])->toBe('fail')
        ->and($report['mismatches'])->toContain([
            'path' => 'plugins/corex-core/corex-core.php',
            'field' => 'Version',
            'expected' => '0.26.1',
            'actual' => '0.1.0',
            'status' => 'mismatch',
        ]);
});

it('reports a COREX version constant mismatch with the constant name', function () {
    $report = (new MetadataConsistencyCheck())->evaluate('0.26.1', [
        'plugins/corex-core/corex-core.php' => "define('COREX_CORE_VERSION', '0.26.0');\n",
    ]);

    expect($report['mismatches'])->toContain([
        'path' => 'plugins/corex-core/corex-core.php',
        'field' => 'COREX_CORE_VERSION',
        'expected' => '0.26.1',
        'actual' => '0.26.0',
        'status' => 'mismatch',
    ]);
});

it('reports README, CHANGELOG, and PROGRESS narrative mismatches separately', function () {
    $report = (new MetadataConsistencyCheck())->evaluate('0.26.1', [
        'README.md' => 'Status: latest release **v0.26.0**.',
        'CHANGELOG.md' => "## [0.26.0] - 2026-06-14\n",
        'PROGRESS.md' => 'Verified release baseline is v0.26.0.',
    ]);

    expect($report['mismatches'])->toContain(
        [
            'path' => 'README.md',
            'field' => 'latest release',
            'expected' => '0.26.1',
            'actual' => '0.26.0',
            'status' => 'mismatch',
        ],
        [
            'path' => 'CHANGELOG.md',
            'field' => 'latest changelog entry',
            'expected' => '0.26.1',
            'actual' => '0.26.0',
            'status' => 'mismatch',
        ],
        [
            'path' => 'PROGRESS.md',
            'field' => 'release baseline',
            'expected' => '0.26.1',
            'actual' => '0.26.0',
            'status' => 'mismatch',
        ],
    );
});

it('keeps policy exceptions visible instead of silently ignoring them', function () {
    $report = (new MetadataConsistencyCheck())->evaluate(
        '0.26.1',
        ['package.json' => '{"version":"0.1.0"}'],
        ['package.json:version' => 'npm workspace root version is independent of Corex release tags'],
    );

    expect($report['status'])->toBe('pass')
        ->and($report['mismatches'])->toContain([
            'path' => 'package.json',
            'field' => 'version',
            'expected' => '0.26.1',
            'actual' => '0.1.0',
            'status' => 'ignored-by-policy',
            'policy' => 'npm workspace root version is independent of Corex release tags',
        ]);
});
