<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Environment;

defined('ABSPATH') || exit;

/**
 * Whether this is a development, staging or production site, and the setting that said so
 * (spec 113, FR-001).
 */
final readonly class SiteEnvironmentAnswer
{
    public function __construct(
        public string $environment,
        public string $decidedBy,
    ) {
    }

    public function isDevelopment(): bool
    {
        return $this->environment === SiteEnvironment::DEVELOPMENT;
    }

    public function isStaging(): bool
    {
        return $this->environment === SiteEnvironment::STAGING;
    }

    public function isProduction(): bool
    {
        return $this->environment === SiteEnvironment::PRODUCTION;
    }

    /** Set in `.env`, by the network or by the `corex_app_env` option: the mode selector cannot change it. */
    public function isLocked(): bool
    {
        return in_array($this->decidedBy, SiteEnvironment::LOCKS, true);
    }

    /** The environment's name, as a screen shows it. */
    public function label(): string
    {
        return match ($this->environment) {
            SiteEnvironment::DEVELOPMENT => __('Development', 'corex'),
            SiteEnvironment::STAGING     => __('Staging', 'corex'),
            default                      => __('Production', 'corex'),
        };
    }

    /** Where the answer comes from, in words a site owner can act on. */
    public function source(): string
    {
        return match ($this->decidedBy) {
            SiteEnvironment::BY_DOTENV  => __('APP_ENV in the site\'s .env file', 'corex'),
            SiteEnvironment::BY_NETWORK => __('the network\'s settings', 'corex'),
            SiteEnvironment::BY_OPTION  => __('the corex_app_env option', 'corex'),
            SiteEnvironment::BY_MODE    => __('the operations mode', 'corex'),
            default                     => __('WordPress\'s environment type', 'corex'),
        };
    }

    /** The environment and where it is set, in one line: "Development (set by the operations mode)". */
    public function summary(): string
    {
        return sprintf(
            /* translators: 1: environment name, 2: where it is set. */
            __('%1$s (set by %2$s)', 'corex'),
            $this->label(),
            $this->source(),
        );
    }

    /** @return array{environment:string,label:string,decided_by:string,source:string,locked:bool} */
    public function toArray(): array
    {
        return [
            'environment' => $this->environment,
            'label'       => $this->label(),
            'decided_by'  => $this->decidedBy,
            'source'      => $this->source(),
            'locked'      => $this->isLocked(),
        ];
    }
}
