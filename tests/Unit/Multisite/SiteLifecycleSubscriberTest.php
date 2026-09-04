<?php

/**
 * @package Corex\Tests\Unit\Multisite
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Database\Schema\SchemaComponent;
use Corex\Database\Schema\SchemaMigrator;
use Corex\Database\Schema\SchemaRegistry;
use Corex\Database\Schema\SchemaVersionStore;
use Corex\Database\Schema\SiteMigrationRunner;
use Corex\Database\Schema\Table;
use Corex\Events\EventDispatcher;
use Corex\Events\ListenerProvider;
use Corex\Multisite\Events\SiteCreated;
use Corex\Multisite\Events\SiteDeleted;
use Corex\Multisite\Events\SiteDeleting;
use Corex\Multisite\Events\SiteMigrated;
use Corex\Multisite\Events\SiteSchemaInstalled;
use Corex\Multisite\NetworkContext;
use Corex\Multisite\SiteLifecycleSubscriber;
use Corex\Multisite\SiteScope;
use Corex\Multisite\SiteScoped;
use Corex\Support\BootLogger;

if (! class_exists('WP_Site')) {
    final class WP_Site
    {
        public int $blog_id;
        public int $site_id;
        public string $domain;
        public string $path;

        /** @param array{blog_id: int, site_id: int, domain: string, path: string} $properties */
        public function __construct(array $properties)
        {
            $this->blog_id = $properties['blog_id'];
            $this->site_id = $properties['site_id'];
            $this->domain = $properties['domain'];
            $this->path = $properties['path'];
        }
    }
}

final class LifecycleMigrator100 implements SchemaMigrator
{
    public function fullName(string $name): string
    {
        return 'wp_12_corex_' . $name;
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
}

final class LifecycleVersionStore100 implements SchemaVersionStore
{
    /** @var array<string, string> */
    private array $versions = [];

    public function get(string $optionName): string
    {
        return $this->versions[$optionName] ?? '';
    }

    public function put(string $optionName, string $version): void
    {
        $this->versions[$optionName] = $version;
    }
}

final class LifecycleScope100 implements SiteScope
{
    /** @var list<string> */
    public array $sequence;

    /** @param list<string> $sequence */
    public function __construct(array &$sequence)
    {
        $this->sequence =& $sequence;
    }

    public function currentSiteId(): int
    {
        return 1;
    }

    public function register(SiteScoped $service): void
    {
    }

    public function run(int $siteId, Closure $callback): mixed
    {
        $this->sequence[] = 'run:' . $siteId;

        return $callback();
    }
}

final class LifecycleNetwork100 implements NetworkContext
{
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
        return 2;
    }

    public function siteIds(int $limit = 0, int $offset = 0): array
    {
        return [1, 12];
    }

    public function exists(int $siteId): bool
    {
        return in_array($siteId, [1, 12], true);
    }
}

/** @return array{0: SiteLifecycleSubscriber, 1: SchemaRegistry, 2: ListenerProvider, 3: LifecycleScope100} */
function lifecycleSubscriber100(): array
{
    $sequence = [];
    $migrator = new LifecycleMigrator100();
    $schemas = new SchemaRegistry();
    $schemas->register(new SchemaComponent(
        'product-foundation',
        '3',
        [new Table('activity'), new Table('jobs')],
        'corex_product_foundation_schema_version',
    ));
    $listeners = new ListenerProvider();
    $events = new EventDispatcher($listeners, new BootLogger(false));
    $scope = new LifecycleScope100($sequence);
    $runner = new SiteMigrationRunner(
        $migrator,
        $schemas,
        new LifecycleVersionStore100(),
        $scope,
        new LifecycleNetwork100(),
        $events,
    );

    Functions\when('Corex\Database\Schema\is_file')->justReturn(true);

    return [new SiteLifecycleSubscriber($runner, $migrator, $schemas, $events), $schemas, $listeners, $scope];
}

function lifecycleWpSite100(): WP_Site
{
    return new WP_Site([
        'blog_id' => 12,
        'site_id' => 4,
        'domain' => 'example.test',
        'path' => '/team/',
    ]);
}

it('declares the repository hook seam with the required priorities and arguments', function () {
    [$subscriber] = lifecycleSubscriber100();

    expect($subscriber->hooks())->toBe([
        'wp_initialize_site' => ['onSiteInitialized', 20, 2],
        'wp_delete_site' => ['onSiteDeleted', 10, 1],
        'wpmu_drop_tables' => ['filterDropTables', 10, 2],
    ]);
});

it('appends every registered full table name without losing or duplicating input', function () {
    [$subscriber, , $listeners] = lifecycleSubscriber100();
    $events = [];
    $listeners->listen(SiteDeleting::class, function (SiteDeleting $event) use (&$events): void {
        $events[] = [$event->siteId, $event->dropTables];
    });

    $tables = $subscriber->filterDropTables(['wp_posts', 'wp_12_corex_activity'], 12);

    expect($tables)->toBe(['wp_posts', 'wp_12_corex_activity', 'wp_12_corex_jobs'])
        ->and($events)->toBe([[12, true]]);
});

it('dispatches creation then runs migration then dispatches schema installation', function () {
    [$subscriber, , $listeners, $scope] = lifecycleSubscriber100();
    Functions\when('get_site_url')->justReturn('https://example.test/team/');
    Functions\when('is_main_site')->justReturn(false);
    Functions\when('do_action')->justReturn(null);
    $listeners->listen(SiteCreated::class, function (SiteCreated $event) use ($scope): void {
        $scope->sequence[] = 'created:' . $event->siteId;
    });
    $listeners->listen(SiteMigrated::class, function (SiteMigrated $event) use ($scope): void {
        $scope->sequence[] = 'migrated:' . $event->siteId;
    });
    $listeners->listen(SiteSchemaInstalled::class, function (SiteSchemaInstalled $event) use ($scope): void {
        $scope->sequence[] = 'installed:' . $event->siteId;
    });

    $subscriber->onSiteInitialized(lifecycleWpSite100(), []);

    expect($scope->sequence)->toBe(['created:12', 'run:12', 'installed:12']);
});

it('dispatches site deleted with the scalar blog id', function () {
    [$subscriber, , $listeners] = lifecycleSubscriber100();
    Functions\when('do_action')->justReturn(null);
    $deleted = [];
    $listeners->listen(SiteDeleted::class, function (SiteDeleted $event) use (&$deleted): void {
        $deleted[] = $event->siteId;
    });

    $subscriber->onSiteDeleted(lifecycleWpSite100());

    expect($deleted)->toBe([12]);
});

it('mirrors created and deleted events without calling a new site migrated', function () {
    [$subscriber] = lifecycleSubscriber100();
    Functions\when('get_site_url')->justReturn('https://example.test/team/');
    Functions\when('is_main_site')->justReturn(false);
    $actions = [];
    Functions\when('do_action')->alias(function (string $hook, mixed ...$arguments) use (&$actions): void {
        $actions[] = [$hook, $arguments];
    });

    $site = lifecycleWpSite100();
    $subscriber->onSiteInitialized($site, []);
    $subscriber->onSiteDeleted($site);

    expect(array_column($actions, 0))->toBe([
        'corex_site_created',
        'corex_site_deleted',
    ])->and($actions[0][1])->toBe([12])
        ->and($actions[1][1])->toBe([12]);
});
