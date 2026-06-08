<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Container;

defined('ABSPATH') || exit;

use Closure;
use Corex\Container\Exceptions\BindingResolutionException;
use Corex\Container\Exceptions\CircularDependencyException;
use Corex\Container\Exceptions\EntryNotFoundException;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * A focused PSR-11 dependency-injection container with constructor autowiring,
 * shared/transient bindings, and circular-dependency detection (spec FR-005–FR-010).
 *
 * See DECISIONS #21 for why Corex ships its own container rather than a third-party engine.
 */
final class Container implements ContainerInterface
{
    /**
     * @var array<string, array{concrete: Closure|string, shared: bool}>
     */
    private array $bindings = [];

    /**
     * @var array<string, mixed>
     */
    private array $instances = [];

    /**
     * Ids currently being resolved, used to detect cycles (FR-010).
     *
     * @var array<string, true>
     */
    private array $building = [];

    public function bind(string $id, Closure|string|null $concrete = null): void
    {
        $this->register($id, $concrete, shared: false);
    }

    public function singleton(string $id, Closure|string|null $concrete = null): void
    {
        $this->register($id, $concrete, shared: true);
    }

    public function instance(string $id, object $instance): object
    {
        $this->instances[$id] = $instance;

        return $instance;
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || isset($this->instances[$id]) || class_exists($id);
    }

    public function get(string $id): mixed
    {
        return $this->make($id);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function make(string $id, array $parameters = []): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->building[$id])) {
            throw CircularDependencyException::forChain(array_keys($this->building), $id);
        }

        $this->building[$id] = true;

        try {
            $binding  = $this->bindings[$id] ?? null;
            $concrete = $binding['concrete'] ?? $id;

            $object = $concrete instanceof Closure
                ? $concrete($this, $parameters)
                : $this->build($concrete, $parameters);

            if ($binding !== null && $binding['shared']) {
                $this->instances[$id] = $object;
            }

            return $object;
        } finally {
            unset($this->building[$id]);
        }
    }

    private function register(string $id, Closure|string|null $concrete, bool $shared): void
    {
        // Re-binding drops any previously cached shared instance.
        unset($this->instances[$id]);

        $this->bindings[$id] = ['concrete' => $concrete ?? $id, 'shared' => $shared];
    }

    /**
     * Instantiate a concrete class, autowiring its constructor dependencies.
     *
     * @param array<string, mixed> $parameters
     */
    private function build(string $concrete, array $parameters): object
    {
        try {
            $reflector = new ReflectionClass($concrete);
        } catch (ReflectionException) {
            throw EntryNotFoundException::forId($concrete);
        }

        if (! $reflector->isInstantiable()) {
            throw BindingResolutionException::notInstantiable($concrete);
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $concrete();
        }

        $arguments = array_map(
            fn (ReflectionParameter $parameter): mixed => $this->resolveParameter($concrete, $parameter, $parameters),
            $constructor->getParameters()
        );

        return $reflector->newInstanceArgs($arguments);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function resolveParameter(string $concrete, ReflectionParameter $parameter, array $parameters): mixed
    {
        $name = $parameter->getName();

        if (array_key_exists($name, $parameters)) {
            return $parameters[$name];
        }

        $type = $parameter->getType();

        if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
            return $this->make($type->getName());
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($parameter->allowsNull()) {
            return null;
        }

        throw BindingResolutionException::unresolvableParameter($concrete, $name);
    }
}
