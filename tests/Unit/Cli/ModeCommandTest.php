<?php

/**
 * Unit tests for `wp corex mode get|set` (spec 101, T048; FR-017, User Story 5).
 *
 * The command decides nothing about a mode change: it hands the request to the same
 * ModeChangeService the Operations screen uses, and these tests hold it to that. What it owns is
 * reading its arguments, and turning the service's answer into words and an exit code a deploy
 * script can act on. No WP-CLI here — `execute()` is the command without the printing.
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Cli\Commands\ModeCommand;
use Corex\Config\Operations\ModeChangeRequest;
use Corex\Config\Operations\ModeChangeService;
use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OperationsModeStore;
use Corex\Config\Operations\PreviewAccess;
use Corex\Config\Operations\ProductionLaunchService;
use Corex\Config\Operations\ProductionReadinessSnapshotFactory;
use Corex\Config\Security\HardeningChecks;
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
    Functions\when('wp_get_environment_type')->justReturn(OperationsMode::STAGING);
    Functions\when('is_ssl')->justReturn(false);
    Functions\when('force_ssl_admin')->justReturn(false);
    Functions\when('home_url')->justReturn('http://corex.test');
    Functions\when('username_exists')->justReturn(12);

    $modes         = new OperationsMode();
    $this->store   = new OperationsModeStore($modes);
    $this->preview = new PreviewAccess(new InMemoryPreviewAccessStore(), 'a-key-only-the-site-knows');
    $this->service = new ModeChangeService(
        $modes,
        $this->store,
        new ProductionReadinessSnapshotFactory(new HardeningChecks()),
        new ProductionLaunchService($this->store),
        $this->preview,
    );
    $this->command = new ModeCommand($this->service, $this->store, $modes);
    $this->now     = new DateTimeImmutable('2026-10-04T12:00:00+00:00');
});

/** @return list<array<string,mixed>> */
function modeCommandLog(): array
{
    return $GLOBALS['corex_test_options']['corex_operations_mode_log'] ?? [];
}

// get

it('reports a mode the site has only inherited, and says where it came from', function () {
    $result = $this->command->execute('get', [], [], 0, $this->now);

    expect($result->ok)->toBeTrue()
        ->and($result->shouldExitNonZero())->toBeFalse()
        ->and($result->mode)->toBe(OperationsMode::STAGING)
        ->and($result->message)->toContain('staging')
        ->and($result->message)->toContain('inherited')
        ->and($result->message)->toContain('WordPress environment type');
});

it('reports a declared mode as declared', function () {
    $this->store->set(OperationsMode::COMING_SOON, 3);

    $result = $this->command->execute('get', [], [], 0, $this->now);

    expect($result->mode)->toBe(OperationsMode::COMING_SOON)
        ->and($result->message)->toContain('coming-soon')
        ->and($result->message)->toContain('declared')
        ->and($result->message)->not->toContain('inherited');
});

it('changes nothing when it reads the mode', function () {
    $this->command->execute('get', [], [], 0, $this->now);

    expect($this->store->isDeclared())->toBeFalse()
        ->and(modeCommandLog())->toBe([]);
});

// set — the same rules as the screen

it('puts the site into Coming soon when the acknowledgement is given, with the same history row the screen writes', function () {
    $result = $this->command->execute('set', ['coming-soon'], ['acknowledge' => true], 7, $this->now);
    $fromTheCommand = modeCommandLog();

    // The same change, made the way the screen makes it, on a clean slate.
    $GLOBALS['corex_test_options'] = [];
    $this->service->apply(new ModeChangeRequest('coming-soon', 7, $this->now, acknowledged: true));
    $fromTheScreen = modeCommandLog();

    $withoutTime = static fn (array $rows): array => array_map(
        static fn (array $row): array => array_diff_key($row, ['time' => 0]),
        $rows,
    );

    expect($result->ok)->toBeTrue()
        ->and($result->shouldExitNonZero())->toBeFalse()
        ->and($result->mode)->toBe(OperationsMode::COMING_SOON)
        ->and($result->message)->toContain('coming-soon')
        ->and($fromTheCommand)->toHaveCount(1)
        ->and($withoutTime($fromTheCommand))->toBe($withoutTime($fromTheScreen));
});

