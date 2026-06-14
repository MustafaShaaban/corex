<?php

/**
 * Unit tests for the new alert + badge component renderers (spec 051: US2, FR-004/FR-005).
 *
 * @package Corex\Tests\Unit\Ui
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Ui\Blocks\AlertRenderer;
use Corex\Ui\Blocks\BadgeRenderer;

beforeEach(function () {
    Functions\when('esc_attr')->returnArg();
    Functions\when('wp_kses_post')->returnArg();
});

it('renders an accessible alert with the variant by class', function () {
    $html = (new AlertRenderer())->render(['message' => 'Saved!', 'variant' => 'success'], '', (object) []);

    expect($html)->toContain('role="alert"')
        ->and($html)->toContain('corex-alert--success')
        ->and($html)->toContain('Saved!');
});

it('falls back to the info variant for an unknown variant', function () {
    $html = (new AlertRenderer())->render(['message' => 'Hi', 'variant' => 'bogus'], '', (object) []);

    expect($html)->toContain('corex-alert--info');
});

it('renders nothing for an empty alert message', function () {
    expect((new AlertRenderer())->render(['message' => ''], '', (object) []))->toBe('');
});

it('renders a labelled badge span, nothing when empty', function () {
    expect((new BadgeRenderer())->render(['label' => 'New'], '', (object) []))->toBe('<span class="corex-badge">New</span>')
        ->and((new BadgeRenderer())->render(['label' => ''], '', (object) []))->toBe('');
});
