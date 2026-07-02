<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security;

defined('ABSPATH') || exit;

/**
 * Pure model for the Security Center hardening checks (spec 063, Phase 4). Given REAL, locally-verified
 * facts about the WordPress install (the corex-config boundary reads the constants/functions), it
 * decides a truthful pass/warn status per check with an actionable detail. It computes nothing itself
 * and invents nothing — each row reflects a real signal. Status is meaning-by-label (pass/warn), never
 * colour alone. WordPress-free, so it is unit-testable.
 */
final class HardeningChecks
{
    public const PASS = 'pass';
    public const WARN = 'warn';

    /**
     * @param array{
     *   ssl:bool,
     *   fileEditDisabled:bool,
     *   debugDisplayOff:bool,
     *   defaultAdminAbsent:bool
     * } $facts
     *
     * @return list<array{key:string,label:string,status:string,detail:string}>
     */
    public function checks(array $facts): array
    {
        return [
            $this->check(
                'ssl',
                __('HTTPS', 'corex'),
                $facts['ssl'],
                __('The site is served over HTTPS.', 'corex'),
                __('Serve the site over HTTPS — install an SSL certificate and force it for admin.', 'corex'),
            ),
            $this->check(
                'file_edit',
                __('File editing disabled', 'corex'),
                $facts['fileEditDisabled'],
                __('The theme/plugin file editor is disabled in wp-admin.', 'corex'),
                __('Set DISALLOW_FILE_EDIT to true in wp-config.php to disable the in-admin code editor.', 'corex'),
            ),
            $this->check(
                'debug_display',
                __('Debug output hidden', 'corex'),
                $facts['debugDisplayOff'],
                __('PHP errors are not displayed to visitors.', 'corex'),
                __('Set WP_DEBUG_DISPLAY to false so errors are logged, not shown to visitors.', 'corex'),
            ),
            $this->check(
                'default_admin',
                __('No default "admin" account', 'corex'),
                $facts['defaultAdminAbsent'],
                __('There is no user literally named "admin".', 'corex'),
                __('Avoid a user named "admin" — it is the first username brute-force bots try.', 'corex'),
            ),
        ];
    }

    /**
     * @return array{key:string,label:string,status:string,detail:string}
     */
    private function check(string $key, string $label, bool $ok, string $passDetail, string $warnDetail): array
    {
        return [
            'key'    => $key,
            'label'  => $label,
            'status' => $ok ? self::PASS : self::WARN,
            'detail' => $ok ? $passDetail : $warnDetail,
        ];
    }

    /**
     * The number of checks that did not pass, for a truthful summary.
     *
     * @param list<array{status:string}> $checks
     */
    public function warnings(array $checks): int
    {
        return count(array_filter($checks, static fn (array $c): bool => $c['status'] === self::WARN));
    }
}
