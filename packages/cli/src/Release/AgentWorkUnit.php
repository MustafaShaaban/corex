<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Release;

use InvalidArgumentException;

defined('ABSPATH') || exit;

/**
 * Immutable handoff record for one agent-owned work unit.
 */
final class AgentWorkUnit
{
    /**
     * @var list<string>
     */
    private const STATUSES = [
        'planned',
        'in-progress',
        'blocked',
        'ready-for-review',
        'done',
    ];

    public readonly string $branch;

    public readonly string $specPath;

    /**
     * @var list<string>
     */
    public readonly array $taskIds;

    /**
     * @var list<string>
     */
    public readonly array $filesOwned;

    public readonly string $handoff;

    /**
     * @var list<string>
     */
    public readonly array $verification;

    /**
     * @var list<string>
     */
    public readonly array $guards;

    public readonly string $status;

    /**
     * @param array{
     *     branch?: string,
     *     specPath?: string,
     *     taskIds?: list<string>,
     *     filesOwned?: list<string>,
     *     handoff?: string,
     *     verification?: list<string>,
     *     guards?: list<string>,
     *     status?: string
     * } $attributes
     */
    private function __construct(array $attributes)
    {
        $this->branch = self::requiredString($attributes, 'branch');
        $this->specPath = self::requiredString($attributes, 'specPath');
        $this->taskIds = self::requiredStringList($attributes, 'taskIds');
        $this->filesOwned = self::requiredStringList($attributes, 'filesOwned');
        $this->handoff = trim((string) ($attributes['handoff'] ?? ''));
        $this->verification = self::optionalStringList($attributes, 'verification');
        $this->guards = self::optionalStringList($attributes, 'guards');
        $this->status = self::requiredString($attributes, 'status');

        if (! in_array($this->status, self::STATUSES, true)) {
            throw new InvalidArgumentException(sprintf('Invalid agent work-unit status "%s".', $this->status));
        }
    }

    /**
     * @param array{
     *     branch?: string,
     *     specPath?: string,
     *     taskIds?: list<string>,
     *     filesOwned?: list<string>,
     *     handoff?: string,
     *     verification?: list<string>,
     *     guards?: list<string>,
     *     status?: string
     * } $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self($attributes);
    }

    /**
     * @return list<string>
     */
    public function completionIssues(): array
    {
        if ($this->status !== 'done') {
            return [];
        }

        $issues = [];

        if ($this->handoff === '') {
            $issues[] = 'handoff missing';
        }

        if ($this->verification === []) {
            $issues[] = 'verification missing';
        }

        if ($this->guards === []) {
            $issues[] = 'guards missing';
        }

        return $issues;
    }

    public function taskLabel(): string
    {
        return implode(',', $this->taskIds);
    }

    /**
     * @param array<string,mixed> $attributes
     */
    private static function requiredString(array $attributes, string $key): string
    {
        $value = $attributes[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(sprintf('Agent work-unit field "%s" is required.', $key));
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
            throw new InvalidArgumentException(sprintf('Agent work-unit field "%s" is required.', $key));
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
            throw new InvalidArgumentException(sprintf('Agent work-unit field "%s" must be a list.', $key));
        }

        return array_values(array_filter(array_map(
            static fn (mixed $value): string => is_string($value) ? trim($value) : '',
            $values,
        ), static fn (string $value): bool => $value !== ''));
    }
}

