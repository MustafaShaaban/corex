<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Hooks;

defined('ABSPATH') || exit;

use Corex\Container\ContainerInterface;

/**
 * Wires a subscriber's declared hooks to WordPress. The subscriber is resolved
 * from the container so its dependencies inject (FR-016); each hook→method pair
 * is registered at most once (FR-017). `add_filter` registers actions too — in
 * WordPress `add_action` is an alias of `add_filter`.
 */
final class HookRegistry
{
    /**
     * @var array<string, true>
     */
    private array $wired = [];

    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /**
     * @param class-string<SubscribesToHooks> $subscriberClass
     */
    public function register(string $subscriberClass): void
    {
        $subscriber = $this->container->make($subscriberClass);

        foreach ($subscriber->hooks() as $hook => $definition) {
            [$method, $priority, $acceptedArgs] = $this->normalize($definition);

            $key = $subscriberClass . '::' . $method . '@' . $hook;

            if (isset($this->wired[$key])) {
                continue;
            }

            $this->wired[$key] = true;

            add_filter($hook, [$subscriber, $method], $priority, $acceptedArgs);
        }
    }

    /**
     * @param string|array{0: string, 1?: int, 2?: int} $definition
     *
     * @return array{0: string, 1: int, 2: int}
     */
    private function normalize(string|array $definition): array
    {
        if (is_string($definition)) {
            return [$definition, 10, 1];
        }

        return [$definition[0], $definition[1] ?? 10, $definition[2] ?? 1];
    }
}
