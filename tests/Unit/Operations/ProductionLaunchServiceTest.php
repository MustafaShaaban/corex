<?php

/**
 * Tests for the Spec 068 Production launch gate: readiness blockers require a typed override.
 *
 * @package Corex\Tests\Unit\Operations
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OperationsModeStore;
use Corex\Config\Operations\ProductionLaunchOverride;
use Corex\Config\Operations\ProductionReadinessSnapshotFactory;
use Corex\Config\Operations\ProductionLaunchRequest;
use Corex\Config\Operations\ProductionLaunchService;
use Corex\Config\Operations\ReadinessSnapshot;
use Corex\Config\Security\HardeningChecks;
use Corex\Operations\OperationResult;

beforeEach(function () {
    Functions\when('__')->returnArg();

    $GLOBALS['corex_test_options'] = ['corex_operations_mode' => OperationsMode::STAGING];
    Functions\when('get_option')->alias(static fn (string $key, $default = false) => $GLOBALS['corex_test_options'][$key] ?? $default);
    Functions\when('update_option')->alias(static function (string $key, $value): bool {
        $GLOBALS['corex_test_options'][$key] = $value;

        return true;
    });
    Functions\when('wp_get_environment_type')->justReturn(OperationsMode::STAGING);

    $this->store = new OperationsModeStore(new OperationsMode());
    $this->now   = new DateTimeImmutable('2026-07-07T12:00:00+00:00');
});

function productionSnapshot(array $overrides = []): ReadinessSnapshot
{
    return new ReadinessSnapshot([
        [
            'key'            => 'ssl',
            'label'          => 'HTTPS',
            'state'          => 'pass',
            'summary'        => 'HTTPS is active.',
            'resolution_url' => '',
            'checked_at'     => '2026-07-07T11:59:00+00:00',
            'evidence_hash'  => hash('sha256', 'ssl-pass'),
        ],
        [
            'key'            => 'debug_display',
            'label'          => 'Debug output',
            'state'          => 'blocking',
            'summary'        => 'Debug output is visible to visitors.',
            'resolution_url' => 'https://corex.local/docs/security',
            'checked_at'     => '2026-07-07T11:59:00+00:00',
            'evidence_hash'  => hash('sha256', 'debug-blocking'),
        ],
        ...$overrides,
    ]);
}

it('summarizes blocking readiness checks and creates a production override confirmation', function () {
    $service = new ProductionLaunchService($this->store);
    $preview = $service->preview(productionSnapshot(), actorId: 7, now: $this->now);

    expect($preview->blocked())->toBeTrue()
        ->and($preview->blockingKeys())->toBe(['debug_display'])
        ->and($preview->confirmation->requiredPhrase)->toBe('PRODUCTION')
        ->and($preview->confirmation->operationKind)->toBe('operations.production.launch')
        ->and($preview->confirmation->actorId)->toBe(7);
});

it('does not switch to production when readiness is blocking and no typed override is supplied', function () {
    $service = new ProductionLaunchService($this->store);
    $request = new ProductionLaunchRequest(
        snapshot: productionSnapshot(),
        actorId: 7,
        now: $this->now,
        override: null,
    );

    $result = $service->apply($request);

    expect($result->state)->toBe(OperationResult::STATE_BLOCKED)
        ->and($result->errors[0]['code'])->toBe('readiness_blocking')
        ->and($this->store->current())->toBe(OperationsMode::STAGING);
});

it('does not switch to production when the typed override phrase is wrong', function () {
    $service = new ProductionLaunchService($this->store);
    $preview = $service->preview(productionSnapshot(), actorId: 7, now: $this->now);
    $request = new ProductionLaunchRequest(
        snapshot: productionSnapshot(),
        actorId: 7,
        now: $this->now,
        override: new ProductionLaunchOverride($preview->confirmation, 'prod'),
    );

    $result = $service->apply($request);

    expect($result->state)->toBe(OperationResult::STATE_BLOCKED)
        ->and($this->store->current())->toBe(OperationsMode::STAGING);
});

it('switches to production after a matching one-time typed PRODUCTION override', function () {
    $service = new ProductionLaunchService($this->store);
    $preview = $service->preview(productionSnapshot(), actorId: 7, now: $this->now);
    $request = new ProductionLaunchRequest(
        snapshot: productionSnapshot(),
        actorId: 7,
        now: $this->now,
        override: new ProductionLaunchOverride($preview->confirmation, 'PRODUCTION'),
    );

    $result = $service->apply($request);

    expect($result->state)->toBe(OperationResult::STATE_COMPLETED)
        ->and($result->affectedIds)->toBe(['operations-mode:production'])
        ->and($this->store->current())->toBe(OperationsMode::PRODUCTION)
        ->and(fn () => $service->apply($request))->toThrow(DomainException::class);
});

it('builds production readiness from the real hardening facts boundary', function () {
    Functions\when('is_ssl')->justReturn(false);
    Functions\when('force_ssl_admin')->justReturn(false);
    Functions\when('home_url')->justReturn('http://corex.test');
    Functions\when('username_exists')->justReturn(12);

    $snapshot = (new ProductionReadinessSnapshotFactory(new HardeningChecks()))->fromCurrentSite($this->now);

    expect($snapshot->hasBlockingChecks())->toBeTrue()
        ->and($snapshot->blockingKeys())->toContain('ssl', 'file_edit', 'default_admin')
        ->and($snapshot->checks()[0]['checked_at'])->toBe($this->now->format(DATE_ATOM));
});
