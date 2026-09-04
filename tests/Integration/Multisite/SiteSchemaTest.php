<?php

/**
 * New-site schema installation against WordPress Multisite (spec 100 T068).
 *
 * @package Corex\Tests\Integration\Multisite
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Database\Schema\Migrator;
use Corex\Database\Schema\SchemaRegistry;

it('installs all seven foundation tables on the site created after activation', function () {
    $container = Boot::app()->container();
    $migrator = $container->make(Migrator::class);
    $tableNames = $container->make(SchemaRegistry::class)->tableNames();
    $siteId = $this->siteIdForPath('/site3/');

    expect($tableNames)->toHaveCount(7);

    $tablesExist = $this->onSite(
        $siteId,
        static fn (): array => array_map(
            static fn (string $name): bool => $migrator->exists($name),
            $tableNames,
        ),
    );

    expect($tablesExist)->each->toBeTrue();
});
