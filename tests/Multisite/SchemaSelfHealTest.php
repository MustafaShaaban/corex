<?php

/**
 * The schema self-heal on a network: each site is looked at, locked and brought up to date on
 * its own (DECISIONS #360).
 *
 * The versions and the lock are options of the site a request is for, so what one site's admin
 * request reads and writes is in that site's options table and in no other. Each request is a
 * WP-CLI process in the admin context, because what a boot asks of the database can only be
 * heard by a listener that was there before WordPress loaded.
 *
 * @package Corex\Tests\Multisite
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Database\Schema\SchemaComponent;
use Corex\Database\Schema\SchemaRegistry;

const NETWORK_SELF_HEAL_LOCK = 'corex_schema_migrating';

// Loaded by WP-CLI before WordPress, which is the only way to hear the queries of the boot.
$listener = <<<'PHP'
<?php
$GLOBALS['corex_ms_self_heal'] = ['statements' => [], 'migrated' => []];

WP_CLI::add_wp_hook('query', static function ($sql) {
    $GLOBALS['corex_ms_self_heal']['statements'][] = (string) $sql;

    return $sql;
});
WP_CLI::add_wp_hook('corex_site_migrated', static function ($siteId) {
    $GLOBALS['corex_ms_self_heal']['migrated'][] = (int) $siteId;
});
PHP;

$report = <<<'PHP'
$heard = $GLOBALS['corex_ms_self_heal'];
$about = static fn (string $sql): bool => str_contains($sql, 'corex_schema_migrating') || str_contains($sql, '_schema_version');

echo 'COREX_MS_JSON:' . wp_json_encode([
    'is_admin' => is_admin(),
    'site' => get_current_blog_id(),
    'migrated' => $heard['migrated'],
    'statements' => array_values(array_filter($heard['statements'], $about)),
]);
PHP;

/**
 * @param list<string> $statements
 *
 * @return list<string> The statements that name the text.
 */
function networkStatementsNaming(array $statements, string $text): array
{
    return array_values(array_filter($statements, static fn (string $sql): bool => str_contains($sql, $text)));
}

beforeEach(function () use ($listener, $report) {
    $this->listenerPath = tempnam(sys_get_temp_dir(), 'corex-ms-heal-') . '.php';
    file_put_contents($this->listenerPath, $listener);

    /** @return array{is_admin: bool, site: int, migrated: list<int>, statements: list<string>} */
    $this->adminRequestOf = fn (int $siteId): array => $this->wpCliJson($siteId, $report, [
        '--context=admin',
        '--require=' . str_replace('\\', '/', $this->listenerPath),
    ]);

    /** What the database holds for a site's option, read past this process's memory of it. */
    $this->storedOn = fn (int $siteId, string $optionName): ?string => $this->onSite(
        $siteId,
        static fn (): ?string => $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare(
            "SELECT option_value FROM {$GLOBALS['wpdb']->options} WHERE option_name = %s",
            $optionName,
        )),
    );
});

afterEach(function () {
    unlink($this->listenerPath);
});

it('takes no lock on the admin request of a site of the network that is current', function () {
    $siteId = $this->siteIdForPath('/site3/');
    $optionsTable = $this->onSite($siteId, static fn (): string => $GLOBALS['wpdb']->options);

    // The first request is where a site that is behind is brought up to date.
    ($this->adminRequestOf)($siteId);
    $request = ($this->adminRequestOf)($siteId);

    $versionReads = networkStatementsNaming($request['statements'], '_schema_version');

    expect($request['is_admin'])->toBeTrue()
        ->and($request['site'])->toBe($siteId)
        ->and(networkStatementsNaming($request['statements'], NETWORK_SELF_HEAL_LOCK))->toBe([])
        ->and($versionReads)->toHaveCount(1)
        ->and($versionReads[0])->toContain($optionsTable)
        ->and($request['migrated'])->toBe([]);
});

it('brings the one site that is behind up to date under that site\'s own lock, and leaves its neighbour alone', function () {
    global $wpdb;

    $siteId = $this->siteIdForPath('/site3/');
    $neighbourId = $this->siteIdForPath('/site2/');
    $optionsTable = $this->onSite($siteId, static fn (): string => $GLOBALS['wpdb']->options);
    /** @var SchemaComponent $component */
    $component = Boot::app()->container()->make(SchemaRegistry::class)->all()[0];

    ($this->adminRequestOf)($siteId);
    $recorded = ($this->storedOn)($siteId, $component->optionName);
    $neighbourRecorded = ($this->storedOn)($neighbourId, $component->optionName);

    // Removed in the database and not through delete_option(), so this process's memory of the
    // option is never wrong for the tests after this one. The component's tables stay.
    $wpdb->delete($optionsTable, ['option_name' => $component->optionName]);

    try {
        $first = ($this->adminRequestOf)($siteId);
        $lockStatements = networkStatementsNaming($first['statements'], '_transient_' . NETWORK_SELF_HEAL_LOCK);

        expect($first['migrated'])->toBe([$siteId])
            ->and(($this->storedOn)($siteId, $component->optionName))->toBe($component->version)
            ->and($lockStatements)->not->toBeEmpty()
            ->and($lockStatements)->each->toContain($optionsTable)
            ->and(($this->storedOn)($siteId, '_transient_' . NETWORK_SELF_HEAL_LOCK))->toBeNull()
            ->and(($this->storedOn)($neighbourId, $component->optionName))->toBe($neighbourRecorded);

        $second = ($this->adminRequestOf)($siteId);

        expect($second['migrated'])->toBe([])
            ->and(networkStatementsNaming($second['statements'], NETWORK_SELF_HEAL_LOCK))->toBe([]);
    } finally {
        $wpdb->delete($optionsTable, ['option_name' => $component->optionName]);

        if ($recorded !== null) {
            $wpdb->insert($optionsTable, [
                'option_name' => $component->optionName,
                'option_value' => $recorded,
                'autoload' => 'off',
            ]);
        }
    }
});
