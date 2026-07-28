<?php

/**
 * Unit tests for the pure version-stamping planner (spec 036 US2: FR-003). Given a target semver
 * and a map of file contents, it returns the rewritten contents for files that change — no disk,
 * no WordPress.
 *
 * @package Corex\Tests\Unit\Release
 */

declare(strict_types=1);

use Corex\Cli\Release\VersionPlan;

it('rejects an invalid version', function () {
    expect(VersionPlan::isValid('1.2'))->toBeFalse()
        ->and(VersionPlan::isValid('x.y.z'))->toBeFalse()
        ->and(VersionPlan::isValid('0.22.0'))->toBeTrue()
        ->and(VersionPlan::isValid('1.0.0-beta.1'))->toBeTrue();

    expect(fn () => (new VersionPlan())->plan('nope', ['a.php' => 'x']))
        ->toThrow(InvalidArgumentException::class);
});

it('stamps the plugin header Version line to the target version', function () {
    $before = " * Plugin Name: Corex Core\n * Version:           0.1.0\n * Requires PHP: 8.3\n";

    $after = (new VersionPlan())->plan('0.22.0', ['corex-core.php' => $before]);

    expect($after)->toHaveKey('corex-core.php')
        ->and($after['corex-core.php'])->toContain('Version:           0.22.0')
        ->and($after['corex-core.php'])->not->toContain('0.1.0');
});

it('stamps every COREX_*_VERSION constant', function () {
    $before = "define('COREX_CORE_VERSION', '0.1.0');\ndefine('COREX_BLOCKS_VERSION', '0.1.0');\n";

    $after = (new VersionPlan())->plan('0.22.0', ['boot.php' => $before]);

    expect($after['boot.php'])->toContain("define('COREX_CORE_VERSION', '0.22.0')")
        ->and($after['boot.php'])->toContain("define('COREX_BLOCKS_VERSION', '0.22.0')");
});

it('only returns files that actually change (idempotent when already aligned)', function () {
    $files = [
        'old.php'     => " * Version: 0.1.0\n",
        'aligned.php' => " * Version: 0.22.0\n",
        'unrelated.php' => "echo 'hello';\n",
    ];

    $after = (new VersionPlan())->plan('0.22.0', $files);

    expect($after)->toHaveKey('old.php')
        ->and($after)->not->toHaveKey('aligned.php')
        ->and($after)->not->toHaveKey('unrelated.php');
});

it('stamps the docs site so published docs cannot advertise the previous release', function () {
    // v0.35.0 shipped with the docs site still saying 0.34.0 because this file was outside the
    // command's reach and had to be bumped by hand. Verbatim shape of docs-app/src/version.ts.
    $before = <<<'TS'
        export const CURRENT_VERSION = '0.35.0';

        /** Where older releases and their notes live (real, published history). */
        export const RELEASES_URL = 'https://github.com/MustafaShaaban/corex/releases';
        TS;

    $after = (new VersionPlan())->plan('0.36.0', ['version.ts' => $before]);

    expect($after['version.ts'])->toContain("export const CURRENT_VERSION = '0.36.0';")
        // The neighbouring export is a URL, not a version — it must survive untouched.
        ->and($after['version.ts'])->toContain('https://github.com/MustafaShaaban/corex/releases');
});

it('rewrites only the first (header) Version line, not later prose', function () {
    $before = " * Version: 0.1.0\nChangelog: Version: 0.1.0 was the first release.\n";

    $after = (new VersionPlan())->plan('0.22.0', ['readme.php' => $before]);

    expect($after['readme.php'])->toContain('Version: 0.22.0')
        ->and($after['readme.php'])->toContain('Version: 0.1.0 was the first release.');
});

/**
 * `package.json` was stamped by hand at every release until 0.39.0. Anchored to the top-level key so
 * no dependency's version is rewritten — that is the failure mode a bare `"version"` search has.
 */
it('stamps the npm root version without touching any dependency version', function () {
    $before = <<<'JSON'
        {
            "name": "corex-framework",
            "version": "0.38.1",
            "dependencies": {
                "some-package": "0.38.1"
            }
        }
        JSON;

    $after = (new VersionPlan())->plan('0.39.0', ['package.json' => $before]);

    expect($after['package.json'])->toContain('"version": "0.39.0"')
        ->and($after['package.json'])->toContain('"some-package": "0.38.1"');
});

/**
 * The prose declarations. `ROADMAP.md` sat three releases behind a correct `README.md` because these
 * were outside the command's reach, which is exactly the drift the command's docblock promises not to
 * allow.
 */
it('stamps the sentence that declares the current version in prose', function (string $before, string $expected) {
    $after = (new VersionPlan())->plan('0.39.0', ['doc.md' => $before]);

    expect($after['doc.md'])->toBe($expected);
})->with([
    'README status heading' => [
        "## Status: v0.38.1, actively developed\n",
        "## Status: v0.39.0, actively developed\n",
    ],
    'README readiness command' => [
        "wp corex readiness 0.38.1\n",
        "wp corex readiness 0.39.0\n",
    ],
    'ROADMAP current release' => [
        "**Currently released: v0.38.1.** This roadmap is\n",
        "**Currently released: v0.39.0.** This roadmap is\n",
    ],
    'PROJECT-STATUS heading' => [
        "**Version 0.38.1** · updated 2026-07-28\n",
        "**Version 0.39.0** · updated 2026-07-28\n",
    ],
    'docs-site mirror heading' => [
        "**Version 0.38.1.** This page exists\n",
        "**Version 0.39.0.** This page exists\n",
    ],
]);

/**
 * The one that matters most, and the reason the prose patterns are anchored to whole sentences
 * rather than to a bare version string: `ROADMAP.md` §17 and the entire changelog are statements
 * about the *past*. Rewriting them would not update the record, it would corrupt it.
 */
it('leaves historical version references alone', function () {
    $before = "**Currently released: v0.38.1.**\n\n"
        . "20. **Spec 081** — released as **v0.38.1**, merged via PR #154.\n"
        . "## [0.38.1] — 2026-07-28\n"
        . "Corrected in v0.35.1 after v0.35.0 shipped with stale docs.\n";

    $after = (new VersionPlan())->plan('0.39.0', ['ROADMAP.md' => $before]);

    expect($after['ROADMAP.md'])
        ->toContain('**Currently released: v0.39.0.**')
        ->toContain('released as **v0.38.1**, merged via PR #154.')
        ->toContain('## [0.38.1] — 2026-07-28')
        ->toContain('Corrected in v0.35.1 after v0.35.0 shipped');
});
