<?php

/**
 * @package Corex\Tests\Unit\Database
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Database\Schema\DataMigrationStep;
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
function schemaSelfHeal100(bool $throws = false, string $storedVersion = '', ?DataMigrationStep $step = null, ?BootLogger $logger = null): array
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
        $step === null ? [] : [$step],
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
            $logger ?? new BootLogger(false),
        ),
        $migrator,
        $listeners,
        $runner,
    ];
}

function expectSchemaSelfHealLock100(): void
{
    Functions\expect('get_transient')->once()->with('corex_schema_backoff')->andReturn(false);
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
    Functions\expect('get_transient')->once()->with('corex_schema_backoff')->andReturn(false);
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

/** A data step that fails every time it runs, and counts its runs. */
final class AlwaysFailingStep100 implements DataMigrationStep
{
    public int $runs = 0;

    public function id(): string
    {
        return 'lockout-notice-privacy';
    }

    public function run(): void
    {
        $this->runs++;

        throw new RuntimeException('2 lockout notices could not be rewritten (ids 4, 9).');
    }
}

/**
 * A data step that kept failing ran again, with `dbDelta`, on every admin page, every admin-ajax
 * call and every cron run, and nothing said so: nothing listens for `SiteMigrationFailed` and the
 * self-heal returned quietly. The failure is now logged, and the self-heal leaves that component
 * alone on that site for fifteen minutes. `wp corex migrate` is not held back (spec 124 slice 3).
 */
it('spec 124: logs a failing data step once and leaves it for the backoff, while an explicit run still tries it', function () {
    Functions\when('Corex\Support\error_log')->justReturn(true);
    Functions\when('is_admin')->justReturn(true);
    $transients = [];
    Functions\when('get_transient')->alias(static function (string $key) use (&$transients): mixed {
        return $transients[$key] ?? false;
    });
    Functions\when('set_transient')->alias(static function (string $key, mixed $value, int $ttl) use (&$transients): bool {
        $transients[$key] = $value;

        return $ttl > 0;
    });
    Functions\when('delete_transient')->alias(static function (string $key) use (&$transients): bool {
        unset($transients[$key]);

        return true;
    });
    $step = new AlwaysFailingStep100();
    $logger = new BootLogger(false);
    [$heal, , , $runner] = schemaSelfHeal100(step: $step, logger: $logger);

    $heal->run();
    $heal->run();
    $heal->run();
    $explicit = $runner->runForSite(7);

    expect($step->runs)->toBe(2)
        ->and($logger->messages())->toHaveCount(1)
        ->and($logger->messages()[0]['level'])->toBe('error')
        ->and($logger->messages()[0]['message'])->toContain('product-foundation')
        ->and($logger->messages()[0]['message'])->toContain('2 lockout notices could not be rewritten (ids 4, 9).')
        ->and($logger->messages()[0]['message'])->toContain('wp corex migrate')
        ->and(array_keys($transients['corex_schema_backoff']))->toBe(['product-foundation'])
        ->and($explicit->componentsFailed)->toBe(['product-foundation']);
});

it('spec 124: tries a backed-off component again once its backoff has passed', function () {
    Functions\when('Corex\Support\error_log')->justReturn(true);
    Functions\when('is_admin')->justReturn(true);
    $transients = ['corex_schema_backoff' => ['product-foundation' => time() - 1]];
    Functions\when('get_transient')->alias(static fn (string $key): mixed => $transients[$key] ?? false);
    Functions\when('set_transient')->justReturn(true);
    Functions\when('delete_transient')->justReturn(true);
    $step = new AlwaysFailingStep100();
    [$heal] = schemaSelfHeal100(step: $step);

    $heal->run();

    expect($step->runs)->toBe(1);
});
