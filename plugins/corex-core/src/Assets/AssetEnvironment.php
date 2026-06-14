<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Assets;

defined('ABSPATH') || exit;

/**
 * The resolved asset environment (spec 047) — local / staging / production — selecting the
 * version strategy and whether source maps are exposed. Pure: resolved from an explicit
 * config value, defaulting to **production-safe** behaviour when unset/unknown.
 */
final class AssetEnvironment
{
    public const LOCAL      = 'local';
    public const STAGING    = 'staging';
    public const PRODUCTION = 'production';

    private function __construct(public readonly string $name)
    {
    }

    public static function from(?string $value): self
    {
        return new self(match (strtolower(trim((string) $value))) {
            'local', 'development', 'dev' => self::LOCAL,
            'staging', 'stage'            => self::STAGING,
            default                       => self::PRODUCTION,
        });
    }

    public function isLocal(): bool
    {
        return $this->name === self::LOCAL;
    }

    public function isProduction(): bool
    {
        return $this->name === self::PRODUCTION;
    }

    /** Public source maps are exposed only in local — never in staging/production by default. */
    public function exposesSourceMaps(): bool
    {
        return $this->isLocal();
    }
}
