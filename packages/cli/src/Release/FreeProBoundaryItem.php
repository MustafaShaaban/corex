<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Release;

use InvalidArgumentException;

defined('ABSPATH') || exit;

/**
 * Immutable Free/Core vs Pro capability classification.
 */
final class FreeProBoundaryItem
{
    public const CLASSIFICATION_FREE_CORE = 'free-core';
    public const CLASSIFICATION_PRO_CANDIDATE = 'pro-candidate';
    public const CLASSIFICATION_DEFERRED = 'deferred';
    public const CLASSIFICATION_OUT_OF_SCOPE = 'out-of-scope';

    /**
     * @var list<string>
     */
    private const CLASSIFICATIONS = [
        self::CLASSIFICATION_FREE_CORE,
        self::CLASSIFICATION_PRO_CANDIDATE,
        self::CLASSIFICATION_DEFERRED,
        self::CLASSIFICATION_OUT_OF_SCOPE,
    ];

    public readonly string $capability;

    public readonly string $classification;

    public readonly string $reason;

    public readonly bool $securityCritical;

    /**
     * @param array{
     *     capability?: string,
     *     classification?: string,
     *     reason?: string,
     *     securityCritical?: bool
     * } $attributes
     */
    private function __construct(array $attributes)
    {
        $this->capability = self::requiredString($attributes, 'capability');
        $this->classification = self::requiredString($attributes, 'classification');
        $this->reason = self::requiredString($attributes, 'reason');
        $this->securityCritical = (bool) ($attributes['securityCritical'] ?? false);

        if (! in_array($this->classification, self::CLASSIFICATIONS, true)) {
            throw new InvalidArgumentException(sprintf('Unknown Free/Core classification "%s".', $this->classification));
        }

        if ($this->securityCritical && $this->classification === self::CLASSIFICATION_PRO_CANDIDATE) {
            throw new InvalidArgumentException('Security-critical capabilities cannot be Pro candidates.');
        }
    }

    /**
     * @param array{
     *     capability?: string,
     *     classification?: string,
     *     reason?: string,
     *     securityCritical?: bool
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
            throw new InvalidArgumentException(sprintf('Free/Core boundary field "%s" is required.', $key));
        }

        return trim($value);
    }
}

