<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Http;

defined('ABSPATH') || exit;

use Corex\Container\ContainerInterface;
use ReflectionClass;

/**
 * Discovers controllers by convention: every instantiable class in a module's
 * `Controllers/` directory (mapped to its FQCN via the module's PSR-4 prefix) is
 * registered with the container — no annotations, no central list (spec FR-018).
 * Abstracts, interfaces, traits, and non-class files are skipped (FR-019).
 */
final class ControllerMap
{
    /**
     * @var list<class-string>
     */
    private array $controllers = [];

    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /**
     * Scan each module's controllers directory and register the controllers found.
     * Read the result with controllers().
     *
     * @param array<string, string> $namespaceToDir PSR-4 prefix => directory.
     */
    public function discover(array $namespaceToDir): void
    {
        foreach ($namespaceToDir as $namespace => $directory) {
            $this->discoverIn(rtrim($namespace, '\\') . '\\', $directory);
        }
    }

    /**
     * All controllers discovered so far, across every module scanned.
     *
     * @return list<class-string>
     */
    public function controllers(): array
    {
        return $this->controllers;
    }

    private function discoverIn(string $namespace, string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (glob(rtrim($directory, '/\\') . '/*.php') ?: [] as $file) {
            $class = $namespace . basename($file, '.php');

            if ($this->isInstantiableClass($class)) {
                $this->container->bind($class);
                $this->controllers[] = $class;
            }
        }
    }

    /**
     * @phpstan-assert-if-true class-string $class
     */
    private function isInstantiableClass(string $class): bool
    {
        return class_exists($class) && (new ReflectionClass($class))->isInstantiable();
    }
}
