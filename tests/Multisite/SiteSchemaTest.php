<?php

/**
 * New-site schema installation against WordPress Multisite (spec 100 T068).
 *
 * @package Corex\Tests\Multisite
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Database\Schema\Migrator;
use Corex\Database\Schema\SchemaRegistry;

it('installs every foundation table on the site created after activation', function () {
    $container = Boot::app()->container();
    $migrator = $container->make(Migrator::class);
    $tableNames = $container->make(SchemaRegistry::class)->tableNames();
    $siteId = $this->siteIdForPath('/site3/');

    // Not a hard-coded count. ConfigServiceProvider owns how many foundation tables there
    // are, and it registered an eighth (notification_user_state) after this spec was written
    // — so `toHaveCount(7)` failed on a number, saying nothing about whether a new site gets
    // its schema. What matters here is that every registered table arrives, whatever the
    // registry holds.
    expect($tableNames)->not->toBeEmpty();

    $tablesExist = $this->onSite(
        $siteId,
        static fn (): array => array_map(
            static fn (string $name): bool => $migrator->exists($name),
            $tableNames,
        ),
    );

    expect($tablesExist)->each->toBeTrue();
});
