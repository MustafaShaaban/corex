<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Release;

use InvalidArgumentException;

defined('ABSPATH') || exit;

/**
 * Immutable readiness finding for the spec 055 report.
 */
final class ReadinessFinding
{
    public const STATUS_PASS = 'pass';
    public const STATUS_FAIL = 'fail';
    public const STATUS_WARNING = 'warning';
    public const STATUS_ENVIRONMENT_GATED = 'environment-gated';
    public const STATUS_NOT_RUN = 'not-run';

    /**
     * @var list<string>
     */
    private const STATUSES = [
        self::STATUS_PASS,
        self::STATUS_FAIL,
        self::STATUS_WARNING,
        self::STATUS_ENVIRONMENT_GATED,
        self::STATUS_NOT_RUN,
    ];

    /**
     * @param list<string> $evidence
     */
    public function __construct(
        public readonly string $category,
        public readonly string $status,
        public readonly string $summary,
        public readonly array $evidence,
        public readonly string $owner,
        public readonly bool $blocking,
        public readonly string $nextAction,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if (! in_array($this->status, self::STATUSES, true)) {
            throw new InvalidArgumentException(sprintf('Invalid readiness status "%s".', $this->status));
        }

        if ($this->status === self::STATUS_ENVIRONMENT_GATED && $this->evidence === []) {
            throw new InvalidArgumentException('Environment-gated findings require evidence.');
        }

        if ($this->status === self::STATUS_ENVIRONMENT_GATED && trim($this->nextAction) === '') {
            throw new InvalidArgumentException('Environment-gated findings require a next action.');
        }

        if ($this->blocking && trim($this->nextAction) === '') {
            throw new InvalidArgumentException('Blocking findings require a next action.');
        }
    }
}
