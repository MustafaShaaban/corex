<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Release;

use InvalidArgumentException;

defined('ABSPATH') || exit;

/**
 * Immutable component coverage row for native-first client-site readiness.
 */
final class ComponentCoverageItem
{
    public const MECHANISM_COREX_BLOCK = 'corex-block';
    public const MECHANISM_WORDPRESS_CORE_BLOCK_STYLE = 'wordpress-core-block-style';
    public const MECHANISM_PATTERN = 'pattern';
    public const MECHANISM_FORM_FIELD = 'form-field';
    public const MECHANISM_ADMIN_COMPONENT = 'admin-component';
    public const MECHANISM_UTILITY = 'utility';
    public const MECHANISM_MISSING = 'missing';
    public const MECHANISM_DEFERRED = 'deferred';
    public const MECHANISM_PRO_CANDIDATE = 'pro-candidate';

    /**
     * @var list<string>
     */
    private const MECHANISMS = [
        self::MECHANISM_COREX_BLOCK,
        self::MECHANISM_WORDPRESS_CORE_BLOCK_STYLE,
        self::MECHANISM_PATTERN,
        self::MECHANISM_FORM_FIELD,
        self::MECHANISM_ADMIN_COMPONENT,
        self::MECHANISM_UTILITY,
        self::MECHANISM_MISSING,
        self::MECHANISM_DEFERRED,
        self::MECHANISM_PRO_CANDIDATE,
    ];

    /**
     * @var list<string>
     */
    private const FREE_PRO_CLASSIFICATIONS = [
        'free-core',
        'pro-candidate',
        'deferred',
        'out-of-scope',
    ];

    public readonly string $need;

    public readonly string $mechanism;

    public readonly string $source;

    public readonly string $accessibility;

    public readonly string $tokenStrategy;

    public readonly string $rtlStrategy;

    public readonly string $freePro;

    /**
     * @param array{
     *     need?: string,
     *     mechanism?: string,
     *     source?: string,
     *     accessibility?: string,
     *     tokenStrategy?: string,
     *     rtlStrategy?: string,
     *     freePro?: string
     * } $attributes
     */
    private function __construct(array $attributes)
    {
        $this->need = self::requiredString($attributes, 'need');
        $this->mechanism = self::requiredString($attributes, 'mechanism');
        $this->source = self::requiredString($attributes, 'source');
        $this->accessibility = self::requiredString($attributes, 'accessibility');
        $this->tokenStrategy = self::requiredString($attributes, 'tokenStrategy');
        $this->rtlStrategy = self::requiredString($attributes, 'rtlStrategy');
        $this->freePro = self::requiredString($attributes, 'freePro');

        if (! in_array($this->mechanism, self::MECHANISMS, true)) {
            throw new InvalidArgumentException(sprintf('Unknown component coverage mechanism "%s".', $this->mechanism));
        }

        if (! in_array($this->freePro, self::FREE_PRO_CLASSIFICATIONS, true)) {
            throw new InvalidArgumentException(sprintf('Unknown Free/Core classification "%s".', $this->freePro));
        }
    }

    /**
     * @param array{
     *     need?: string,
     *     mechanism?: string,
     *     source?: string,
     *     accessibility?: string,
     *     tokenStrategy?: string,
     *     rtlStrategy?: string,
     *     freePro?: string
     * } $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self($attributes);
    }

    /**
     * @return list<string>
     */
    public static function knownMechanisms(): array
    {
        return self::MECHANISMS;
    }

    /**
     * @param array<string,mixed> $attributes
     */
    private static function requiredString(array $attributes, string $key): string
    {
        $value = $attributes[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(sprintf('Component coverage field "%s" is required.', $key));
        }

        return trim($value);
    }
}
