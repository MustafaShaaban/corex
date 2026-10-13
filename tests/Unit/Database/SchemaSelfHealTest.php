<?php

/**
 * @package Corex\Tests\Unit\Database
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Database\Schema\SchemaComponent;
use Corex\Database\Schema\SchemaMigrator;
use Corex\Database\Schema\SchemaRegistry;
use Corex\Database\Schema\SchemaSelfHeal;
use Corex\Database\Schema\SchemaVersionStore;
use Corex\Database\Schema\SiteMigrationRunner;
use Corex\Database\Schema\Table;
use Corex\Events\EventDispatcher;
use Corex\Events\ListenerProvider;
use Corex\Multisite\Events\SiteMigrated;
use Corex\Multisite\NetworkContext;
use Corex\Multisite\SingleSiteMultisiteContext;
use Corex\Multisite\SiteContext;
use Corex\Multisite\SiteScope;
use Corex\Multisite\SiteScoped;
use Corex\Support\BootLogger;

/**
 * A self-heal over one registered component. Nothing is stored for it unless `$storedVersion`
 * says so, so the site is behind by default; `'3'` is the site that is current.
 *
 * @return array{0: SchemaSelfHeal, 1: object, 2: ListenerProvider} The second is the migrator, whose `$created` lists the tables a run made.
 */
function schemaSelfHeal100(bool $throws = false, string $storedVersion = ''): array
{
    $scope = new class implements SiteScope {
        public function currentSiteId(): int
        {
            return 7;
        }

        public function register(SiteScoped $service): void
        {
        }

        public function run(int $siteId, Closure $callback): mixed
        {
            return $callback();
        }
    };
    $schemas = new SchemaRegistry();
    $schemas->register(new SchemaComponent(
        'product-foundation',
        '3',
        [new Table('activity')],
        'corex_product_foundation_schema_version',
    ));
    $migrator = new class($throws) implements SchemaMigrator {
        /** @var list<string> */
        public array $created = [];

        public function __construct(private readonly bool $throws)
        {
        }

        public function fullName(string $name): string
        {
            return $name;
        }

        public function create(Table $table): void
        {
            if ($this->throws) {
                throw new RuntimeException('migration exploded');
            }

            $this->created[] = $table->name;
        }

        public function drop(string $name): void
        {
        }

        public function exists(string $name): bool
        {
            return true;
        }
    };

    $listeners = new ListenerProvider();
    $events = new EventDispatcher($listeners, new BootLogger(false));
    $runner = new SiteMigrationRunner(
        $migrator,
        $schemas,
        new class($storedVersion) implements SchemaVersionStore {
            public function __construct(private string $version)
            {
            }

            public function get(string $optionName): string
            {
                return $this->version;
            }

            public function getMany(array $optionNames): array
            {
                return array_fill_keys($optionNames, $this->version);
            }

            public function forget(array $optionNames): void
            {
            }

            public function put(string $optionName, string $version): void
            {
                $this->version = $version;
            }
        },
        $scope,
        new class implements NetworkContext {
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
                return 1;
            }

            public function siteIds(int $limit = 0, int $offset = 0): array
            {
                return [7];
            }

            public function exists(int $siteId): bool
            {
                return true;
            }
        },
        $events,
    );
    $site = new class implements SiteContext {
        public function id(): int
        {
            return 7;
        }

        public function networkId(): int
        {
            return 1;
        }

        public function isMainSite(): bool
        {
            return false;
        }

        public function url(): string
        {
            return 'https://example.test/site7/';
        }
    };

    Functions\when('Corex\Database\Schema\is_file')->justReturn(true);

    return [
        new SchemaSelfHeal(
            $runner,
            $site,
            new SingleSiteMultisiteContext(),
            $events,
        ),
        $migrator,
        $listeners,
    ];
}

function expectSchemaSelfHealLock100(): void
{
    Functions\expect('get_transient')->once()->with('corex_schema_migrating')->andReturn(false);
    Functions\expect('set_transient')->once()->with('corex_schema_migrating', 1, 60);
    Functions\expect('delete_transient')->once()->with('corex_schema_migrating');
}

it('returns before touching the lock on a front-end request', function () {
    Functions\expect('is_admin')->once()->andReturn(false);
    Functions\expect('wp_doing_cron')->once()->andReturn(false);
    Functions\expect('get_transient')->never();
    [$heal, $migrator] = schemaSelfHeal100();

    $heal->run();

    expect($migrator->created)->toBe([]);
});

it('takes no lock on an admin request of a site that is current', function () {
    Functions\expect('is_admin')->once()->andReturn(true);
    Functions\expect('get_transient')->never();
    Functions\expect('set_transient')->never();
    Functions\expect('delete_transient')->never();
    [$heal, $migrator] = schemaSelfHeal100(storedVersion: '3');

    $heal->run();

    expect($migrator->created)->toBe([]);
});

it('migrates a site that is behind under an admin request and releases the site lock', function () {
    Functions\expect('is_admin')->once()->andReturn(true);
    expectSchemaSelfHealLock100();
    [$heal, $migrator] = schemaSelfHeal100();

    $heal->run();

    expect($migrator->created)->toBe(['activity']);
});

it('migrates a site that is behind under cron and releases the site lock', function () {
    Functions\expect('is_admin')->once()->andReturn(false);
    Functions\expect('wp_doing_cron')->once()->andReturn(true);
    expectSchemaSelfHealLock100();
    [$heal, $migrator] = schemaSelfHeal100();

    $heal->run();

    expect($migrator->created)->toBe(['activity']);
});

it('does not run under WP CLI', function () {
    Functions\expect('is_admin')->once()->andReturn(false);
    Functions\expect('wp_doing_cron')->once()->andReturn(false);
    Functions\expect('get_transient')->never();
    [$heal, $migrator] = schemaSelfHeal100();

    $heal->run();

    expect($migrator->created)->toBe([]);
});

it('announces a successful self-heal as an existing-site migration', function () {
    Functions\expect('is_admin')->once()->andReturn(true);
    expectSchemaSelfHealLock100();
    $actions = [];
    Functions\when('do_action')->alias(function (string $hook, mixed ...$arguments) use (&$actions): void {
        $actions[] = [$hook, $arguments];
    });
    [$heal, , $listeners] = schemaSelfHeal100();
    $migrated = [];
    $listeners->listen(SiteMigrated::class, function (SiteMigrated $event) use (&$migrated): void {
        $migrated[] = $event->siteId;
    });

    $heal->run();

    expect($migrated)->toBe([7])
        ->and($actions[0][0])->toBe('corex_site_migrated')
        ->and($actions[0][1][0])->toBe(7)
        ->and($actions[0][1][1]->siteId)->toBe(7);
});

it('does not start a second migration while the site lock exists', function () {
    Functions\expect('is_admin')->once()->andReturn(true);
    Functions\expect('get_transient')->once()->with('corex_schema_migrating')->andReturn(1);
    Functions\expect('set_transient')->never();
    [$heal, $migrator] = schemaSelfHeal100();

    $heal->run();

    expect($migrator->created)->toBe([]);
});

it('releases the lock when the migration runner throws', function () {
    Functions\expect('is_admin')->once()->andReturn(true);
    expectSchemaSelfHealLock100();
    [$heal] = schemaSelfHeal100(throws: true);

    expect(fn () => $heal->run())->toThrow(RuntimeException::class, 'migration exploded');
});
