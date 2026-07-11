<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use Closure;
use InvalidArgumentException;

/**
 * Runtime extension registry for custom flow pipeline actions.
 */
final class FlowActionRegistry
{
    /** @var array<string,array{label:string,action:Closure}> */
    private array $actions = [];

    public function register(string $key, string $label, callable $action): void
    {
        if (! preg_match('/^[a-z][a-z0-9_]*$/', $key) || isset($this->actions[$key])) {
            throw new InvalidArgumentException(sprintf('Invalid or duplicate flow action: "%s".', $key));
        }

        $this->actions[$key] = ['label' => $label, 'action' => Closure::fromCallable($action)];
    }

    /** @param array<string,mixed> $context */
    public function execute(string $key, array $context): mixed
    {
        if (! isset($this->actions[$key])) {
            throw new InvalidArgumentException(sprintf('Unknown flow action: "%s".', $key));
        }

        return ($this->actions[$key]['action'])($context);
    }

    /** @return list<array{key:string,label:string}> */
    public function definitions(): array
    {
        $definitions = [];
        foreach ($this->actions as $key => $definition) {
            $definitions[] = ['key' => $key, 'label' => $definition['label']];
        }

        return $definitions;
    }
}
