<?php

/**
 * An add-on that owns a table declares it, and creates nothing on a request.
 *
 * Newsletter, Bookings and Careers each called `Migrator::create()` on `init`. That is `dbDelta()`
 * on every front-end, REST and admin request of a site that has one of them, and the whole of
 * `wp-admin/includes/` loaded with it. A declared table is created by the framework's migration
 * runner instead: from an admin page or cron, from `wp corex migrate`, or when a network gets a
 * new site, and only when the version stored for the site differs (DECISIONS #299).
 *
 * @package Corex\Tests\Unit\Addons
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Blocks\BlockMap;
use Corex\Blocks\DynamicBlockRegistrar;
use Corex\Bookings\BookingsServiceProvider;
use Corex\Careers\CareersServiceProvider;
use Corex\Container\Container;
use Corex\Database\Schema\ManagedTable;
use Corex\Database\Schema\ManagedTables;
use Corex\Database\Schema\Migrator;
use Corex\Database\Schema\SchemaRegistry;
use Corex\Database\Schema\Table;
use Corex\Email\Template\TemplateRegistry;
use Corex\Foundation\ServiceProvider;
use Corex\Newsletter\NewsletterServiceProvider;

/**
 * Stands in for the migrator the providers used to ask the container for, and keeps what it was
 * told to create. The real one cannot be used here: it loads a WordPress file.
 */
final class RecordingAddonMigrator
{
    /** @var list<string> */
    public array $created = [];

    public function create(Table $table): void
    {
        $this->created[] = $table->name;
    }
}

/**
 * @param class-string<ServiceProvider> $providerClass
 *
 * @return array{0: ServiceProvider, 1: SchemaRegistry, 2: RecordingAddonMigrator, 3: Container}
 */
function addonProviderWithItsSchemaSeams(string $providerClass): array
{
    $registry = new SchemaRegistry();
    $migrator = new RecordingAddonMigrator();
    $container = new Container();
    $container->instance(SchemaRegistry::class, $registry);
    $container->instance(Migrator::class, $migrator);
    // Careers registers its block on the same hook. Neither class can run without WordPress, and
    // neither is what this test is about.
    $container->instance(BlockMap::class, new class {
        /** @return list<array<string, mixed>> */
        public function discover(string $blocksDir): array
        {
            return [];
        }
    });
    $container->instance(DynamicBlockRegistrar::class, new stdClass());

    $container->instance(ManagedTables::class, new ManagedTables());
    $container->instance(TemplateRegistry::class, new TemplateRegistry());

    return [new $providerClass($container), $registry, $migrator, $container];
}

dataset('add-ons that own a table', [
    'newsletter' => [NewsletterServiceProvider::class, 'newsletter', 'corex_newsletter_schema_version', 'subscribers'],
    'bookings' => [BookingsServiceProvider::class, 'bookings', 'corex_bookings_schema_version', 'call_requests'],
    'careers' => [CareersServiceProvider::class, 'careers', 'corex_careers_schema_version', 'applications'],
]);

it('declares its table to the migration runner when it boots', function (
    string $providerClass,
    string $componentId,
    string $optionName,
    string $tableName,
) {
    [$provider, $registry] = addonProviderWithItsSchemaSeams($providerClass);

    $provider->boot();

    $component = $registry->get($componentId);

    // An empty version is what a site with nothing stored reads back, so a component with one
    // would look current everywhere and its table would never be created.
    expect($component)->not->toBeNull()
        ->and($component->optionName)->toBe($optionName)
        ->and($component->version)->not->toBe('')
        ->and($registry->tableNames())->toBe([$tableName]);
})->with('add-ons that own a table');

dataset('what each add-on registers on init', [
    'newsletter' => [NewsletterServiceProvider::class, ['subscribers'], ['newsletter-confirm', 'newsletter-notify']],
    'bookings' => [BookingsServiceProvider::class, [], ['call-request-leader', 'call-request-confirm']],
    'careers' => [CareersServiceProvider::class, ['applications'], ['careers-new-application', 'careers-application-received']],
]);

it('creates no table on init, and registers there what it always did', function (
    string $providerClass,
    array $managedTables,
    array $emailTemplates,
) {
    Functions\when('__')->returnArg();
    Functions\when('register_post_type')->justReturn(new stdClass());
    Functions\when('register_taxonomy')->justReturn(new stdClass());

    [$provider, , $migrator, $container] = addonProviderWithItsSchemaSeams($providerClass);

    $provider->install();

    $managed = array_map(
        static fn (ManagedTable $table): string => $table->name,
        $container->make(ManagedTables::class)->all(),
    );

    expect($migrator->created)->toBe([])
        ->and($managed)->toBe($managedTables)
        ->and($container->make(TemplateRegistry::class)->names())->toBe($emailTemplates);
})->with('what each add-on registers on init');
