<?php

/**
 * @package Corex\Tests\Unit\Database
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Database\Schema\NetworkMigrationResult;
use Corex\Database\Schema\SchemaComponent;
use Corex\Database\Schema\SchemaMigrator;
use Corex\Database\Schema\SchemaRegistry;
use Corex\Database\Schema\SchemaVersionStore;
use Corex\Database\Schema\SiteMigrationOutcome;
use Corex\Database\Schema\SiteMigrationRunner;
use Corex\Database\Schema\Table;
use Corex\Events\EventDispatcher;
use Corex\Events\ListenerProvider;
use Corex\Multisite\Events\SiteMigrated;
use Corex\Multisite\Events\SiteMigrationFailed;
use Corex\Multisite\NetworkContext;
use Corex\Multisite\SiteScope;
use Corex\Multisite\SiteScoped;
use Corex\Support\BootLogger;

final class FakeSchemaMigrator100 implements SchemaMigrator
{
    /** @var array<string, true> */
    public array $existing = [];

    /** @var list<string> */
    public array $created = [];

    /** @var list<string> */
    public array $dropped = [];

    /** @var list<string> */
    public array $timeline;

    /** @param list<string> $timeline */
    public function __construct(array &$timeline)
    {
        $this->timeline =& $timeline;
    }

    public function fullName(string $name): string
    {
        return 'wp_corex_' . $name;
    }

    public function create(Table $table): void
    {
        $this->created[] = $table->name;
        $this->timeline[] = 'create:' . $table->name;
    }

    public function drop(string $name): void
    {
        $this->dropped[] = $name;
    }

    public function exists(string $name): bool
    {
        $this->timeline[] = 'exists:' . $name;

        return isset($this->existing[$name]);
    }
}

final class ArraySchemaVersionStore100 implements SchemaVersionStore
{
    /** @var array<string, string> */
    public array $versions = [];

    /** @var list<array{0: string, 1: string}> */
    public array $puts = [];

    /** @var list<string> */
    private array $timeline;

    /** @param list<string> $timeline */
    public function __construct(array &$timeline)
    {
        $this->timeline =& $timeline;
    }

    public function get(string $optionName): string
    {
        return $this->versions[$optionName] ?? '';
    }

    public function put(string $optionName, string $version): void
    {
        $this->timeline[] = 'put:' . $optionName . '=' . $version;
        $this->puts[] = [$optionName, $version];
        $this->versions[$optionName] = $version;
    }
}

final class FakeSiteScope100 implements SiteScope
{
    /** @var list<int> */
    public array $siteIds = [];

    /** @var array<int, true> */
    public array $throwingSites = [];

    public function currentSiteId(): int
    {
        return 1;
    }

    public function register(SiteScoped $service): void
    {
    }

    public function run(int $siteId, Closure $callback): mixed
    {
        $this->siteIds[] = $siteId;

        if (isset($this->throwingSites[$siteId])) {
            throw new RuntimeException('site ' . $siteId . ' exploded');
        }

        return $callback();
    }
}

final class FakeNetworkContext100 implements NetworkContext
{
    /** @var list<int> */
    public array $ids;

    /** @param list<int> $ids */
    public function __construct(array $ids)
    {
        $this->ids = $ids;
    }

    public function id(): int
    {
        return 1;
    }

    public function mainSiteId(): int
    {
        return 1;
    }

    public function siteCount(): int
    {
        return count($this->ids);
    }

    public function siteIds(int $limit = 0, int $offset = 0): array
    {
        return array_values(array_slice($this->ids, $offset, $limit > 0 ? $limit : null));
    }

    public function exists(int $siteId): bool
    {
        return in_array($siteId, $this->ids, true);
    }
}

/**
 * @param list<int> $siteIds
 * @return array{0: SiteMigrationRunner, 1: FakeSchemaMigrator100, 2: ArraySchemaVersionStore100, 3: FakeSiteScope100, 4: ListenerProvider}
 */
function migrationRunner100(array $siteIds = [1]): array
{
    $timeline = [];
    $migrator = new FakeSchemaMigrator100($timeline);
    $versions = new ArraySchemaVersionStore100($timeline);
    $scope = new FakeSiteScope100();
    $schemas = new SchemaRegistry();
    $schemas->register(new SchemaComponent(
        'product-foundation',
        '3',
        [new Table('activity'), new Table('jobs')],
        'corex_product_foundation_schema_version',
    ));
    $listeners = new ListenerProvider();
    $events = new EventDispatcher($listeners, new BootLogger(false));

    Functions\when('Corex\Database\Schema\is_file')->justReturn(true);
    Functions\when('do_action')->justReturn(null);

    return [
        new SiteMigrationRunner(
            $migrator,
            $schemas,
            $versions,
            $scope,
            new FakeNetworkContext100($siteIds),
            $events,
        ),
        $migrator,
        $versions,
        $scope,
        $listeners,
    ];
}

