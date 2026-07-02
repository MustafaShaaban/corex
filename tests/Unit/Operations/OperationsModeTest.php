<?php

/**
 * Unit tests for the pure Operations Mode model (spec 065). No WordPress.
 * Contract: valid modes only, production/maintenance need confirmation, only maintenance affects
 * public behaviour, unknown values normalise to production (the safe default).
 *
 * @package Corex\Tests\Unit\Operations
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Operations\OperationsMode;

beforeEach(function () {
    Functions\when('__')->returnArg();
    $this->modes = new OperationsMode();
});

it('lists the four real modes and validates them', function () {
    expect($this->modes->all())->toBe(['development', 'staging', 'production', 'maintenance'])
        ->and($this->modes->isValid('production'))->toBeTrue()
        ->and($this->modes->isValid('coming-soon'))->toBeFalse();
});

it('normalises an unknown mode to production, never an invented mode', function () {
    expect($this->modes->normalize('banana'))->toBe('production')
        ->and($this->modes->normalize(''))->toBe('production')
        ->and($this->modes->normalize('staging'))->toBe('staging');
});

it('requires confirmation only for production and maintenance', function () {
    expect($this->modes->requiresConfirmation('production'))->toBeTrue()
        ->and($this->modes->requiresConfirmation('maintenance'))->toBeTrue()
        ->and($this->modes->requiresConfirmation('development'))->toBeFalse()
        ->and($this->modes->requiresConfirmation('staging'))->toBeFalse();
});

it('marks only maintenance as changing public behaviour', function () {
    expect($this->modes->affectsPublic('maintenance'))->toBeTrue()
        ->and($this->modes->affectsPublic('production'))->toBeFalse()
        ->and($this->modes->affectsPublic('development'))->toBeFalse();
});

it('describes each mode with a label, tone, and detail', function () {
    expect($this->modes->describe('development')['tone'])->toBe(OperationsMode::TONE_INFO)
        ->and($this->modes->describe('staging')['tone'])->toBe(OperationsMode::TONE_WARNING)
        ->and($this->modes->describe('production')['tone'])->toBe(OperationsMode::TONE_SUCCESS)
        ->and($this->modes->describe('maintenance')['tone'])->toBe(OperationsMode::TONE_DANGER)
        ->and($this->modes->describe('banana')['mode'])->toBe('production');
});

it('gives maintenance a lockout-prevention warning', function () {
    $warnings = implode(' ', $this->modes->warnings('maintenance'));

    expect($warnings)->toContain('admin access')
        ->and($this->modes->warnings('production'))->not->toBe([]);
});
