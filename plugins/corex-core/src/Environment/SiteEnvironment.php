<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Environment;

defined('ABSPATH') || exit;

use Corex\Support\Config\Source;

/**
 * The one answer to "is this a development, staging or production site" (spec 113, FR-001).
 *
 * In this order: `APP_ENV` in `.env`, a value the network locks, or the `corex_app_env`
 * option, each of which locks the operations mode; then the operations mode the owner chose;
 * then WordPress's environment type. Coming soon and Maintenance are production here: they change
 * what visitors see, not where mail goes.
 *
 * The three lock layers are read one by one rather than through the config repository, whose
 * defaults layer answers `app.env` with "production" on every site and so hides the two answers
 * below it.
 */
final class SiteEnvironment
{
    public const DEVELOPMENT = 'development';
    public const STAGING     = 'staging';
    public const PRODUCTION  = 'production';

    public const BY_DOTENV    = 'dotenv';
    public const BY_NETWORK   = 'network';
    public const BY_OPTION    = 'option';
    public const BY_MODE      = 'mode';
    public const BY_WORDPRESS = 'wordpress';

    /** @var list<string> */
    public const LOCKS = [self::BY_DOTENV, self::BY_NETWORK, self::BY_OPTION];

    /** The option the operations mode is stored in (corex-config's `OperationsModeStore`). */
    public const MODE_OPTION = 'corex_operations_mode';

    /** The operations modes that are production for mail. */
    public const PRODUCTION_MODES = ['production', 'coming-soon', 'maintenance'];

    /**
     * The operations modes that change only what visitors see. A lock never takes them away: a
     * site put in Coming soon or Maintenance stays closed to visitors whatever `APP_ENV` says,
     * while its mail follows the lock.
     */
    public const VISITOR_MODES = ['coming-soon', 'maintenance'];

    private const KEY = 'app.env';

    /**
     * @param list<array{0:string,1:Source}> $locks each layer that locks the mode, with the name
     *                                              {@see self::LOCKS} gives it, highest first
     */
    public function __construct(private readonly array $locks)
    {
    }

    public function current(): SiteEnvironmentAnswer
    {
        foreach ($this->locks as [$decidedBy, $source]) {
            if ($source->has(self::KEY)) {
                return new SiteEnvironmentAnswer($this->fromValue((string) $source->get(self::KEY)), $decidedBy);
            }
        }

        $mode = get_option(self::MODE_OPTION, '');
        if (is_string($mode) && $mode !== '') {
            $environment = $this->fromMode($mode);
            if ($environment !== null) {
                return new SiteEnvironmentAnswer($environment, self::BY_MODE);
            }
        }

        return new SiteEnvironmentAnswer($this->fromValue(wp_get_environment_type()), self::BY_WORDPRESS);
    }

    /**
     * Whether an operations mode may be chosen while a lock decides: the lock's own environment,
     * and Coming soon and Maintenance under any lock. Without a lock every mode may.
     */
    public function allowsMode(string $mode): bool
    {
        if (in_array($mode, self::VISITOR_MODES, true)) {
            return true;
        }

        $answer = $this->current();
        if (! $answer->isLocked()) {
            return true;
        }

        return $this->fromMode($mode) === $answer->environment;
    }

    /** What a value of `APP_ENV` or of WordPress's environment type means here. */
    public function fromValue(string $value): string
    {
        return match (strtolower(trim($value))) {
            'local', 'development', 'dev' => self::DEVELOPMENT,
            'staging'                     => self::STAGING,
            default                       => self::PRODUCTION,
        };
    }

    /** What an operations mode means here; null for a value that is not a mode. */
    public function fromMode(string $mode): ?string
    {
        return match (true) {
            $mode === self::DEVELOPMENT                    => self::DEVELOPMENT,
            $mode === self::STAGING                        => self::STAGING,
            in_array($mode, self::PRODUCTION_MODES, true)  => self::PRODUCTION,
            default                                        => null,
        };
    }
}
