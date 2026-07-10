<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use Closure;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Scalar-only email variable extensions for flow notifications.
 */
final class EmailVariableRegistry
{
    /** @var array<string,array{label:string,resolver:Closure}> */
    private array $variables = [];

    public function register(string $key, string $label, callable $resolver): void
    {
        if (! preg_match('/^[a-z][a-z0-9_.]*$/', $key) || isset($this->variables[$key])) {
            throw new InvalidArgumentException(sprintf('Invalid or duplicate email variable: "%s".', $key));
        }

        $this->variables[$key] = ['label' => $label, 'resolver' => Closure::fromCallable($resolver)];
    }

    /** @param array<string,mixed> $context */
    public function resolve(string $key, array $context): string|int|float|bool|null
    {
        if (! isset($this->variables[$key])) {
            throw new InvalidArgumentException(sprintf('Unknown email variable: "%s".', $key));
        }

        $resolved = ($this->variables[$key]['resolver'])($context);
        if ($resolved !== null && ! is_scalar($resolved)) {
            throw new UnexpectedValueException('Email variables must resolve to scalar values or null.');
        }

        return $resolved;
    }

    /** @return list<array{key:string,label:string}> */
    public function definitions(): array
    {
        $definitions = [];
        foreach ($this->variables as $key => $definition) {
            $definitions[] = ['key' => $key, 'label' => $definition['label']];
        }

        return $definitions;
    }
}
