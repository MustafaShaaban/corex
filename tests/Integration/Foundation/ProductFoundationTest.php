<?php

/**
 * Integration tests for Spec 068 shared service bindings and repeatable migrations.
 *
 * @package Corex\Tests\Integration\Foundation
 */

declare(strict_types=1);

use Corex\Access\AccessRequestStore;
use Corex\Access\RoleAbilityStore;
use Corex\Activity\ActivityRepository;
use Corex\Activity\ActivityService;
use Corex\Boot;
use Corex\Config\Access\AccessRequestRepository;
use Corex\Config\Access\AccessService;
use Corex\Config\Access\AccessTables;
use Corex\Config\Access\RoleAbilityRepository;
use Corex\Config\Activity\ActivityTable;
use Corex\Config\Activity\WpActivityRepository;
use Corex\Config\Data\DataRegistry;
use Corex\Config\Jobs\JobTable;
use Corex\Config\Jobs\WpJobRepository;
use Corex\Database\Schema\ManagedTables;
use Corex\Database\Schema\Migrator;
use Corex\Jobs\JobRepository;
use Corex\Jobs\JobService;

it('resolves every shared product foundation through the container', function () {
    $container = Boot::app()->container();

    expect($container->make(ActivityRepository::class))->toBeInstanceOf(WpActivityRepository::class)
        ->and($container->make(ActivityService::class))->toBeInstanceOf(ActivityService::class)
        ->and($container->make(RoleAbilityStore::class))->toBeInstanceOf(RoleAbilityRepository::class)
        ->and($container->make(AccessRequestStore::class))->toBeInstanceOf(AccessRequestRepository::class)
        ->and($container->make(AccessService::class))->toBeInstanceOf(AccessService::class)
        ->and($container->make(JobRepository::class))->toBeInstanceOf(WpJobRepository::class)
        ->and($container->make(JobService::class))->toBeInstanceOf(JobService::class);
});
it('reapplies all shared managed-table migrations without errors or data loss', function () {
    global $wpdb;

    $container = Boot::app()->container();
    $migrator  = $container->make(Migrator::class);
    $schemas   = [$container->make(ActivityTable::class)->schema()];
    array_push($schemas, ...$container->make(AccessTables::class)->schemas());
    $schemas[] = $container->make(JobTable::class)->schema();

    foreach ($schemas as $schema) {
        $migrator->create($schema);
        $migrator->create($schema);

        expect($migrator->exists($schema->name))->toBeTrue();
    }

    expect($wpdb->last_error)->toBe('');
});

it('registers activity access and job tables as real data sources', function () {
    $container    = Boot::app()->container();
    $managedNames = array_map(
        static fn ($table): string => $table->name,
        $container->make(ManagedTables::class)->all(),
    );
    $sourceKeys = array_map(
        static fn ($source): string => $source->key(),
        $container->make(DataRegistry::class)->all(),
    );

    foreach ([ActivityTable::NAME, AccessTables::ROLE_GRANTS, AccessTables::REQUESTS, JobTable::NAME] as $table) {
        expect($managedNames)->toContain($table)
            ->and($sourceKeys)->toContain('table-' . $table);
    }
});
