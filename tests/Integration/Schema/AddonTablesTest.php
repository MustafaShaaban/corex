<?php

/**
 * Integration test: an add-on's table is created when the site needs it, and a request creates
 * nothing (DECISIONS #299).
 *
 * Newsletter, Bookings and Careers each ran `dbDelta()` on `init`. Every request of a site with
 * one of them loaded `wp-admin/includes/upgrade.php`, and the rest of the admin's files with it,
 * and asked the database to describe a table that was already there. Whether that still happens
 * can only be seen from a request of its own: this process has run other tests, and some of them
 * create tables. So the requests here are separate PHP processes that load WordPress and report.
 *
 * @package Corex\Tests\Integration\Schema
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Database\Schema\Migrator;
use Corex\Database\Schema\SchemaComponent;
use Corex\Database\Schema\SchemaMigrator;
use Corex\Database\Schema\SchemaRegistry;
use Corex\Database\Schema\SchemaVersionStore;
use Corex\Database\Schema\SiteMigrationOutcome;
use Corex\Database\Schema\SiteMigrationRunner;
use Corex\Events\EventDispatcher;
use Corex\Multisite\NetworkContext;
use Corex\Multisite\SiteScope;

const ADDON_SCHEMA_COMPONENTS = ['newsletter', 'bookings', 'careers'];

const SCHEMA_STATEMENT = '/^\s*(DESCRIBE|CREATE\s+TABLE|ALTER\s+TABLE)\b/i';

/**
 * Load WordPress in a process of its own, as a front-end request or as an admin one, and report
 * what it did about schema.
 *
 * @return array{is_admin: bool, components: list<string>, dbdelta_loaded: bool, schema_statements: list<string>}
 */
function schemaReportOfOneRequest(bool $admin): array
{
    $script = tempnam(sys_get_temp_dir(), 'corex-request-') . '.php';
    file_put_contents($script, sprintf(
        <<<'PHP'
        <?php
        // WordPress adopts hooks that were set before it loaded, which is the only way to hear the
        // queries of the boot itself.
        $statements = [];
        $GLOBALS['wp_filter']['query'][10]['corex_schema_probe'] = [
            'function' => static function ($sql) use (&$statements) {
                $statements[] = (string) $sql;

                return $sql;
            },
            'accepted_args' => 1,
        ];

        if (%s) {
            define('WP_ADMIN', true);
        }

        require %s;

        $registry = \Corex\Boot::app()->container()->make(\Corex\Database\Schema\SchemaRegistry::class);

        echo 'COREX_REQUEST_JSON:' . wp_json_encode([
            'is_admin' => is_admin(),
            'components' => array_map(static fn ($component) => $component->id, $registry->all()),
            'dbdelta_loaded' => function_exists('dbDelta'),
            'schema_statements' => array_values(array_filter(
                $statements,
                static fn (string $sql): bool => preg_match(%s, $sql) === 1,
            )),
        ]);
        PHP,
        $admin ? 'true' : 'false',
        var_export(ABSPATH . 'wp-load.php', true),
        var_export(SCHEMA_STATEMENT, true),
    ));

    try {
        $process = proc_open(
            [PHP_BINARY, $script],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
    } finally {
        unlink($script);
    }

    expect($exitCode)->toBe(0, "The request did not finish:\n" . $stdout . $stderr);
    expect(preg_match('/COREX_REQUEST_JSON:(\{.+\})/', $stdout, $matches))->toBe(1, $stdout . $stderr);

    return json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
}

/**
 * @return list<SchemaComponent>
 */
function addonSchemaComponents(): array
{
    $registry = Boot::app()->container()->make(SchemaRegistry::class);

    return array_values(array_filter(array_map($registry->get(...), ADDON_SCHEMA_COMPONENTS)));
}

it('creates nothing on a front-end request, and does not load the file that would', function () {
    $request = schemaReportOfOneRequest(admin: false);

    expect($request['is_admin'])->toBeFalse()
        ->and($request['components'])->toContain(...ADDON_SCHEMA_COMPONENTS)
        ->and($request['dbdelta_loaded'])->toBeFalse()
        ->and($request['schema_statements'])->toBe([]);
});

it('creates nothing on an admin request of a site that is current', function () {
    // The first admin request is where a site that is behind is brought up to date, and this
    // install may be one. The second is the one every later admin page is.
    schemaReportOfOneRequest(admin: true);
    $request = schemaReportOfOneRequest(admin: true);

    expect($request['is_admin'])->toBeTrue()
        ->and($request['components'])->toContain(...ADDON_SCHEMA_COMPONENTS)
        ->and($request['dbdelta_loaded'])->toBeFalse()
        ->and($request['schema_statements'])->toBe([]);
});

it('gives a site that has none of them each add-on table, records the version, and then leaves it alone', function () {
    global $wpdb;

    $container = Boot::app()->container();
    $migrator = $container->make(Migrator::class);
    $components = addonSchemaComponents();
    $tableNames = ['subscribers', 'call_requests', 'applications'];

    // Only the add-ons' components, so a foundation that happens to be behind on this install is
    // not marked current by a test that created its tables somewhere else.
    $addons = new SchemaRegistry();
    array_map($addons->register(...), $components);
    $runner = new SiteMigrationRunner(
        $container->make(SchemaMigrator::class),
        $addons,
        $container->make(SchemaVersionStore::class),
        $container->make(SiteScope::class),
        $container->make(NetworkContext::class),
        $container->make(EventDispatcher::class),
    );

    // A site without the tables, made without dropping the ones this install has and whatever is
    // in them: the migrator reads the prefix each time it names a table, which is how a network's
    // sites are told apart, so under another prefix this is a site that has never had them.
    $prefix = $wpdb->prefix;
    $recorded = [];

    foreach ($components as $component) {
        $recorded[$component->optionName] = get_option($component->optionName, null);
    }

    $wpdb->prefix = $prefix . 'cxfresh_';

    try {
        array_map(delete_option(...), array_keys($recorded));

        expect(array_map($migrator->exists(...), $tableNames))->each->toBeFalse();

        $first = $runner->runForSite(get_current_blog_id());

        expect($first->outcome)->toBe(SiteMigrationOutcome::Migrated)
            ->and($first->componentsMigrated)->toEqualCanonicalizing(ADDON_SCHEMA_COMPONENTS)
            ->and(array_map($migrator->exists(...), $tableNames))->each->toBeTrue();

        foreach ($components as $component) {
            expect(get_option($component->optionName))->toBe($component->version);
        }

        $statements = [];
        $listen = static function (string $sql) use (&$statements): string {
            if (preg_match(SCHEMA_STATEMENT, $sql) === 1) {
                $statements[] = $sql;
            }

            return $sql;
        };
        add_filter('query', $listen);

        try {
            $second = $runner->runForSite(get_current_blog_id());
        } finally {
            remove_filter('query', $listen);
        }

        expect($second->outcome)->toBe(SiteMigrationOutcome::AlreadyCurrent)
            ->and($statements)->toBe([]);
    } finally {
        array_map($migrator->drop(...), $tableNames);
        $wpdb->prefix = $prefix;

        foreach ($recorded as $optionName => $version) {
            $version === null ? delete_option($optionName) : update_option($optionName, $version, false);
        }
    }
});
