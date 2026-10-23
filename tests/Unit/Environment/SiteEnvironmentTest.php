<?php

/**
 * Spec 113, US1 (FR-001 to FR-004b): one answer to "is this a development, staging or
 * production site", and the mode store that applies it.
 *
 * @package Corex\Tests\Unit\Environment
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Operations\ModeChangeResult;
use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OperationsModeStore;
use Corex\Environment\SiteEnvironment;
use Corex\Support\Config\Source;

/** A lock layer that answers `app.env` with the given value, or does not answer when it is null. */
function environmentLock(?string $value): Source
{
    return new class ($value) implements Source {
        public function __construct(private readonly ?string $value)
        {
        }

        public function has(string $key): bool
        {
            return $key === 'app.env' && $this->value !== null;
        }

        public function get(string $key): mixed
        {
            return $this->value;
        }
    };
}

/** @param array{dotenv?:string,network?:string,option?:string} $locks */
function siteEnvironment(array $locks = []): SiteEnvironment
{
    return new SiteEnvironment([
        [SiteEnvironment::BY_DOTENV, environmentLock($locks['dotenv'] ?? null)],
        [SiteEnvironment::BY_NETWORK, environmentLock($locks['network'] ?? null)],
        [SiteEnvironment::BY_OPTION, environmentLock($locks['option'] ?? null)],
    ]);
}

beforeEach(function () {
    Functions\when('__')->returnArg();
    $GLOBALS['corex_test_options'] = [];
    $GLOBALS['corex_test_wp_env'] = 'production';
    Functions\when('get_option')->alias(static fn (string $key, $default = false) => $GLOBALS['corex_test_options'][$key] ?? $default);
    Functions\when('wp_get_environment_type')->alias(static fn (): string => $GLOBALS['corex_test_wp_env']);
});

it('answers by the first setting that holds a value', function (array $locks, string $mode, string $wordpress, string $environment, string $decidedBy) {
    $GLOBALS['corex_test_options'][SiteEnvironment::MODE_OPTION] = $mode;
    $GLOBALS['corex_test_wp_env'] = $wordpress;

    $answer = siteEnvironment($locks)->current();

    expect($answer->environment)->toBe($environment)
        ->and($answer->decidedBy)->toBe($decidedBy)
        ->and($answer->isLocked())->toBe(in_array($decidedBy, SiteEnvironment::LOCKS, true));
})->with([
    '.env beats everything' => [['dotenv' => 'production', 'network' => 'staging', 'option' => 'development'], 'development', 'local', 'production', 'dotenv'],
    'the network beats the option' => [['network' => 'staging', 'option' => 'development'], 'production', 'production', 'staging', 'network'],
    'the corex_app_env option locks' => [['option' => 'local'], 'production', 'production', 'development', 'option'],
    'the mode, with no lock' => [[], 'staging', 'production', 'staging', 'mode'],
    'coming soon is production' => [[], 'coming-soon', 'local', 'production', 'mode'],
    'maintenance is production' => [[], 'maintenance', 'development', 'production', 'mode'],
    'WordPress local, no mode' => [[], '', 'local', 'development', 'wordpress'],
    'WordPress development, no mode' => [[], '', 'development', 'development', 'wordpress'],
    'WordPress staging, no mode' => [[], '', 'staging', 'staging', 'wordpress'],
    'nothing set (an upgraded site)' => [[], '', 'production', 'production', 'wordpress'],
    'an unknown APP_ENV is production' => [['dotenv' => 'qa-box'], 'development', 'local', 'production', 'dotenv'],
    'a stored value that is not a mode is skipped' => [[], 'nonsense', 'staging', 'staging', 'wordpress'],
]);

it('allows only the modes of a locked environment', function () {
    $production = siteEnvironment(['dotenv' => 'production']);
    $development = siteEnvironment(['network' => 'development']);

    expect($production->allowsMode(OperationsMode::PRODUCTION))->toBeTrue()
        ->and($production->allowsMode(OperationsMode::COMING_SOON))->toBeTrue()
        ->and($production->allowsMode(OperationsMode::MAINTENANCE))->toBeTrue()
        ->and($production->allowsMode(OperationsMode::DEVELOPMENT))->toBeFalse()
        ->and($production->allowsMode(OperationsMode::STAGING))->toBeFalse()
        ->and($development->allowsMode(OperationsMode::DEVELOPMENT))->toBeTrue()
        ->and($development->allowsMode(OperationsMode::PRODUCTION))->toBeFalse()
        ->and($development->allowsMode(OperationsMode::STAGING))->toBeFalse()
        ->and($development->allowsMode(OperationsMode::COMING_SOON))->toBeTrue()
        ->and($development->allowsMode(OperationsMode::MAINTENANCE))->toBeTrue()
        ->and(siteEnvironment()->allowsMode(OperationsMode::STAGING))->toBeTrue();
});

it('keeps a declared Coming soon or Maintenance under any lock, closed to visitors', function (string $lock, string $mode) {
    $GLOBALS['corex_test_options'][OperationsModeStore::OPTION] = $mode;

    expect((new OperationsModeStore(new OperationsMode(), siteEnvironment(['dotenv' => $lock])))->current())->toBe($mode);
})->with([
    'local, coming soon' => ['local', OperationsMode::COMING_SOON],
    'development, maintenance' => ['development', OperationsMode::MAINTENANCE],
    'staging, coming soon' => ['staging', OperationsMode::COMING_SOON],
    'production, maintenance' => ['production', OperationsMode::MAINTENANCE],
]);

it('gives the mode a lock fixes, and keeps Coming soon under a production lock', function () {
    $GLOBALS['corex_test_options'][OperationsModeStore::OPTION] = OperationsMode::COMING_SOON;
    expect((new OperationsModeStore(new OperationsMode(), siteEnvironment(['dotenv' => 'production'])))->current())
        ->toBe(OperationsMode::COMING_SOON);

    $GLOBALS['corex_test_options'][OperationsModeStore::OPTION] = OperationsMode::DEVELOPMENT;
    $store = new OperationsModeStore(new OperationsMode(), siteEnvironment(['dotenv' => 'production']));
    expect($store->current())->toBe(OperationsMode::PRODUCTION)
        ->and($store->lock()?->decidedBy)->toBe(SiteEnvironment::BY_DOTENV)
        ->and($store->allows(OperationsMode::DEVELOPMENT))->toBeFalse();

    $GLOBALS['corex_test_options'][OperationsModeStore::OPTION] = OperationsMode::PRODUCTION;
    expect((new OperationsModeStore(new OperationsMode(), siteEnvironment(['option' => 'staging'])))->current())
        ->toBe(OperationsMode::STAGING);
});

it('treats WordPress local as development when no mode is declared', function () {
    $GLOBALS['corex_test_wp_env'] = 'local';

    expect((new OperationsModeStore(new OperationsMode()))->current())->toBe(OperationsMode::DEVELOPMENT)
        ->and((new OperationsModeStore(new OperationsMode(), siteEnvironment()))->lock())->toBeNull();
});

it('names a lock refusal as its own result', function () {
    expect(ModeChangeResult::locked(OperationsMode::STAGING)->status)->toBe(ModeChangeResult::LOCKED)
        ->and(ModeChangeResult::locked(OperationsMode::STAGING)->proposed)->toBe(OperationsMode::STAGING);
});
