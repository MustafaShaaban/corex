<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms;

defined('ABSPATH') || exit;

use Corex\Container\ContainerInterface;
use Corex\Foundation\ServiceProvider;
use Corex\Forms\Schema\SchemaResolver;
use Corex\Forms\Validation\RuleRegistry;
use Corex\Forms\Validation\Validator;

/**
 * Boots the forms engine: binds the headless cores (schema resolver, validator,
 * rule registry, form registry, submission service) and, on the boot pass, wires
 * the WordPress boundary — the registered forms, the submission CPT, the REST
 * submit route, the listeners, and the form block.
 */
final class FormsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(RuleRegistry::class);

        $this->container->singleton(
            SchemaResolver::class,
            static fn (ContainerInterface $c): SchemaResolver => new SchemaResolver($c->make(RuleRegistry::class)),
        );

        $this->container->singleton(
            Validator::class,
            static fn (ContainerInterface $c): Validator => new Validator($c->make(RuleRegistry::class)),
        );
    }

    public function boot(): void
    {
        // Boundary wiring (CPT, REST, listeners, block) is added per user story.
    }
}
