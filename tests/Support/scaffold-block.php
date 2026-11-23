<?php

/**
 * Runs `make:block`'s scaffolder outside WordPress, for a test that checks what it generates
 * (spec 136, SC-004): `tests/make-block-standard.test.js` asks for each shape.
 *
 *     php tests/Support/scaffold-block.php <base folder> <name> <in-place|records>
 *
 * Prints the scaffolder's result as JSON: `{"status": ..., "blockDir": ..., "paths": [...]}`.
 *
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

use Corex\Cli\Generators\BlockScaffolder;
use Corex\Cli\Generators\GeneratorContext;
use Corex\Cli\Generators\StubRenderer;
use Corex\Cli\Support\Naming;

// The generators carry the `defined('ABSPATH') || exit;` guard of every CoreX class.
if (! defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__, 2) . '/');
}

require dirname(__DIR__, 2) . '/vendor/autoload.php';

[, $base, $name, $shape] = $argv + [null, '', '', BlockScaffolder::IN_PLACE];

$result = (new BlockScaffolder(new StubRenderer(), new Naming(), dirname(__DIR__, 2) . '/packages/cli/stubs'))
    ->scaffold((string) $name, new GeneratorContext((string) $base, 'App', 'corex'), false, (string) $shape);

echo json_encode(['status' => $result->status, 'blockDir' => $result->blockDir, 'paths' => $result->paths]);
