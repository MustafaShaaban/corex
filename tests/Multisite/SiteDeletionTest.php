<?php

/**
 * Deleted-site schema cleanup against WordPress Multisite (spec 100 T069).
 *
 * @package Corex\Tests\Multisite
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Database\Schema\Migrator;
use Corex\Database\Schema\SchemaRegistry;

it('removes every foundation table when a post-activation site is deleted', function () {
    global $wpdb;

    $network = get_network();
    $path = '/corex-delete-' . substr(wp_generate_uuid4(), 0, 8) . '/';
    $siteId = wp_insert_site([
        'domain'     => $network->domain,
        'path'       => $path,
        'network_id' => (int) $network->id,
        'user_id'    => 1,
        'title'      => 'Corex deletion fixture',
    ]);
    expect($siteId)->toBeInt();

    // A site that has never taken an upload has no uploads directory, and wp_delete_site() walks
    // that tree unconditionally (wp-includes/ms-site.php). Core silences its own opendir() with @,
    // but PHPUnit promotes the suppressed warning anyway and the test reports as warned for a
    // condition it did not create. Any real site has this directory; the fixture should too.
    $uploads = $this->onSite($siteId, static fn (): string => wp_upload_dir()['basedir']);
    wp_mkdir_p($uploads);

    try {
        $container = Boot::app()->container();
        $migrator = $container->make(Migrator::class);
        $tableNames = $container->make(SchemaRegistry::class)->tableNames();
        $fullNames = $this->onSite(
            $siteId,
            static fn (): array => array_map(
                static fn (string $name): string => $migrator->fullName($name),
                $tableNames,
            ),
        );
        $tablesExist = array_map(
            static fn (string $table): bool => $wpdb->get_var(
                $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)),
            ) === $table,
            $fullNames,
        );

        // Count owned by ConfigServiceProvider, not restated here — see SiteSchemaTest.
        expect($fullNames)->not->toBeEmpty()
            ->and($tablesExist)->each->toBeTrue();
        wp_delete_site($siteId);

        foreach ($fullNames as $table) {
            $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
            expect($found)->toBeNull();
        }
    } finally {
        if (get_site($siteId) instanceof WP_Site) {
            wp_delete_site($siteId);
        }
    }
});
