<?php

/**
 * Unit tests for the pure Operations Mode model (spec 065; Coming soon added by spec 101). No
 * WordPress. Contract: valid modes only; production, maintenance and coming soon need confirmation;
 * maintenance and coming soon change what the public receives; unknown values normalise to
 * production (the safe default).
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

it('lists the five real modes, coming soon after maintenance, and validates them', function () {
    expect($this->modes->all())->toBe(['development', 'staging', 'production', 'maintenance', 'coming-soon'])
        ->and($this->modes->isValid('production'))->toBeTrue()
        // Until spec 101 this line asserted the opposite: spec 063 named coming soon as a mode and
        // spec 065 shipped without it. It is replaced rather than deleted (FR-022) so the history
        // of the claim stays in one place.
        ->and($this->modes->isValid('coming-soon'))->toBeTrue()
        ->and($this->modes->isValid('coming_soon'))->toBeFalse()
        ->and(OperationsMode::COMING_SOON)->toBe('coming-soon');
});

it('normalises an unknown mode to production, never an invented mode', function () {
    expect($this->modes->normalize('banana'))->toBe('production')
        ->and($this->modes->normalize(''))->toBe('production')
        ->and($this->modes->normalize('staging'))->toBe('staging');
});

it('requires confirmation for production and for the two modes that close the site to visitors', function () {
    expect($this->modes->requiresConfirmation('production'))->toBeTrue()
        ->and($this->modes->requiresConfirmation('maintenance'))->toBeTrue()
        ->and($this->modes->requiresConfirmation('coming-soon'))->toBeTrue()
        ->and($this->modes->requiresConfirmation('development'))->toBeFalse()
        ->and($this->modes->requiresConfirmation('staging'))->toBeFalse();
});

it('marks maintenance and coming soon, and nothing else, as changing public behaviour', function () {
    expect($this->modes->affectsPublic('maintenance'))->toBeTrue()
        ->and($this->modes->affectsPublic('coming-soon'))->toBeTrue()
        ->and($this->modes->affectsPublic('staging'))->toBeFalse()
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

it('describes coming soon as itself, not as the production it would otherwise fall back to', function () {
    $described = $this->modes->describe('coming-soon');

    expect($described['mode'])->toBe('coming-soon')
        ->and($described['label'])->toBe('Coming soon')
        // A warning, not the danger maintenance carries: the site is closed on purpose and is
        // answering 200, which is the difference between the two modes.
        ->and($described['tone'])->toBe(OperationsMode::TONE_WARNING)
        // The detail says who is served what, because that is what the mode does (FR-003).
        ->and($described['detail'])->toContain('coming-soon page')
        ->and($described['detail'])->toContain('edit posts');
});

it('warns that coming soon closes the public site, and says how it is opened again', function () {
    $warnings = implode(' ', $this->modes->warnings('coming-soon'));

    expect($this->modes->warnings('coming-soon'))->not->toBe($this->modes->warnings('production'))
        ->and($warnings)->toContain('coming-soon page')
        ->and($warnings)->toContain('redirected')
        // Published content stays readable through the REST API. Saying so here is the point of
        // FR-003: the screen must not let an operator believe the mode keeps content secret.
        ->and($warnings)->toContain('REST API')
        ->and($warnings)->toContain('Switch to production');
});

it('gives maintenance a lockout-prevention warning', function () {
    $warnings = implode(' ', $this->modes->warnings('maintenance'));

    expect($warnings)->toContain('admin access')
        ->and($this->modes->warnings('production'))->not->toBe([]);
});
