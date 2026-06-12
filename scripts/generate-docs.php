<?php

/**
 * Headless class-reference generator — the CI/build entrypoint for the same AST-based
 * generator that `wp corex docs:generate` runs, but with NO WordPress (so it works in CI
 * and any docs build). It mirrors the layer→source map wired in
 * packages/cli/src/CliServiceProvider.php; keep the two in sync.
 *
 * The generated pages are git-ignored (DECISIONS #50) — they are rebuilt from source here
 * so the published reference can never drift from the code.
 *
 * Usage:  php scripts/generate-docs.php
 *
 * @package Corex\Cli
 */

declare(strict_types=1);

// Corex source files carry a `defined('ABSPATH') || exit;` direct-access guard. Define it so
// they load outside WordPress; no WordPress is loaded — the generator only parses source ASTs.
defined('ABSPATH') || define('ABSPATH', __DIR__ . '/');

require dirname(__DIR__) . '/vendor/autoload.php';

use Corex\Cli\Docs\ClassDocReader;
use Corex\Cli\Docs\DocsGenerator;
use Corex\Cli\Docs\MarkdownDocRenderer;

$root = dirname(__DIR__);

$layers = [
    'Core'    => $root . '/plugins/corex-core/src',
    'Blocks'  => $root . '/plugins/corex-blocks/src',
    'Forms'   => $root . '/plugins/corex-forms/src',
    'Config'  => $root . '/plugins/corex-config/src',
    'CLI'     => $root . '/packages/cli/src',
    'Add-ons' => $root . '/addons',
];
$outputDir = $root . '/docs-app/src/content/docs/reference';

$generator = new DocsGenerator(new ClassDocReader(), new MarkdownDocRenderer());
$written = $generator->generate($layers, $outputDir);

fwrite(STDOUT, sprintf("Generated %d reference pages -> %s\n", count($written), $outputDir));
