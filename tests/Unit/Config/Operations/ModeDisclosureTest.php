<?php

/**
 * Unit tests for ModeDisclosure (spec 077, T008 / FR-006/FR-007).
 *
 * The defect this guards: the mode form rendered the typed PRODUCTION confirmation *and* the
 * maintenance acknowledgement for every mode, so a site in Development asked its operator to
 * acknowledge consequences that could not occur. `requiresConfirmation()` knew that production and
 * maintenance need *a* confirmation; it could not say *which*, and that collapse is what put both
 * fields on screen whenever either applied.
 *
 * @package Corex\Tests\Unit\Config\Operations
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Operations\ModeDisclosure;
use Corex\Config\Operations\OperationsMode;

beforeEach(function () {
    Functions\when('__')->returnArg();

    $this->disclosure = new ModeDisclosure(new OperationsMode());
});

it('asks each mode for its own confirmation and no other', function (string $mode, string $expected) {
    expect($this->disclosure->confirmationFor($mode))->toBe($expected);
})->with([
    'development' => [OperationsMode::DEVELOPMENT, ModeDisclosure::CONFIRM_NONE],
    'staging'     => [OperationsMode::STAGING, ModeDisclosure::CONFIRM_NONE],
    'production'  => [OperationsMode::PRODUCTION, ModeDisclosure::CONFIRM_PHRASE],
    'maintenance' => [OperationsMode::MAINTENANCE, ModeDisclosure::CONFIRM_ACKNOWLEDGEMENT],
]);

it('never asks for both confirmations at once', function (string $mode) {
    // The defect, stated directly: no mode may require the typed phrase AND the acknowledgement.
    $bothRequired = $this->disclosure->requiresPhrase($mode)
        && $this->disclosure->requiresAcknowledgement($mode);

    expect($bothRequired)->toBeFalse();
})->with([
    OperationsMode::DEVELOPMENT,
    OperationsMode::STAGING,
    OperationsMode::PRODUCTION,
    OperationsMode::MAINTENANCE,
]);

it('asks for the phrase only when going live', function () {
    expect($this->disclosure->requiresPhrase(OperationsMode::PRODUCTION))->toBeTrue()
        ->and($this->disclosure->requiresPhrase(OperationsMode::MAINTENANCE))->toBeFalse()
        ->and($this->disclosure->requiresPhrase(OperationsMode::DEVELOPMENT))->toBeFalse()
        ->and($this->disclosure->requiresPhrase(OperationsMode::STAGING))->toBeFalse();
});

it('asks for the acknowledgement only when visitors are affected', function () {
    expect($this->disclosure->requiresAcknowledgement(OperationsMode::MAINTENANCE))->toBeTrue()
        ->and($this->disclosure->requiresAcknowledgement(OperationsMode::PRODUCTION))->toBeFalse()
        ->and($this->disclosure->requiresAcknowledgement(OperationsMode::DEVELOPMENT))->toBeFalse()
        ->and($this->disclosure->requiresAcknowledgement(OperationsMode::STAGING))->toBeFalse();
});

it('agrees with OperationsMode about which modes need confirming at all', function (string $mode) {
    // Two sources on one question is how they drift. This asserts they cannot: whenever the older
    // boolean says a confirmation is needed, this must name one, and vice versa.
    $modes = new OperationsMode();

    expect($this->disclosure->confirmationFor($mode) !== ModeDisclosure::CONFIRM_NONE)
        ->toBe($modes->requiresConfirmation($mode));
})->with([
    OperationsMode::DEVELOPMENT,
    OperationsMode::STAGING,
    OperationsMode::PRODUCTION,
    OperationsMode::MAINTENANCE,
]);

it('describes every mode with a summary and real consequences', function () {
    foreach ($this->disclosure->describeAll() as $described) {
        expect($described['summary'])->not->toBe('', "{$described['mode']} has no summary")
            ->and($described['consequences'])
            ->not->toBeEmpty("{$described['mode']} lists no consequences");
    }
});

it('describes maintenance with what the guard actually does', function () {
    // Each line is checkable against MaintenanceGuard. A consequence list that describes behaviour
    // the code does not have is the kind of confident documentation that outlives the code.
    $consequences = implode(' ', $this->disclosure->describe(OperationsMode::MAINTENANCE)['consequences']);

    expect($consequences)->toContain('503')
        ->and($consequences)->toContain('administrators')
        ->and($consequences)->toContain('REST')
        ->and($consequences)->toContain('cron')
        ->and($consequences)->toContain('Recovery');
});

it('normalises an unknown mode rather than describing nothing', function () {
    // `normalize()` falls back to production, which is the safe direction: an unrecognised mode
    // must not silently become the one with no confirmation.
    $described = $this->disclosure->describe('not-a-mode');

    expect($described['mode'])->toBe(OperationsMode::PRODUCTION)
        ->and($described['confirmation'])->toBe(ModeDisclosure::CONFIRM_PHRASE);
});
