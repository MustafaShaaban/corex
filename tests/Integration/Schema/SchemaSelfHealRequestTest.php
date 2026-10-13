<?php

/**
 * Integration test: what the schema self-heal asks of the database on an admin request and on a
 * cron run (DECISIONS #360).
 *
 * The self-heal took its lock before it knew whether there was anything to do. A site whose
 * schema was current wrote the lock and deleted it on every admin page, every `admin-ajax.php`
 * call (an open admin screen makes one on a timer) and every cron run, and read each component's
 * version in a statement of its own. What a request's boot asks can only be heard from a request
 * of its own, so each request here is a separate PHP process that loads WordPress and reports.
 *
 * The assertions name the statements they are about. A count of everything a boot writes would
 * also count whatever else an install does while it loads.
 *
 * @package Corex\Tests\Integration\Schema
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Database\Schema\SchemaComponent;
use Corex\Database\Schema\SchemaRegistry;

const SELF_HEAL_LOCK = 'corex_schema_migrating';

const OPTIONS_WRITE = '/^\s*(INSERT|UPDATE|DELETE|REPLACE)\b/i';

const TABLE_STATEMENT = '/^\s*(DESCRIBE|CREATE\s+TABLE|ALTER\s+TABLE)\b/i';

/**
 * Load WordPress in a process of its own, as an admin request or as a cron run, and report what
 * its boot asked of the database and whether it announced a migration.
 *
 * `$landing` is another request's migration finishing while this one is between its look at the
 * versions and its look at the lock: the versions given are written straight to the database, as
 * a process that is not this one would leave them, at the moment this request reads the lock.
 *
 * @param 'admin'|'cron'        $kind
 * @param array<string, string> $landing Version by option name.
 *
 * @return array{statements: list<string>, migrated: int, landed: bool, is_admin: bool, doing_cron: bool, dbdelta_loaded: bool}
 */
function selfHealReportOfOneRequest(string $kind, array $landing = []): array
{
    $config = ['kind' => $kind, 'landing' => $landing, 'wp_load' => ABSPATH . 'wp-load.php'];
    $script = tempnam(sys_get_temp_dir(), 'corex-self-heal-') . '.php';
    file_put_contents($script, '<?php $config = ' . var_export($config, true) . ';' . <<<'PHP'

        $report = ['statements' => [], 'migrated' => 0, 'landed' => false];

        // WordPress adopts hooks that were set before it loaded, which is the only way to hear the
        // queries of the boot itself.
        $GLOBALS['wp_filter']['query'][10]['corex_self_heal_probe'] = [
            'function' => static function ($sql) use (&$report) {
                $report['statements'][] = (string) $sql;

                return $sql;
            },
            'accepted_args' => 1,
        ];
        $GLOBALS['wp_filter']['corex_site_migrated'][10]['corex_self_heal_probe'] = [
            'function' => static function () use (&$report) {
                $report['migrated']++;
            },
            'accepted_args' => 0,
        ];

        if ($config['landing'] !== []) {
            $GLOBALS['wp_filter']['pre_transient_corex_schema_migrating'][10]['corex_self_heal_probe'] = [
                'function' => static function ($pre) use (&$report, $config) {
                    global $wpdb;

                    foreach ($config['landing'] as $optionName => $version) {
                        $wpdb->query($wpdb->prepare(
                            "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')
                             ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)",
                            $optionName,
                            $version,
                        ));
                    }

                    $report['landed'] = true;

                    return $pre;
                },
                'accepted_args' => 1,
            ];
        }

        define($config['kind'] === 'cron' ? 'DOING_CRON' : 'WP_ADMIN', true);

        require $config['wp_load'];

        echo 'COREX_REQUEST_JSON:' . wp_json_encode($report + [
            'is_admin' => is_admin(),
            'doing_cron' => wp_doing_cron(),
            'dbdelta_loaded' => function_exists('dbDelta'),
        ]);
        PHP);

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
 * @param list<string> $statements
 * @param list<string> $names
 *
 * @return list<string> The statements that name one of the names.
 */
function statementsNaming(array $statements, array $names): array
{
    return array_values(array_filter(
        $statements,
        static fn (string $sql): bool => array_filter($names, static fn (string $name): bool => str_contains($sql, $name)) !== [],
    ));
}

/**
 * @param list<string> $statements
 *
 * @return list<string>
 */
function statementsMatching(array $statements, string $pattern): array
{
    return array_values(array_filter($statements, static fn (string $sql): bool => preg_match($pattern, $sql) === 1));
}

