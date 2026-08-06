<?php

/**
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Cli\Commands\MigrateCommand;
use Corex\Database\Schema\SchemaComponent;
use Corex\Database\Schema\SchemaMigrator;
use Corex\Database\Schema\SchemaRegistry;
use Corex\Database\Schema\SchemaVersionStore;
use Corex\Database\Schema\SiteMigrationRunner;
use Corex\Database\Schema\Table;
use Corex\Events\EventDispatcher;
use Corex\Events\ListenerProvider;
use Corex\Multisite\Events\SiteMigrated;
use Corex\Multisite\MultisiteContext;
use Corex\Multisite\NetworkContext;
use Corex\Multisite\SiteContext;
use Corex\Multisite\SiteScope;
use Corex\Multisite\SiteScoped;
use Corex\Support\BootLogger;

final class CliMigrationState100
{
    public int $siteId = 1;
}

final class CliSchemaMigrator100 implements SchemaMigrator
{
    /** @var array<int, true> */
    public array $failingSites = [];

    public function __construct(private readonly CliMigrationState100 $state)
    {
    }

    public function fullName(string $name): string
    {
        return sprintf('wp_%d_corex_%s', $this->state->siteId, $name);
    }

    public function create(Table $table): void
    {
    }

    public function drop(string $name): void
    {
    }

    public function exists(string $name): bool
    {
        return ! isset($this->failingSites[$this->state->siteId]);
    }
}

final class CliSchemaVersionStore100 implements SchemaVersionStore
{
    /** @var array<int, array<string, string>> */
    public array $versions = [];

    public function __construct(private readonly CliMigrationState100 $state)
    {
    }

    public function get(string $optionName): string
    {
        return $this->versions[$this->state->siteId][$optionName] ?? '';
    }

    public function put(string $optionName, string $version): void
    {
        $this->versions[$this->state->siteId][$optionName] = $version;
    }
}

final class CliSiteScope100 implements SiteScope
{
    /** @var list<int> */
    public array $siteIds = [];

    public function __construct(private readonly CliMigrationState100 $state)
    {
    }

    public function currentSiteId(): int
    {
        return $this->state->siteId;
    }

    public function register(SiteScoped $service): void
    {
    }

    public function run(int $siteId, Closure $callback): mixed
    {
        $previous = $this->state->siteId;
        $this->state->siteId = $siteId;
        $this->siteIds[] = $siteId;

        try {
            return $callback();
        } finally {
            $this->state->siteId = $previous;
        }
    }
}

final class CliNetworkContext100 implements NetworkContext
{
    /** @var list<array{0: int, 1: int}> */
    public array $requests = [];

    /** @param list<int> $ids */
    public function __construct(public readonly array $ids)
    {
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
        $this->requests[] = [$limit, $offset];

        return array_values(array_slice($this->ids, $offset, $limit > 0 ? $limit : null));
    }

    public function exists(int $siteId): bool
    {
        return in_array($siteId, $this->ids, true);
    }
}

final class CliSiteContext100 implements SiteContext
{
    public function __construct(private readonly int $siteId)
    {
    }

    public function id(): int
    {
        return $this->siteId;
    }

    public function networkId(): int
    {
        return 1;
    }

    public function isMainSite(): bool
    {
        return $this->siteId === 1;
    }

    public function url(): string
    {
        return 'https://example.test/';
    }
}

