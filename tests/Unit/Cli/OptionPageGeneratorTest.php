<?php

/**
 * Unit test: make:option-page scaffolds a valid OptionPage definition (spec 039: FR-005).
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Corex\Cli\Generators\GeneratorContext;
use Corex\Cli\Generators\GeneratorEngine;
use Corex\Cli\Generators\OptionPageGenerator;
use Corex\Cli\Generators\StubRenderer;
use Corex\Cli\Support\Naming;

it('scaffolds a valid OptionPage definition with no leftover placeholders', function () {
    $base = sys_get_temp_dir() . '/corex_optionpage_' . uniqid('', true);
    mkdir($base);
    $stubs = dirname(__DIR__, 3) . '/packages/cli/stubs';
    $naming = new Naming();

    $engine = new GeneratorEngine(new StubRenderer(), $naming, new GeneratorContext($base, 'App', 'corex'), $stubs);
    $result = $engine->generate(new OptionPageGenerator(), 'Billing');

    $code = file_get_contents($result->path);

    expect($result->isCreated())->toBeTrue()
        ->and($code)->not->toContain('{{')
        ->and($code)->toContain('namespace App\\Options;')
        ->and($code)->toContain('final class Billing')
        ->and($code)->toContain('use Corex\\Config\\Options\\OptionPage;')
        ->and($code)->toContain("slug: 'billing'")
        ->and($code)->toContain("defined('ABSPATH') || exit;");
});
