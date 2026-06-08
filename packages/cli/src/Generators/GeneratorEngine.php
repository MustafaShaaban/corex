<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Generators;

defined('ABSPATH') || exit;

use Corex\Cli\Support\Naming;

/**
 * The WP-CLI-independent core: resolve the target path from the Config-driven
 * context, render the stub, and write the file idempotently (spec FR-002, FR-008).
 * Throws on an invalid name (Naming) or an unresolved placeholder (StubRenderer)
 * before writing anything; returns a structured GeneratorResult otherwise.
 */
final class GeneratorEngine
{
    public function __construct(
        private readonly StubRenderer $renderer,
        private readonly Naming $naming,
        private readonly GeneratorContext $context,
        private readonly string $stubsDir,
    ) {
    }

    public function generate(Generator $generator, string $rawName, bool $force = false): GeneratorResult
    {
        $className = $this->naming->classNameFor($rawName, $generator->suffix());

        $targetDir = rtrim($this->context->basePath, '/\\') . DIRECTORY_SEPARATOR . $generator->subPath();
        $path = $targetDir . DIRECTORY_SEPARATOR . $className . '.php';

        if (is_file($path) && ! $force) {
            return GeneratorResult::skipped($path);
        }

        $contents = $this->renderer->render(
            $this->readStub($generator->stub()),
            $generator->placeholders($className, $this->context),
        );

        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        if (file_put_contents($path, $contents) === false) {
            return GeneratorResult::error($path, 'Could not write the file.');
        }

        return GeneratorResult::created($path);
    }

    private function readStub(string $name): string
    {
        $file = rtrim($this->stubsDir, '/\\') . DIRECTORY_SEPARATOR . $name . '.stub';

        return (string) file_get_contents($file);
    }
}
