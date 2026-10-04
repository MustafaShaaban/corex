<?php

/**
 * Tests for the rules that govern a change of operations mode (spec 101, T002).
 *
 * These are the rules `OperationsModeController::handle()` held until spec 101, stated against a
 * service so the command line can be held to the same ones. Nothing in the first part is new
 * behaviour: each case is something the controller already did.
 *
 * The cases at the end are User Story 1 of spec 101 — turning Coming soon on, and off again. The
 * service needed no change for them, which is the point of the mode being a mode: they are here so
 * that the acceptance scenarios are stated somewhere that fails if that stops being true.
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
use Corex\Config\Operations\PreviewAccess;
use Corex\Config\Operations\ProductionLaunchService;
use Corex\Config\Operations\ProductionReadinessSnapshotFactory;
use Corex\Config\Operations\ReadinessEvaluatedEvent;
use Corex\Config\Security\HardeningChecks;
use Corex\Events\EventDispatcher;
use Corex\Events\ListenerProvider;
use Corex\Support\BootLogger;
use Corex\Tests\Fixtures\Operations\InMemoryPreviewAccessStore;

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

    $this->preview = new PreviewAccess(new InMemoryPreviewAccessStore(), 'a-key-only-the-site-knows');

    $this->service = new ModeChangeService(
        $modes,
        $this->store,
        new ProductionReadinessSnapshotFactory(new HardeningChecks()),
        new ProductionLaunchService($this->store),
        $this->preview,
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
        $this->preview,
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

// Spec 101, User Story 1 — an operator turns the coming-soon page on and off.

it('asks for the acknowledgement Coming soon needs, proposing the mode back, and writes nothing', function () {
    // US1.2
    $result = $this->service->apply(modeChange(OperationsMode::COMING_SOON));

    expect($result->status)->toBe(ModeChangeResult::NEEDS_ACKNOWLEDGEMENT)
        ->and($result->proposed)->toBe(OperationsMode::COMING_SOON)
        ->and($this->store->current())->toBe(OperationsMode::DEVELOPMENT)
        ->and(modeChangeLog())->toBe([]);
});

it('does not accept the production phrase in place of the Coming soon acknowledgement', function () {
    // FR-002: the acknowledgement is ticked. A typed phrase answers a different question.
    $result = $this->service->apply(modeChange(OperationsMode::COMING_SOON, phrase: 'PRODUCTION'));

    expect($result->status)->toBe(ModeChangeResult::NEEDS_ACKNOWLEDGEMENT)
        ->and(modeChangeLog())->toBe([]);
});

it('turns Coming soon on once acknowledged, and records who did it', function () {
    // US1.1
    $result = $this->service->apply(modeChange(OperationsMode::COMING_SOON, acknowledged: true));

    expect($result->status)->toBe(ModeChangeResult::SAVED)
        ->and($result->applied)->toBe(OperationsMode::COMING_SOON)
        ->and($this->store->current())->toBe(OperationsMode::COMING_SOON)
        ->and(modeChangeLog())->toHaveCount(1)
        ->and(modeChangeLog()[0]['to'])->toBe(OperationsMode::COMING_SOON)
        ->and(modeChangeLog()[0]['user'])->toBe(7);
});

it('writes nothing when Coming soon is applied to a site already in it', function () {
    // US1.4
    $this->service->apply(modeChange(OperationsMode::COMING_SOON, acknowledged: true));

    $again = $this->service->apply(modeChange(OperationsMode::COMING_SOON, acknowledged: true));

    expect($again->status)->toBe(ModeChangeResult::UNCHANGED)
        ->and(modeChangeLog())->toHaveCount(1);
});

it('leaves Coming soon by the rules of the mode it leaves for, not by any of its own', function () {
    // FR-002, second sentence; US1.3 for production. Staging needs nothing, so nothing is asked;
    // production still needs its phrase, and the acknowledgement that turned Coming soon on is
    // not one.
    $this->service->apply(modeChange(OperationsMode::COMING_SOON, acknowledged: true));

    $toProduction = $this->service->apply(modeChange(OperationsMode::PRODUCTION, acknowledged: true));
    $toStaging    = $this->service->apply(modeChange(OperationsMode::STAGING));

    expect($toProduction->status)->toBe(ModeChangeResult::NEEDS_PHRASE)
        ->and($toStaging->status)->toBe(ModeChangeResult::SAVED)
        ->and($this->store->current())->toBe(OperationsMode::STAGING)
        // Two rows: into Coming soon, and out of it. The refused production launch left none.
        ->and(modeChangeLog())->toHaveCount(2)
        ->and(modeChangeLog()[1]['from'])->toBe(OperationsMode::COMING_SOON)
        ->and(modeChangeLog()[1]['to'])->toBe(OperationsMode::STAGING);
});

// Spec 101, FR-012a — leaving Coming soon removes the preview link, whoever asked for the change.
// Stated against the service because the screen and the command line both go through it, so it
// cannot be true of one and not the other.

it('removes the preview link when the site leaves Coming soon for a mode that needs no confirmation', function () {
    $this->service->apply(modeChange(OperationsMode::COMING_SOON, acknowledged: true));
    $token = $this->preview->create(7, $this->now);
    $grant = $this->preview->grant($token, $this->now);

    $this->service->apply(modeChange(OperationsMode::STAGING));

    expect($this->preview->exists())->toBeFalse()
        ->and($this->preview->accepts($token))->toBeFalse()
        ->and($this->preview->honours($grant, $this->now))->toBeFalse();
});

it('removes the preview link when the site leaves Coming soon by launching', function () {
    // The launch goes through ProductionLaunchService, not the store call the other modes take.
    // The link has to go on that route too: it is the one a site in Coming soon is meant to leave by.
    $this->service->apply(modeChange(OperationsMode::COMING_SOON, acknowledged: true));
    $token = $this->preview->create(7, $this->now);

    $result = $this->service->apply(modeChange(OperationsMode::PRODUCTION, phrase: 'PRODUCTION'));

    expect($result->status)->toBe(ModeChangeResult::SAVED)
        ->and($this->store->current())->toBe(OperationsMode::PRODUCTION)
        ->and($this->preview->exists())->toBeFalse()
        ->and($this->preview->accepts($token))->toBeFalse();
});

it('starts with no link when the site comes back to Coming soon', function () {
    // US4.7: the old link is not waiting to work again.
    $this->service->apply(modeChange(OperationsMode::COMING_SOON, acknowledged: true));
    $token = $this->preview->create(7, $this->now);
    $this->service->apply(modeChange(OperationsMode::STAGING));

    $this->service->apply(modeChange(OperationsMode::COMING_SOON, acknowledged: true));

    expect($this->store->current())->toBe(OperationsMode::COMING_SOON)
        ->and($this->preview->exists())->toBeFalse()
        ->and($this->preview->accepts($token))->toBeFalse();
});

it('keeps the preview link when a change away from Coming soon did not happen', function (ModeChangeRequest $refused) {
    $this->service->apply(modeChange(OperationsMode::COMING_SOON, acknowledged: true));
    $token = $this->preview->create(7, $this->now);

    $this->service->apply($refused);

    // The site is still in Coming soon, so the client's link must still work.
    expect($this->store->current())->toBe(OperationsMode::COMING_SOON)
        ->and($this->preview->accepts($token))->toBeTrue();
})->with([
    'maintenance, not acknowledged'    => [fn () => modeChange(OperationsMode::MAINTENANCE)],
    'production, phrase not typed'     => [fn () => modeChange(OperationsMode::PRODUCTION)],
    'a mode that does not exist'       => [fn () => modeChange('holiday')],
    'Coming soon again'                => [fn () => modeChange(OperationsMode::COMING_SOON, acknowledged: true)],
]);

it('records leaving the mode as the mode change, with no separate row for the link', function () {
    // Plan, Decision 6: the mode change beside it is the record. A "revoked" row here would say
    // an operator revoked the link, which nobody did.
    $this->service->apply(modeChange(OperationsMode::COMING_SOON, acknowledged: true));
    $this->preview->create(7, $this->now);

    $this->service->apply(modeChange(OperationsMode::STAGING));

    expect(array_column($this->store->timeline(), 'event'))->toBe(['', '']);
});
