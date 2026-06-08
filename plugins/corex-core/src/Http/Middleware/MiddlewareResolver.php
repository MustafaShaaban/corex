<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Http\Middleware;

defined('ABSPATH') || exit;

use Corex\Container\ContainerInterface;
use Corex\Support\BootLogger;

/**
 * Maps a declared middleware name (optionally `alias:parameter`) to a
 * container-resolved middleware. An unknown alias fails closed — it resolves to a
 * RejectingMiddleware, never a silent skip (spec FR-012, FR-014, FR-015).
 */
final class MiddlewareResolver
{
    private const ALIAS_PREFIX = 'corex.middleware.';

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly BootLogger $logger,
    ) {
    }

    public function resolve(string $name): Middleware
    {
        [$alias, $parameter] = array_pad(explode(':', $name, 2), 2, null);
        $entry = self::ALIAS_PREFIX . $alias;

        if (! $this->container->has($entry)) {
            $this->logger->error(sprintf('Unknown middleware "%s"; failing closed.', $name));

            return new RejectingMiddleware(sprintf('Unknown middleware: %s', $alias));
        }

        // The alias entry resolves to a factory: fn(?string $parameter): Middleware.
        $factory = $this->container->make($entry);

        return $factory($parameter);
    }

    /**
     * @param list<string> $names
     *
     * @return list<Middleware>
     */
    public function resolveAll(array $names): array
    {
        return array_map(fn (string $name): Middleware => $this->resolve($name), $names);
    }
}
