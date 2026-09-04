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

/** @return array{0: SchemaSelfHeal, 1: object, 2: ListenerProvider} */
function schemaSelfHeal100(bool $throws = false, bool $migrationPending = false): array
{
    $scope = new class($throws) implements SiteScope {
        /** @var list<int> */
        public array $siteIds = [];

        public function __construct(private readonly bool $throws)
        {
        }

        public function currentSiteId(): int
        {
            return 7;
        }

        public function register(SiteScoped $service): void
        {
        }

        public function run(int $siteId, Closure $callback): mixed
        {
            $this->siteIds[] = $siteId;

            if ($this->throws) {
                throw new RuntimeException('migration exploded');
            }

            return $callback();
        }
    };
    $schemas = new SchemaRegistry();

    if ($migrationPending) {
        $schemas->register(new SchemaComponent(
            'product-foundation',
            '3',
            [new Table('activity')],
            'corex_product_foundation_schema_version',
        ));
    }

    $listeners = new ListenerProvider();
    $events = new EventDispatcher($listeners, new BootLogger(false));
    $runner = new SiteMigrationRunner(
        new class implements SchemaMigrator {
            public function fullName(string $name): string
            {
                return $name;
            }

            public function create(Table $table): void
            {
            }

            public function drop(string $name): void
            {
            }

            public function exists(string $name): bool
            {
                return true;
            }
        },
        $schemas,
        new class implements SchemaVersionStore {
            public function get(string $optionName): string
            {
                return '';
            }

            public function put(string $optionName, string $version): void
            {
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
        $scope,
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
    [$heal, $scope] = schemaSelfHeal100();

    $heal->run();

    expect($scope->siteIds)->toBe([]);
});

it('runs under an admin request and releases the site lock', function () {
    Functions\expect('is_admin')->once()->andReturn(true);
    expectSchemaSelfHealLock100();
    [$heal, $scope] = schemaSelfHeal100();

    $heal->run();

    expect($scope->siteIds)->toBe([7]);
});

it('runs under cron and releases the site lock', function () {
    Functions\expect('is_admin')->once()->andReturn(false);
    Functions\expect('wp_doing_cron')->once()->andReturn(true);
    expectSchemaSelfHealLock100();
    [$heal, $scope] = schemaSelfHeal100();

    $heal->run();

    expect($scope->siteIds)->toBe([7]);
});

it('does not run under WP CLI', function () {
    Functions\expect('is_admin')->once()->andReturn(false);
    Functions\expect('wp_doing_cron')->once()->andReturn(false);
    Functions\expect('get_transient')->never();
    [$heal, $scope] = schemaSelfHeal100();

    $heal->run();

    expect($scope->siteIds)->toBe([]);
});

it('announces a successful self-heal as an existing-site migration', function () {
    Functions\expect('is_admin')->once()->andReturn(true);
    expectSchemaSelfHealLock100();
    $actions = [];
    Functions\when('do_action')->alias(function (string $hook, mixed ...$arguments) use (&$actions): void {
        $actions[] = [$hook, $arguments];
    });
    [$heal, , $listeners] = schemaSelfHeal100(migrationPending: true);
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
    [$heal, $scope] = schemaSelfHeal100();

    $heal->run();

    expect($scope->siteIds)->toBe([]);
});

it('releases the lock when the migration runner throws', function () {
    Functions\expect('is_admin')->once()->andReturn(true);
    expectSchemaSelfHealLock100();
    [$heal] = schemaSelfHeal100(throws: true);

    expect(fn () => $heal->run())->toThrow(RuntimeException::class, 'migration exploded');
});
