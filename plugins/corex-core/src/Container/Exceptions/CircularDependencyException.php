<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Container\Exceptions;

defined('ABSPATH') || exit;

/**
 * Thrown when a dependency graph cycles back on itself (spec FR-010).
 */
final class CircularDependencyException extends ContainerException
{
    /**
     * @param list<string> $chain The ids currently being resolved, in order.
     */
    public static function forChain(array $chain, string $id): self
    {
        $path = implode(' -> ', [...$chain, $id]);

        return new self(sprintf('Circular dependency detected while resolving: %s', $path));
    }
}
