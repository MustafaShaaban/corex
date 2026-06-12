<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Foundation;

defined('ABSPATH') || exit;

use Corex\Container\ContainerInterface;
use Corex\Database\QueryExecutor;
use Corex\Fields\AcfFieldDriver;
use Corex\Fields\FieldDriver;
use Corex\Fields\FieldResolver;
use Corex\Fields\MetaFieldDriver;
use Corex\Repositories\Hydrator;
use Corex\Support\Config\ConfigInterface;

/**
 * Registers the data layer: the ACF-optional field driver (resolved at runtime),
 * the hydrator, and the query executor (with the configured result cap). Concrete
 * repositories autowire these and are bound by the consuming module's provider.
 */
final class DataServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(\Corex\Database\Casts\Caster::class);
        $this->container->singleton(\Corex\Database\Schema\Migrator::class);

        // The registry of tables an app marks managed (spec 038); corex-config reads it to seed
        // the Corex → Data screen with a source per table. Apps register their tables into it.
        $this->container->singleton(\Corex\Database\Schema\ManagedTables::class);

        $this->container->singleton(
            FieldResolver::class,
            static fn (ContainerInterface $c): FieldResolver => new FieldResolver(
                $c->make(MetaFieldDriver::class),
                $c->make(AcfFieldDriver::class),
            ),
        );

        // Transient: re-evaluated each resolution so runtime ACF toggling is honored (FR-009).
        $this->container->bind(
            FieldDriver::class,
            static fn (ContainerInterface $c): FieldDriver => $c->make(FieldResolver::class)->driver(),
        );

        $this->container->singleton(
            Hydrator::class,
            static fn (ContainerInterface $c): Hydrator => new Hydrator($c->make(FieldDriver::class)),
        );

        $this->container->singleton(
            QueryExecutor::class,
            static fn (ContainerInterface $c): QueryExecutor => new QueryExecutor(
                $c->make(Hydrator::class),
                (int) $c->make(ConfigInterface::class)->get('query.max', 500),
            ),
        );
    }
}