/** @return list<string> */
function schemaVersionOptionNames(): array
{
    $registry = Boot::app()->container()->make(SchemaRegistry::class);

    return array_map(static fn (SchemaComponent $component): string => $component->optionName, $registry->all());
}

/** What the database holds for an option, read past whatever this process remembers of it. */
function storedOptionValue(string $optionName): ?string
{
    global $wpdb;

    return $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $optionName));
}

/**
 * Run the test on a site whose first registered component has no version recorded, which is a
 * site that is behind, and leave the record as it was found.
 *
 * The row is removed and put back in the database and not through `delete_option()`: this
 * process's own memory of the option is then never wrong for the tests that run after this one.
 * The component's tables stay. Bringing it up to date runs `dbDelta()` over tables that are
 * already as declared, which is what the first admin page after an update does on every site.
 *
 * @param Closure(SchemaComponent): void $test
 */
function onASiteThatIsBehind(Closure $test): void
{
    global $wpdb;

    // This install may itself be behind, and the test is about one component being behind.
    selfHealReportOfOneRequest('admin');

    $component = Boot::app()->container()->make(SchemaRegistry::class)->all()[0];
    $recorded = storedOptionValue($component->optionName);
    $wpdb->delete($wpdb->options, ['option_name' => $component->optionName]);

    try {
        $test($component);
    } finally {
        $wpdb->delete($wpdb->options, ['option_name' => $component->optionName]);

        if ($recorded !== null) {
            $wpdb->insert($wpdb->options, [
                'option_name' => $component->optionName,
                'option_value' => $recorded,
                'autoload' => 'off',
            ]);
        }
    }
}

it('takes no lock and reads every version at once on a site that is current', function (string $kind) {
    // The first request is where a site that is behind is brought up to date, and this install
    // may be one. The second is the one every later admin page and cron run is.
    selfHealReportOfOneRequest($kind);
    $request = selfHealReportOfOneRequest($kind);

    $versionOptions = schemaVersionOptionNames();
    $aboutSchema = statementsNaming($request['statements'], [SELF_HEAL_LOCK, ...$versionOptions]);

    expect($versionOptions)->not->toBeEmpty()
        ->and($kind === 'cron' ? $request['doing_cron'] : $request['is_admin'])->toBeTrue()
        ->and(statementsMatching($aboutSchema, OPTIONS_WRITE))->toBe([])
        ->and(statementsNaming($request['statements'], [SELF_HEAL_LOCK]))->toBe([])
        // One read at most: none on a site whose object cache outlives the request.
        ->and(count(statementsNaming($request['statements'], $versionOptions)))->toBeLessThanOrEqual(1)
        ->and($request['migrated'])->toBe(0)
        ->and($request['dbdelta_loaded'])->toBeFalse();
})->with([
    'an admin request' => 'admin',
    'a cron run' => 'cron',
]);

it('brings a site that is behind up to date on its first admin request, under the lock, and announces it once', function () {
    onASiteThatIsBehind(function (SchemaComponent $component): void {
        $first = selfHealReportOfOneRequest('admin');
        $lockStatements = statementsMatching(
            statementsNaming($first['statements'], ['_transient_' . SELF_HEAL_LOCK]),
            OPTIONS_WRITE,
        );

        expect($first['migrated'])->toBe(1)
            ->and(storedOptionValue($component->optionName))->toBe($component->version)
            ->and(statementsMatching($lockStatements, '/^\s*INSERT\b/i'))->not->toBeEmpty()
            ->and(statementsMatching($lockStatements, '/^\s*DELETE\b/i'))->not->toBeEmpty()
            ->and(storedOptionValue('_transient_' . SELF_HEAL_LOCK))->toBeNull();

        $second = selfHealReportOfOneRequest('admin');

        expect($second['migrated'])->toBe(0)
            ->and(statementsNaming($second['statements'], [SELF_HEAL_LOCK]))->toBe([])
            ->and(statementsMatching($second['statements'], TABLE_STATEMENT))->toBe([]);
    });
});

it('does not migrate again when another request finished the migration between its look at the versions and its lock', function () {
    onASiteThatIsBehind(function (SchemaComponent $component): void {
        $request = selfHealReportOfOneRequest('admin', landing: [$component->optionName => $component->version]);

        expect($request['landed'])->toBeTrue()
            ->and($request['migrated'])->toBe(0)
            ->and(statementsMatching($request['statements'], TABLE_STATEMENT))->toBe([])
            ->and($request['dbdelta_loaded'])->toBeFalse()
            ->and(storedOptionValue('_transient_' . SELF_HEAL_LOCK))->toBeNull();
    });
});
