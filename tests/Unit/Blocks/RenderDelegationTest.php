<?php

/**
 * Unit tests for the container-resolved render callback (spec US3: FR-008, FR-010).
 *
 * @package Corex\Tests\Unit\Blocks
 */

declare(strict_types=1);

use Corex\Blocks\BlockPathResolver;
use Corex\Blocks\DynamicBlockRegistrar;
use Corex\Blocks\PluginMountMap;
use Corex\Container\Container;
use Corex\Support\BootLogger;
use Corex\Tests\Fixtures\Blocks\FakeRenderer;
use Corex\Tests\Fixtures\Blocks\ThrowingRenderer;

require_once __DIR__ . '/BlockFixtures.php';

it('resolves the renderer from the container and returns its markup', function () {
    $registrar = new DynamicBlockRegistrar(new Container(), new BootLogger(debug: false), new PluginMountMap(), new BlockPathResolver());

    $callback = $registrar->renderCallback(FakeRenderer::class);

    expect($callback(['x' => 'hi'], '', (object) []))->toBe('rendered: hi');
});

it('returns empty output and logs when the renderer throws (non-fatal)', function () {
    $logger = new BootLogger(debug: false);
    $registrar = new DynamicBlockRegistrar(new Container(), $logger, new PluginMountMap(), new BlockPathResolver());

    $callback = $registrar->renderCallback(ThrowingRenderer::class);

    expect($callback([], '', (object) []))->toBe('')
        ->and($logger->messages())->not->toBeEmpty();
});