it('performs zero schema writes when every component is current', function () {
    [$runner, $migrator, $versions, $scope] = migrationRunner100();
    $versions->versions['corex_product_foundation_schema_version'] = '3';

    $result = $runner->runForSite(1);

    expect($result->outcome)->toBe(SiteMigrationOutcome::AlreadyCurrent)
        ->and($result->succeeded())->toBeTrue()
        ->and($migrator->created)->toBe([])
        ->and($versions->puts)->toBe([])
        ->and($scope->siteIds)->toBe([1]);
});

it('creates every table then verifies every table before writing the version', function () {
    [$runner, $migrator, $versions] = migrationRunner100();
    $migrator->existing = ['activity' => true, 'jobs' => true];

    $result = $runner->runForSite(1);

    expect($migrator->timeline)->toBe([
        'create:activity',
        'create:jobs',
        'exists:activity',
        'exists:jobs',
        'put:corex_product_foundation_schema_version=3',
    ])->and($result->outcome)->toBe(SiteMigrationOutcome::Migrated)
        ->and($result->componentsMigrated)->toBe(['product-foundation'])
        ->and($result->tablesCreated)->toBe(['activity', 'jobs'])
        ->and($versions->puts)->toBe([['corex_product_foundation_schema_version', '3']]);
});

it('leaves the version unwritten after partial creation and retries the component', function () {
    [$runner, $migrator, $versions] = migrationRunner100();
    $migrator->existing = ['activity' => true];

    $first = $runner->runForSite(1);
    $migrator->existing['jobs'] = true;
    $second = $runner->runForSite(1);

    expect($first->outcome)->toBe(SiteMigrationOutcome::Failed)
        ->and($first->error)->toContain('jobs')
        ->and($second->outcome)->toBe(SiteMigrationOutcome::Migrated)
        ->and($migrator->created)->toBe(['activity', 'jobs', 'activity', 'jobs'])
        ->and($versions->puts)->toBe([['corex_product_foundation_schema_version', '3']]);
});

it('continues after a throwing site and returns that failure', function () {
    [$runner, $migrator, , $scope] = migrationRunner100([1, 2, 3]);
    $migrator->existing = ['activity' => true, 'jobs' => true];
    $scope->throwingSites[2] = true;

    $result = $runner->runForSites([1, 2, 3]);

    expect($result)->toBeInstanceOf(NetworkMigrationResult::class)
        ->and($result->results)->toHaveCount(3)
        ->and($result->failed())->toBe(1)
        ->and($result->failures()[0]->siteId)->toBe(2)
        ->and($scope->siteIds)->toBe([1, 2, 3]);
});

it('advances network offsets and completes only on a short batch', function () {
    [$runner, $migrator] = migrationRunner100([1, 2, 3]);
    $migrator->existing = ['activity' => true, 'jobs' => true];

    $first = $runner->runForNetwork(2, 0);
    $second = $runner->runForNetwork(2, $first->nextOffset);

    expect($first->nextOffset)->toBe(2)
        ->and($first->complete)->toBeFalse()
        ->and($second->nextOffset)->toBe(3)
        ->and($second->complete)->toBeTrue();
});

it('passes requested site ids to the site scope in exact order', function () {
    [$runner, $migrator, , $scope] = migrationRunner100([8, 4, 9]);
    $migrator->existing = ['activity' => true, 'jobs' => true];

    $runner->runForSites([8, 4, 9]);

    expect($scope->siteIds)->toBe([8, 4, 9]);
});

it('dispatches component failures but leaves successful migration events to callers', function () {
    [$runner, $migrator, $versions, , $listeners] = migrationRunner100();
    $events = [];
    $listeners->listen(SiteMigrated::class, function (SiteMigrated $event) use (&$events): void {
        $events[] = 'migrated:' . $event->siteId;
    });
    $listeners->listen(SiteMigrationFailed::class, function (SiteMigrationFailed $event) use (&$events): void {
        $events[] = 'failed:' . $event->componentId;
    });

    $versions->versions['corex_product_foundation_schema_version'] = '3';
    $runner->runForSite(1);
    unset($versions->versions['corex_product_foundation_schema_version']);
    $migrator->existing = ['activity' => true];
    $runner->runForSite(1);
    $migrator->existing['jobs'] = true;
    $runner->runForSite(1);

    expect($events)->toBe(['failed:product-foundation']);
});
