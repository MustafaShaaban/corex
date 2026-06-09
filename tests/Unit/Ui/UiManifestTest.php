<?php

/**
 * Unit tests for the UI manifest (spec 009 US3: FR-006, SC-004).
 *
 * The manifest enumerates exactly the dynamic blocks (from their block.json files) and the
 * section patterns that the library actually provides.
 *
 * @package Corex\Tests\Unit\Ui
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Ui\Patterns\PatternLibrary;
use Corex\Ui\UiManifest;

it('enumerates the actual dynamic blocks and section patterns', function () {
    Functions\when('__')->returnArg();
    Functions\when('esc_html__')->returnArg();

    $blocksDir = dirname(__DIR__, 3) . '/addons/corex-ui/src/Blocks';
    $manifest  = (new UiManifest(new PatternLibrary(), $blocksDir))->describe();

    expect($manifest['blocks'])->toContain('corex/posts', 'corex/breadcrumbs', 'corex/copyright');

    $patternNames = array_column($manifest['patterns'], 'name');
    expect($patternNames)->toContain('corex/hero', 'corex/contact')
        ->and($manifest['patterns'][0]['category'])->toBe('corex');
});
