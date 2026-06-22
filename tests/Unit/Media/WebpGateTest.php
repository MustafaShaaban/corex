<?php

/**
 * WebP activation gate + tracked metadata + reset safety (spec 062): a WebP is served only when it passes
 * the gate (present/valid, dimensions match, smaller by the threshold); the metadata round-trips; and the
 * reset target only ever resolves a tracked, CoreX-generated `.webp` path (never an original / untracked).
 *
 * @package Corex\Tests\Unit\Media
 */

declare(strict_types=1);

use Corex\Media\MediaSettings;
use Corex\Media\WebpGate;
use Corex\Media\WebpMeta;
use Corex\Media\WebpResetCommand;

it('activates a smaller, valid, same-dimension derivative', function () {
    $gate = WebpGate::evaluate([
        'generated_valid'      => true,
        'original_bytes'       => 1000,
        'generated_bytes'      => 800,        // 20% smaller
        'original_dimensions'  => '800x600',
        'generated_dimensions' => '800x600',
    ], 5.0);

    expect($gate['active'])->toBeTrue()
        ->and($gate['reason'])->toBe('')
        ->and($gate['saving'])->toBe(20.0);
});

it('refuses to serve WebP that fails any gate condition', function () {
    // below the saving threshold
    $small = WebpGate::evaluate(['generated_valid' => true, 'original_bytes' => 1000, 'generated_bytes' => 980, 'original_dimensions' => '10x10', 'generated_dimensions' => '10x10'], 5.0);
    expect($small['active'])->toBeFalse()->and($small['reason'])->toContain('below-threshold');

    // dimensions mismatch
    $dim = WebpGate::evaluate(['generated_valid' => true, 'original_bytes' => 1000, 'generated_bytes' => 100, 'original_dimensions' => '10x10', 'generated_dimensions' => '9x10'], 5.0);
    expect($dim['active'])->toBeFalse()->and($dim['reason'])->toBe('dimensions-mismatch');

    // missing / invalid derivative
    $missing = WebpGate::evaluate(['generated_valid' => false, 'original_bytes' => 1000, 'generated_bytes' => 0, 'original_dimensions' => '10x10', 'generated_dimensions' => ''], 5.0);
    expect($missing['active'])->toBeFalse()->and($missing['reason'])->toBe('generated-missing-or-invalid');
});

it('measures a missing derivative as inactive and round-trips its metadata', function () {
    $meta = WebpMeta::measure('/no/such/original.jpg', '/no/such/original.webp', 82, 5.0, '2026-06-23T00:00:00+00:00');

    expect($meta->activeForDelivery)->toBeFalse()
        ->and($meta->inactiveReason)->not->toBe('')
        ->and($meta->quality)->toBe(82)
        ->and($meta->generatedAt)->toBe('2026-06-23T00:00:00+00:00');

    $restored = WebpMeta::fromArray($meta->toArray());
    expect($restored->toArray())->toBe($meta->toArray())
        ->and($restored->toArray())->toHaveKeys(['original_path', 'generated_path', 'saving', 'active_for_delivery', 'inactive_reason']);
});

it('only ever targets a tracked CoreX-generated .webp for deletion', function () {
    expect(WebpResetCommand::target(['generated_path' => '/u/a.webp']))->toBe('/u/a.webp')
        // never an original / non-webp, missing path, or absent record
        ->and(WebpResetCommand::target(['generated_path' => '/u/a.jpg']))->toBeNull()
        ->and(WebpResetCommand::target(['original_path' => '/u/a.jpg']))->toBeNull()
        ->and(WebpResetCommand::target([]))->toBeNull()
        ->and(WebpResetCommand::target(null))->toBeNull();
});

it('reads and clamps the min-saving threshold from settings', function () {
    expect(MediaSettings::defaults()->minSaving)->toBe(5.0)
        ->and(new MediaSettings(true, 82, true, true, 12.5))->toHaveProperty('minSaving', 12.5);
});
