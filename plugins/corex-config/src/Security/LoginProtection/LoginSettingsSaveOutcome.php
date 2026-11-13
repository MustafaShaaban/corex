<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

/**
 * What a login settings save did: the settings as stored, or the problems that refused it, with
 * nothing written (spec 124, FR-015).
 */
final readonly class LoginSettingsSaveOutcome
{
    /**
     * @param array<string,LoginSettingsProblem> $problems
     */
    private function __construct(
        public ?LoginProtectionSettings $settings,
        public array $problems,
    ) {
    }

    public static function saved(LoginProtectionSettings $settings): self
    {
        return new self($settings, []);
    }

    /**
     * @param array<string,LoginSettingsProblem> $problems
     */
    public static function refused(array $problems): self
    {
        return new self(null, $problems);
    }

    public function isRefused(): bool
    {
        return $this->problems !== [];
    }

    /** Another save held the settings past the wait, and nothing was written. */
    public function isBusy(): bool
    {
        return ($this->problems['request'] ?? null)?->reason === LoginSettingsProblem::REASON_BUSY;
    }
}
