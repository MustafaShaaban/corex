<?php

/**
 * An add-on's table on a network: made for the site that has the add-on, when that site needs it,
 * and removed with the site (DECISIONS #299).
 *
 * Each step is a request of its own, because which add-ons a request has is decided as it boots.
 * The fixture network has none of the three add-ons that own a table, so the test makes a site,
 * gives it all three, and removes everything it made.
 *
 * @package Corex\Tests\Multisite
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Database\Schema\Migrator;

const ADDON_PLUGINS_WITH_A_TABLE = [
    'corex-newsletter/corex-newsletter.php',
    'corex-bookings/corex-bookings.php',
    'corex-careers/corex-careers.php',
];

const ADDON_TABLES = ['subscribers', 'call_requests', 'applications'];

$addonTables = <<<'PHP'
$container = \Corex\Boot::app()->container();
$registry = $container->make(\Corex\Database\Schema\SchemaRegistry::class);
$migrator = $container->make(\Corex\Database\Schema\Migrator::class);
$tables = [];
$recorded = [];

foreach (['newsletter', 'bookings', 'careers'] as $id) {
    $component = $registry->get($id);

    if ($component === null) {
        continue;
    }

    foreach ($component->tables as $table) {
        $tables[$migrator->fullName($table->name)] = $migrator->exists($table->name);
    }

    $recorded[$id] = get_option($component->optionName, '') === $component->version;
}

echo 'COREX_MS_JSON:' . wp_json_encode([
    'is_admin' => is_admin(),
    'tables' => $tables,
    'recorded' => $recorded,
    'dbdelta_loaded' => function_exists('dbDelta'),
]);
PHP;

// Run from the site that has the add-ons: a site made by that request gets their tables as
// WordPress initializes it, and loses them as WordPress deletes it.
$siteMadeAndDeleted = <<<'PHP'
// WordPress reads this unconditionally when it logs a new site, and a command line has none.
$_SERVER['REMOTE_ADDR'] ??= '127.0.0.1';

$migrator = \Corex\Boot::app()->container()->make(\Corex\Database\Schema\Migrator::class);
$network = get_network();
$siteId = wp_insert_site([
    'domain' => $network->domain,
    'path' => '/corex-addon-child-' . substr(wp_generate_uuid4(), 0, 8) . '/',
    'network_id' => (int) $network->id,
    'user_id' => 1,
    'title' => 'Corex add-on tables, a site made by a site that has them',
]);
$names = ['subscribers', 'call_requests', 'applications'];
$existing = static function () use ($siteId, $migrator, $names): array {
    switch_to_blog($siteId);

    try {
        return array_map($migrator->exists(...), $names);
    } finally {
        restore_current_blog();
    }
};

$made = $existing();
switch_to_blog($siteId);
// wp_delete_site() walks the uploads directory whether or not the site ever had one.
wp_mkdir_p(wp_upload_dir()['basedir']);
restore_current_blog();
wp_delete_site($siteId);

echo 'COREX_MS_JSON:' . wp_json_encode([
    'made' => $made,
    'left_after_delete' => $existing(),
]);
PHP;

it('makes an add-on table for the site that has the add-on, on its first admin request, and removes it with a site', function () use ($addonTables, $siteMadeAndDeleted) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';

    $migrator = Boot::app()->container()->make(Migrator::class);
    $network = get_network();
    $onMainSiteBefore = array_map($migrator->exists(...), ADDON_TABLES);
    $siteId = wp_insert_site([
        'domain' => $network->domain,
        'path' => '/corex-addon-' . substr(wp_generate_uuid4(), 0, 8) . '/',
        'network_id' => (int) $network->id,
        'user_id' => 1,
        'title' => 'Corex add-on tables fixture',
    ]);
    expect($siteId)->toBeInt();

    // See SiteDeletionTest: wp_delete_site() walks this directory whether or not it exists.
    wp_mkdir_p($this->onSite($siteId, static fn (): string => wp_upload_dir()['basedir']));

    try {
        $activated = $this->onSite($siteId, static fn (): array => array_map(
            static fn (string $plugin): mixed => activate_plugin($plugin, '', false, true),
            ADDON_PLUGINS_WITH_A_TABLE,
        ));
        expect($activated)->each->toBeNull();

        $prefix = $this->onSite($siteId, static fn (): string => $GLOBALS['wpdb']->prefix);
        $ownTables = array_map(static fn (string $name): string => $prefix . 'corex_' . $name, ADDON_TABLES);

        // A request that is neither an admin page nor cron writes no schema, so the tables are
        // not there yet, and nothing was loaded to make them.
        $before = $this->wpCliJson($siteId, $addonTables);
        expect(array_keys($before['tables']))->toBe($ownTables)
            ->and($before['tables'])->each->toBeFalse()
            ->and($before['dbdelta_loaded'])->toBeFalse();

        $firstAdminRequest = $this->wpCliJson($siteId, $addonTables, ['--context=admin']);
        expect($firstAdminRequest['is_admin'])->toBeTrue()
            ->and($firstAdminRequest['tables'])->each->toBeTrue()
            ->and($firstAdminRequest['recorded'])->toBe(['newsletter' => true, 'bookings' => true, 'careers' => true]);

        $after = $this->wpCliJson($siteId, $addonTables);
        expect($after['tables'])->each->toBeTrue()
            ->and($after['dbdelta_loaded'])->toBeFalse();

        // The main site does not have the add-ons, and got nothing.
        expect(array_map($migrator->exists(...), ADDON_TABLES))->toBe($onMainSiteBefore);

        $child = $this->wpCliJson($siteId, $siteMadeAndDeleted);
        expect($child['made'])->each->toBeTrue()
            ->and($child['left_after_delete'])->each->toBeFalse();
    } finally {
        // This request does not have the add-ons, so deleting the site from here would leave
        // their tables behind.
        $this->onSite($siteId, static fn (): array => array_map($migrator->drop(...), ADDON_TABLES));

        if (get_site($siteId) instanceof WP_Site) {
            wp_delete_site($siteId);
        }
    }
});
