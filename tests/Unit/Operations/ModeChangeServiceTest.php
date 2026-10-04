<?php

/**
 * Tests for the rules that govern a change of operations mode (spec 101, T002).
 *
 * These are the rules `OperationsModeController::handle()` held until spec 101, stated against a
 * service so the command line can be held to the same ones. Nothing here is new behaviour: each
 * case is something the controller already did.
 *
 * @package Corex\Tests\Unit\Operations
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Operations\ModeChangeRequest;
use Corex\Config\Operations\ModeChangeResult;
use Corex\Config\Operations\ModeChangeService;
use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OperationsModeStore;
use Corex\Config\Operations\ProductionLaunchService;
use Corex\Config\Operations\ProductionReadinessSnapshotFactory;
use Corex\Config\Operations\ReadinessEvaluatedEvent;
use Corex\Config\Security\HardeningChecks;
use Corex\Events\EventDispatcher;
use Corex\Events\ListenerProvider;
use Corex\Support\BootLogger;

beforeEach(function () {
    Functions\when('__')->returnArg();

    $GLOBALS['corex_test_options'] = [];
    Functions\when('get_option')->alias(
        static fn (string $key, $default = false) => $GLOBALS['corex_test_options'][$key] ?? $default,
    );
    Functions\when('update_option')->alias(static function (string $key, $value): bool {
        $GLOBALS['corex_test_options'][$key] = $value;

        return true;
    });
    Functions\when('wp_get_environment_type')->justReturn(OperationsMode::DEVELOPMENT);

    // What the readiness snapshot reads on the way to a production launch.
    Functions\when('is_ssl')->justReturn(false);
    Functions\when('force_ssl_admin')->justReturn(false);
    Functions\when('home_url')->justReturn('http://corex.test');
    Functions\when('username_exists')->justReturn(12);

    $modes       = new OperationsMode();
    $this->store = new OperationsModeStore($modes);
    $this->now   = new DateTimeImmutable('2026-10-04T12:00:00+00:00');

    $this->service = new ModeChangeService(
        $modes,
        $this->store,
        new ProductionReadinessSnapshotFactory(new HardeningChecks()),
        new ProductionLaunchService($this->store),
    );
});

/** @return list<array<string,mixed>> */
function modeChangeLog(): array
{
    return $GLOBALS['corex_test_options']['corex_operations_mode_log'] ?? [];
}

function modeChange(string $mode, bool $acknowledged = false, string $phrase = ''): ModeChangeRequest
{
    return new ModeChangeRequest(
        mode: $mode,
        actorId: 7,
        now: new DateTimeImmutable('2026-10-04T12:00:00+00:00'),
        acknowledged: $acknowledged,
        phrase: $phrase,
    );
}

it('refuses a mode it does not know, and writes nothing', function () {
    $result = $this->service->apply(modeChange('holiday'));

    expect($result->status)->toBe(ModeChangeResult::INVALID)
        ->and(modeChangeLog())->toBe([]);
});

it('saves a mode that needs no confirmation', function () {
    $result = $this->service->apply(modeChange(OperationsMode::STAGING));

    expect($result->status)->toBe(ModeChangeResult::SAVED)
        ->and($result->applied)->toBe(OperationsMode::STAGING)
        ->and($this->store->current())->toBe(OperationsMode::STAGING)
        ->and(modeChangeLog())->toHaveCount(1)
        ->and(modeChangeLog()[0]['user'])->toBe(7);
});

it('asks for the acknowledgement maintenance needs, proposing the mode back, and writes nothing', function () {
    $result = $this->service->apply(modeChange(OperationsMode::MAINTENANCE));

    expect($result->status)->toBe(ModeChangeResult::NEEDS_ACKNOWLEDGEMENT)
        ->and($result->proposed)->toBe(OperationsMode::MAINTENANCE)
        ->and($result->applied)->toBe('')
        ->and($this->store->isDeclared())->toBeFalse()
        ->and(modeChangeLog())->toBe([]);
});

it('saves maintenance once it is acknowledged', function () {
    $result = $this->service->apply(modeChange(OperationsMode::MAINTENANCE, acknowledged: true));

    expect($result->status)->toBe(ModeChangeResult::SAVED)
        ->and($this->store->current())->toBe(OperationsMode::MAINTENANCE);
});

it('says nothing changed when the declared mode is applied again, and logs nothing', function () {
    $this->service->apply(modeChange(OperationsMode::STAGING));
    $result = $this->service->apply(modeChange(OperationsMode::STAGING));

    expect($result->status)->toBe(ModeChangeResult::UNCHANGED)
        ->and($result->applied)->toBe(OperationsMode::STAGING)
        ->and(modeChangeLog())->toHaveCount(1);
});

it('counts declaring the mode a site had only inherited as a change', function () {
    // The site follows its environment type, development, without having declared it. Stating it
    // is a real transition and belongs in the history.
    $result = $this->service->apply(modeChange(OperationsMode::DEVELOPMENT));

    expect($result->status)->toBe(ModeChangeResult::SAVED)
        ->and($this->store->isDeclared())->toBeTrue()
        ->and(modeChangeLog())->toHaveCount(1);
});

it('asks for the typed phrase before a production launch, and writes nothing', function (string $phrase) {
    $result = $this->service->apply(modeChange(OperationsMode::PRODUCTION, phrase: $phrase));

    expect($result->status)->toBe(ModeChangeResult::NEEDS_PHRASE)
        ->and($result->proposed)->toBe(OperationsMode::PRODUCTION)
        ->and($this->store->isDeclared())->toBeFalse()
        ->and(modeChangeLog())->toBe([]);
})->with(['', 'production', 'PRODUCTION ']);

it('does not take an acknowledgement in place of the production phrase', function () {
    $result = $this->service->apply(modeChange(OperationsMode::PRODUCTION, acknowledged: true));

    expect($result->status)->toBe(ModeChangeResult::NEEDS_PHRASE)
        ->and($this->store->isDeclared())->toBeFalse();
});

it('evaluates readiness, and announces it, even when the production phrase is still owed', function () {
    // The controller did this in this order, and the announcement is what lets the Notification
    // Center reconcile readiness warnings. A refactor that checked the phrase first would have
    // stopped a submit without the phrase from refreshing them — a behaviour change nobody asked for.
    $listeners = new ListenerProvider();
    $announced = 0;
    $listeners->listen(ReadinessEvaluatedEvent::class, static function () use (&$announced): void {
        $announced++;
    });

    $modes   = new OperationsMode();
    $service = new ModeChangeService(
        $modes,
        $this->store,
        new ProductionReadinessSnapshotFactory(
            new HardeningChecks(),
            new EventDispatcher($listeners, new BootLogger(debug: false)),
        ),
        new ProductionLaunchService($this->store),
    );

    $result = $service->apply(modeChange(OperationsMode::PRODUCTION));

    expect($result->status)->toBe(ModeChangeResult::NEEDS_PHRASE)
        ->and($announced)->toBe(1);
});

it('launches to production through the launch service once the phrase is typed', function () {
    $result = $this->service->apply(modeChange(OperationsMode::PRODUCTION, phrase: 'PRODUCTION'));

    expect($result->status)->toBe(ModeChangeResult::SAVED)
        ->and($result->applied)->toBe(OperationsMode::PRODUCTION)
        ->and($this->store->current())->toBe(OperationsMode::PRODUCTION);
});
