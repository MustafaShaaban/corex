<?php

/**
 * Unit tests for the pure retention settings rules (spec 065). No WordPress.
 *
 * @package Corex\Tests\Unit\Retention
 */

declare(strict_types=1);

use Corex\Config\Retention\RetentionSettings;

beforeEach(function () {
    $this->settings = new RetentionSettings();
});

it('clamps the retention window: negatives become keep-forever, huge values are capped', function () {
    expect($this->settings->sanitizeDays(-5))->toBe(0)
        ->and($this->settings->sanitizeDays('30'))->toBe(30)
        ->and($this->settings->sanitizeDays(999999))->toBe(RetentionSettings::MAX_DAYS)
        ->and($this->settings->sanitizeDays('abc'))->toBe(0);
});

it('treats a zero window as retention disabled', function () {
    expect($this->settings->isEnabled(0))->toBeFalse()
        ->and($this->settings->isEnabled(1))->toBeTrue();
});

it('builds a truthful dry-run preview that only prunes when enabled with matches', function () {
    expect($this->settings->preview(0, 10)['willPrune'])->toBeFalse()
        ->and($this->settings->preview(30, 0)['willPrune'])->toBeFalse()
        ->and($this->settings->preview(30, 4))->toMatchArray([
            'days' => 30, 'enabled' => true, 'count' => 4, 'willPrune' => true,
        ]);
});

it('never reports a negative preview count', function () {
    expect($this->settings->preview(30, -3)['count'])->toBe(0);
});
