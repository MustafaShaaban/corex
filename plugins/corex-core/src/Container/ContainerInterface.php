<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Container;

defined('ABSPATH') || exit;

use Closure;
use Psr\Container\ContainerInterface as PsrContainerInterface;

/**
 * The Corex dependency-injection container contract.
 *
 * Extends PSR-11 (`get`/`has`) with the Laravel-inspired registration and
 * resolution surface every Corex module programs against (spec FR-005).
 */
interface ContainerInterface extends PsrContainerInterface
{
    /**
     * Register a transient binding: a new instance is built on every resolution.
     *
     * @param Closure(ContainerInterface, array<string, mixed>): mixed|string|null $concrete
     *        A factory closure, a concrete class-string, or null to resolve $id itself.
     */
    public function bind(string $id, Closure|string|null $concrete = null): void;

    /**
     * Register a shared binding: one instance is built once and reused for the lifecycle.
     *
     * @param Closure(ContainerInterface, array<string, mixed>): mixed|string|null $concrete
     */
    public function singleton(string $id, Closure|string|null $concrete = null): void;

    /**
     * Register an already-built object as a shared binding.
     */
    public function instance(string $id, object $instance): object;

    /**
     * Resolve $id, autowiring constructor dependencies.
     *
     * @param array<string, mixed> $parameters Override constructor arguments by name.
     */
    public function make(string $id, array $parameters = []): mixed;
}
