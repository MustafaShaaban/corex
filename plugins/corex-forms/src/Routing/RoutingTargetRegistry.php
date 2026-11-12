<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Routing;

defined('ABSPATH') || exit;

use Closure;
use InvalidArgumentException;

/**
 * Routing targets a developer adds (spec 122, US-FLW-07). A target is offered in the routing editor
 * under its label and stored as an `extension` target whose value is its key. Its callback receives
 * the submission (`values`, `flow`, `page_id`, `is_test`) and returns who it is assigned to: a user
 * ID, `['type' => 'user'|'team'|'role', 'value' => …]`, or null for nobody, and the fallback applies.
 */
final class RoutingTargetRegistry
{
    /** @var array<string,array{label:string,resolver:Closure}> */
    private array $targets = [];

    public function register(string $key, string $label, callable $resolver): void
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $key) !== 1 || isset($this->targets[$key]) || trim($label) === '') {
            throw new InvalidArgumentException(sprintf('Invalid or duplicate routing target: "%s".', $key));
        }

        $this->targets[$key] = ['label' => $label, 'resolver' => Closure::fromCallable($resolver)];
    }

    public function has(string $key): bool
    {
        return isset($this->targets[$key]);
    }

    public function label(string $key): string
    {
        return $this->targets[$key]['label'] ?? $key;
    }

    /** @param array<string,mixed> $submission */
    public function resolve(string $key, array $submission): ?Assignee
    {
        if (! isset($this->targets[$key])) {
            throw new InvalidArgumentException(sprintf('Unknown routing target: "%s".', $key));
        }

        return Assignee::from(($this->targets[$key]['resolver'])($submission));
    }

    /** @return list<array{key:string,label:string}> */
    public function definitions(): array
    {
        $definitions = [];
        foreach ($this->targets as $key => $target) {
            $definitions[] = ['key' => $key, 'label' => $target['label']];
        }

        return $definitions;
    }
}
