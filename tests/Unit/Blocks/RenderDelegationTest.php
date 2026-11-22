<?php

/**
 * Unit tests for the container-resolved render callback (spec 004 US3: FR-008, FR-010), and for the
 * wrapper it passes every renderer's markup through (spec 136 FR-001, FR-002, FR-003).
 *
 * @package Corex\Tests\Unit\Blocks
 */

declare(strict_types=1);

use Corex\Blocks\BlockPathResolver;
use Corex\Blocks\BlockWrapper;
use Corex\Blocks\DynamicBlockRegistrar;
use Corex\Blocks\PluginMountMap;
use Corex\Container\Container;
use Corex\Support\BootLogger;
use Corex\Tests\Fixtures\Blocks\EmptyRenderer;
use Corex\Tests\Fixtures\Blocks\FakeRenderer;
use Corex\Tests\Fixtures\Blocks\PassThroughWrapper;
use Corex\Tests\Fixtures\Blocks\RecordingWrapper;
use Corex\Tests\Fixtures\Blocks\ThrowingRenderer;

require_once __DIR__ . '/BlockFixtures.php';

function renderDelegationRegistrar(BlockWrapper $wrapper, ?BootLogger $logger = null): DynamicBlockRegistrar
{
    return new DynamicBlockRegistrar(
        new Container(),
        $logger ?? new BootLogger(debug: false),
        new PluginMountMap(),
        new BlockPathResolver(),
        $wrapper,
    );
}

it('resolves the renderer from the container and returns its markup', function () {
    $callback = renderDelegationRegistrar(new PassThroughWrapper())->renderCallback(FakeRenderer::class);

    expect($callback(['x' => 'hi'], '', (object) []))->toBe('rendered: hi');
});

it('returns empty output and logs when the renderer throws (non-fatal)', function () {
    $logger = new BootLogger(debug: false);
    $wrapper = new RecordingWrapper();

    $callback = renderDelegationRegistrar($wrapper, $logger)->renderCallback(ThrowingRenderer::class);

    expect($callback([], '', (object) []))->toBe('')
        ->and($logger->messages())->not->toBeEmpty();
});

it('FR-002: passes the renderer markup through the wrapper and returns what the wrapper returns', function () {
    $wrapper = new RecordingWrapper();

    $callback = renderDelegationRegistrar($wrapper)->renderCallback(FakeRenderer::class);

    expect($callback(['x' => 'hi'], '', (object) []))->toBe('[wrapped]rendered: hi')
        ->and($wrapper->calls)->toBe([['markup' => 'rendered: hi', 'root' => []]]);
});

it('FR-003: hands the wrapper the root classes the block names', function () {
    $wrapper = new RecordingWrapper();

    $callback = renderDelegationRegistrar($wrapper)->renderCallback(FakeRenderer::class, ['corex-flow', 'corex-form']);
    $callback(['x' => 'hi'], '', (object) []);

    expect($wrapper->calls)->toBe([['markup' => 'rendered: hi', 'root' => ['corex-flow', 'corex-form']]]);
});

it('FR-002: keeps empty markup empty without asking the wrapper', function () {
    $wrapper = new RecordingWrapper();

    $callback = renderDelegationRegistrar($wrapper)->renderCallback(EmptyRenderer::class);

    expect($callback([], '', (object) []))->toBe('')
        ->and($wrapper->calls)->toBe([]);
});