final class CliMultisiteContext100 implements MultisiteContext
{
    public function __construct(private readonly bool $enabled)
    {
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function subdomainInstall(): bool
    {
        return false;
    }

    public function isNetworkAdmin(): bool
    {
        return false;
    }

    public function isSwitched(): bool
    {
        return false;
    }
}

/**
 * @param list<int> $siteIds
 * @return array{0: MigrateCommand, 1: CliNetworkContext100, 2: CliSchemaVersionStore100, 3: CliSchemaMigrator100, 4: CliSiteScope100, 5: ListenerProvider}
 */
function migrateCommand100(array $siteIds = [1, 2, 3], int $currentSiteId = 1, bool $multisite = true): array
{
    $state = new CliMigrationState100();
    $state->siteId = $currentSiteId;
    $migrator = new CliSchemaMigrator100($state);
    $versions = new CliSchemaVersionStore100($state);
    $scope = new CliSiteScope100($state);
    $network = new CliNetworkContext100($siteIds);
    $schemas = new SchemaRegistry();
    $schemas->register(new SchemaComponent(
        'product-foundation',
        '3',
        [new Table('activity'), new Table('jobs')],
        'corex_product_foundation_schema_version',
    ));
    $listeners = new ListenerProvider();
    $events = new EventDispatcher($listeners, new BootLogger(false));
    $runner = new SiteMigrationRunner(
        $migrator,
        $schemas,
        $versions,
        $scope,
        $network,
        $events,
    );

    Functions\when('Corex\Database\Schema\is_file')->justReturn(true);
    Functions\when('do_action')->justReturn(null);

    return [
        new MigrateCommand(
            $runner,
            new CliSiteContext100($currentSiteId),
            new CliMultisiteContext100($multisite),
            $events,
        ),
        $network,
        $versions,
        $migrator,
        $scope,
        $listeners,
    ];
}

it('migrates only the current site by default and builds the documented row', function () {
    [$command, , , , $scope] = migrateCommand100(currentSiteId: 2);

    $result = $command->execute([]);

    expect($result->siteIds)->toBe([2])
        ->and($result->batchSize)->toBe(50)
        ->and($result->rows[0]['site'])->toBe('2')
        ->and($result->rows[0]['outcome'])->toBe('migrated')
        ->and($result->rows[0]['components'])->toBe('product-foundation')
        ->and($result->rows[0]['tables'])->toBe('activity, jobs')
        ->and($result->rows[0]['error'])->toBe('-')
        ->and($result->shouldExitNonZero())->toBeFalse()
        ->and($scope->siteIds)->toBe([2]);
});

it('sweeps a network using the requested batch size and every returned offset', function () {
    [$command, $network] = migrateCommand100();

    $result = $command->execute(['network' => '', 'batch' => '2']);

    expect($result->siteIds)->toBe([1, 2, 3])
        ->and($result->rows)->toHaveCount(3)
        ->and($result->batchSize)->toBe(2)
        ->and($network->requests)->toBe([[2, 0], [2, 2]])
        ->and($result->shouldExitNonZero())->toBeFalse();
});

it('dry-runs only pending network sites without performing schema writes', function () {
    [$command, , $versions, , $scope] = migrateCommand100();
    $versions->versions[1]['corex_product_foundation_schema_version'] = '3';

    $result = $command->execute(['network' => '', 'dry-run' => '', 'batch' => '7']);

    expect($result->siteIds)->toBe([2, 3])
        ->and(array_column($result->rows, 'outcome'))->toBe(['would-migrate', 'would-migrate'])
        ->and($result->batchSize)->toBe(7)
        ->and($result->dryRun)->toBeTrue()
        ->and($versions->versions)->toBe([1 => ['corex_product_foundation_schema_version' => '3']])
        ->and($scope->siteIds)->toBe([1, 2, 3]);
});

it('dry-runs only the current site and clamps a zero batch size to one', function () {
    [$command] = migrateCommand100(currentSiteId: 2);

    $result = $command->execute(['dry-run' => '', 'batch' => '0']);

    expect($result->siteIds)->toBe([2])
        ->and($result->batchSize)->toBe(1)
        ->and($result->rows[0]['outcome'])->toBe('would-migrate');
});

it('keeps every network row and chooses a non-zero exit when one site fails', function () {
    [$command, , , $migrator, , $listeners] = migrateCommand100();
    $migrator->failingSites[2] = true;
    $migrated = [];
    $listeners->listen(SiteMigrated::class, function (SiteMigrated $event) use (&$migrated): void {
        $migrated[] = $event->siteId;
    });
    $actions = [];
    Functions\when('do_action')->alias(function (string $hook, mixed ...$arguments) use (&$actions): void {
        $actions[] = [$hook, $arguments];
    });

    $result = $command->execute(['network' => '', 'batch' => '2']);

    expect($result->rows)->toHaveCount(3)
        ->and(array_column($result->rows, 'site'))->toBe(['1', '2', '3'])
        ->and(array_column($result->rows, 'outcome'))->toBe(['migrated', 'failed', 'migrated'])
        ->and($result->rows[1]['error'])->toContain('activity')
        ->and($result->failed)->toBe(1)
        ->and($result->shouldExitNonZero())->toBeTrue()
        ->and($migrated)->toBe([1, 3])
        ->and(array_column($actions, 0))->toBe(['corex_site_migrated', 'corex_site_migrated'])
        ->and($actions[0][1][0])->toBe(1)
        ->and($actions[1][1][0])->toBe(3);
});

it('refuses network mode on single-site before enumerating or migrating sites', function () {
    [$command, $network, , , $scope] = migrateCommand100(multisite: false);

    $result = $command->execute(['network' => '', 'batch' => '5']);

    expect($result->networkRefused)->toBeTrue()
        ->and($result->shouldExitNonZero())->toBeTrue()
        ->and($result->siteIds)->toBe([])
        ->and($network->requests)->toBe([])
        ->and($scope->siteIds)->toBe([]);
});
