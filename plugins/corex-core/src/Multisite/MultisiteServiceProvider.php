<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Multisite;

defined('ABSPATH') || exit;

use Corex\Container\ContainerInterface;
use Corex\Database\Schema\SchemaRegistry;
use Corex\Database\Schema\SchemaSelfHeal;
use Corex\Database\Schema\SchemaVersionStore;
use Corex\Database\Schema\SiteMigrationRunner;
use Corex\Database\Schema\WpOptionSchemaVersionStore;
use Corex\Events\EventDispatcher;
use Corex\Foundation\ServiceProvider;

/**
 * Binds schema orchestration during the register pass, then enables only the
 * network-specific hooks during boot. Self-heal runs for both install shapes so
 * removing ConfigServiceProvider's direct installer cannot regress single-site.
 */
final class MultisiteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(SchemaRegistry::class);
        $this->container->singleton(WpOptionSchemaVersionStore::class);
        $this->container->singleton(
            SchemaVersionStore::class,
            static fn (ContainerInterface $c): SchemaVersionStore => $c->make(WpOptionSchemaVersionStore::class),
        );
        $this->container->singleton(SiteMigrationRunner::class);
        $this->container->singleton(
            SchemaSelfHeal::class,
            static fn (ContainerInterface $c): SchemaSelfHeal => new SchemaSelfHeal(
                $c->make(SiteMigrationRunner::class),
                $c->make(SiteContext::class),
                $c->make(MultisiteContext::class),
                $c->make(EventDispatcher::class),
            ),
        );
    }

    public function boot(): void
    {
        if ($this->container->make(MultisiteContext::class)->enabled()) {
            $scope = $this->container->make(SiteScope::class);

            if ($scope instanceof SiteScopeManager) {
                $scope->listen();
            }
        }

        $this->container->make(SchemaSelfHeal::class)->run();
    }

    /** @return list<class-string<\Corex\Hooks\SubscribesToHooks>> */
    public function subscribers(): array
    {
        return $this->container->make(MultisiteContext::class)->enabled()
            ? [SiteLifecycleSubscriber::class]
            : [];
    }
}
