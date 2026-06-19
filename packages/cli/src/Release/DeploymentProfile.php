<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Release;

use InvalidArgumentException;

defined('ABSPATH') || exit;

/**
 * Immutable deployment readiness profile.
 */
final class DeploymentProfile
{
    public readonly string $name;

    public readonly string $packageShape;

    /**
     * @var list<string>
     */
    public readonly array $buildCommands;

    /**
     * @var list<string>
     */
    public readonly array $dependencies;

    /**
     * @var list<string>
     */
    public readonly array $secrets;

    /**
     * @var list<string>
     */
    public readonly array $blockers;

    /**
     * @param array{
     *     name?: string,
     *     packageShape?: string,
     *     buildCommands?: list<string>,
     *     dependencies?: list<string>,
     *     secrets?: list<string>,
     *     blockers?: list<string>
     * } $attributes
     */
    private function __construct(array $attributes)
    {
        $this->name = self::requiredString($attributes, 'name');
        $this->packageShape = self::requiredString($attributes, 'packageShape');
        $this->buildCommands = self::requiredStringList($attributes, 'buildCommands');
        $this->dependencies = self::requiredStringList($attributes, 'dependencies');
        $this->secrets = self::requiredStringList($attributes, 'secrets');
        $this->blockers = self::optionalStringList($attributes, 'blockers');

        if (trim($this->name) === '' || trim($this->packageShape) === '') {
            throw new InvalidArgumentException('Deployment profiles require a name and package shape.');
        }

        if ($this->buildCommands === [] || $this->dependencies === [] || $this->secrets === []) {
            throw new InvalidArgumentException('Deployment profiles require build commands, dependencies, and secrets.');
        }
    }

    /**
     * @param array{
     *     name?: string,
     *     packageShape?: string,
     *     buildCommands?: list<string>,
     *     dependencies?: list<string>,
     *     secrets?: list<string>,
     *     blockers?: list<string>
     * } $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self($attributes);
    }

    /**
     * @param array<string,mixed> $attributes
     */
    private static function requiredString(array $attributes, string $key): string
    {
        $value = $attributes[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(sprintf('Deployment profile field "%s" is required.', $key));
        }

        return trim($value);
    }

    /**
     * @param array<string,mixed> $attributes
     *
     * @return list<string>
     */
    private static function requiredStringList(array $attributes, string $key): array
    {
        $values = self::optionalStringList($attributes, $key);

        if ($values === []) {
            throw new InvalidArgumentException(sprintf('Deployment profile field "%s" is required.', $key));
        }

        return $values;
    }

    /**
     * @param array<string,mixed> $attributes
     *
     * @return list<string>
     */
    private static function optionalStringList(array $attributes, string $key): array
    {
        $values = $attributes[$key] ?? [];

        if (! is_array($values)) {
            throw new InvalidArgumentException(sprintf('Deployment profile field "%s" must be a list.', $key));
        }

        return array_values(array_filter(array_map(
            static fn (mixed $value): string => is_string($value) ? trim($value) : '',
            $values,
        ), static fn (string $value): bool => $value !== ''));
    }
}
