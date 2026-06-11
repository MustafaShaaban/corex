<?php

/**
 * Unit tests for the reset safety gate (spec 025 US2: FR-005, FR-009, SC-002).
 * Pure — no WordPress, no database.
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Corex\Cli\Reset\ResetGate;
use Corex\Cli\Reset\ResetRequest;

it('always permits a soft reset', function () {
    $gate = new ResetGate();

    expect($gate->permits(new ResetRequest(ResetRequest::SOFT)))->toBeTrue()
        ->and($gate->permits(new ResetRequest(ResetRequest::SOFT, dryRun: true)))->toBeTrue();
});

it('refuses a full reset without the typed safeguard (fail-closed)', function () {
    $gate = new ResetGate();

    expect($gate->permits(new ResetRequest(ResetRequest::FULL, confirmed: false)))->toBeFalse();
});

it('permits a full reset only when confirmed', function () {
    $gate = new ResetGate();

    expect($gate->permits(new ResetRequest(ResetRequest::FULL, confirmed: true)))->toBeTrue();
});