it('refuses a mode that changes what visitors get without --acknowledge, changes nothing, and fails', function (string $mode) {
    // FR-017: a change from the command line does not bypass the acknowledgement.
    $result = $this->command->execute('set', [$mode], [], 7, $this->now);

    expect($result->ok)->toBeFalse()
        ->and($result->shouldExitNonZero())->toBeTrue()
        ->and($result->message)->toContain('--acknowledge')
        ->and($this->store->isDeclared())->toBeFalse()
        ->and(modeCommandLog())->toBe([]);
})->with([OperationsMode::COMING_SOON, OperationsMode::MAINTENANCE]);

it('sets a mode that needs no confirmation without asking for one', function () {
    $result = $this->command->execute('set', ['development'], [], 7, $this->now);

    expect($result->ok)->toBeTrue()
        ->and($this->store->current())->toBe(OperationsMode::DEVELOPMENT)
        ->and(modeCommandLog())->toHaveCount(1);
});

it('refuses to go live without the typed phrase, and does not take --acknowledge for it', function (array $flags) {
    $result = $this->command->execute('set', ['production'], $flags, 7, $this->now);

    expect($result->ok)->toBeFalse()
        ->and($result->shouldExitNonZero())->toBeTrue()
        ->and($result->message)->toContain('--phrase=PRODUCTION')
        ->and($this->store->isDeclared())->toBeFalse()
        ->and(modeCommandLog())->toBe([]);
})->with([
    'no confirmation'   => [[]],
    'the wrong phrase'  => [['phrase' => 'production']],
    'an acknowledgement' => [['acknowledge' => true]],
]);

it('goes live with the typed phrase and a user to record it against', function () {
    $result = $this->command->execute('set', ['production'], ['phrase' => 'PRODUCTION'], 7, $this->now);

    expect($result->ok)->toBeTrue()
        ->and($this->store->current())->toBe(OperationsMode::PRODUCTION)
        ->and(modeCommandLog())->toHaveCount(1)
        ->and(modeCommandLog()[0]['user'])->toBe(7);
});

it('will not go live as nobody, and says how to name somebody', function () {
    // WP-CLI runs as user 0 unless told otherwise, and a launch is recorded against a person.
    // Without this the launch service throws; the command says what to do instead.
    $result = $this->command->execute('set', ['production'], ['phrase' => 'PRODUCTION'], 0, $this->now);

    expect($result->ok)->toBeFalse()
        ->and($result->shouldExitNonZero())->toBeTrue()
        ->and($result->message)->toContain('--user')
        ->and($this->store->isDeclared())->toBeFalse()
        ->and(modeCommandLog())->toBe([]);
});

it('fails on a mode that does not exist, and lists the ones that do', function () {
    $result = $this->command->execute('set', ['holiday'], ['acknowledge' => true], 7, $this->now);

    expect($result->ok)->toBeFalse()
        ->and($result->shouldExitNonZero())->toBeTrue()
        ->and($result->message)->toContain('holiday')
        ->and($result->message)->toContain('development, staging, production, maintenance, coming-soon')
        ->and(modeCommandLog())->toBe([]);
});

it('fails when no mode is named', function () {
    $result = $this->command->execute('set', [], [], 7, $this->now);

    expect($result->ok)->toBeFalse()
        ->and($result->message)->toContain('wp corex mode set <mode>');
});

it('succeeds, and says nothing changed, when the site is already in the mode asked for', function () {
    // A deploy script that sets the mode on every run must not fail on the second run: the state
    // it wanted is the state it found.
    $this->command->execute('set', ['coming-soon'], ['acknowledge' => true], 7, $this->now);

    $again = $this->command->execute('set', ['coming-soon'], ['acknowledge' => true], 7, $this->now);

    expect($again->ok)->toBeTrue()
        ->and($again->shouldExitNonZero())->toBeFalse()
        ->and($again->message)->toContain('already')
        ->and(modeCommandLog())->toHaveCount(1);
});

it('removes the preview link when the command takes the site out of Coming soon', function () {
    // FR-012a, "from the screen and from the command line alike".
    $this->command->execute('set', ['coming-soon'], ['acknowledge' => true], 7, $this->now);
    $token = $this->preview->create(7, $this->now);

    $this->command->execute('set', ['staging'], [], 7, $this->now);

    expect($this->preview->exists())->toBeFalse()
        ->and($this->preview->accepts($token))->toBeFalse();
});

it('fails on an action that is neither get nor set', function () {
    $result = $this->command->execute('toggle', [], [], 7, $this->now);

    expect($result->ok)->toBeFalse()
        ->and($result->shouldExitNonZero())->toBeTrue();
});
