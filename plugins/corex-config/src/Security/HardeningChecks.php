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
     *   defaultAdminAbsent:bool,
     *   indexingAllowed:bool,
     *   authSaltsConfigured:bool,
     *   trustedProxiesNarrow?:bool,
     *   trustedProxiesWellFormed?:bool
     * } $facts `trustedProxiesNarrow` and `trustedProxiesWellFormed` pass when left out, as facts
     *          gathered before they existed are (spec 124, FR-015).
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
            $this->check(
                'search_indexing',
                __('Search indexing allowed', 'corex'),
                $facts['indexingAllowed'],
                __('Search engines are not discouraged from indexing this production site.', 'corex'),
                __('Review Settings → Reading → Search Engine Visibility before Production launch.', 'corex'),
            ),
            $this->check(
                'auth_salts',
                __('Authentication salts configured', 'corex'),
                $facts['authSaltsConfigured'],
                __('WordPress authentication unique keys and salts are configured.', 'corex'),
                __('Set unique WordPress authentication unique keys and salts in wp-config.php.', 'corex'),
            ),
            $this->trustedProxies($facts['trustedProxiesNarrow'] ?? true, $facts['trustedProxiesWellFormed'] ?? true),
        ];
    }

    /**
     * One row for the trusted proxy list, warning with the sentence for its worse problem (spec 124,
     * FR-015; DECISIONS #629).
     *
     * A list stored before the route and the command refused `/0` is still used as stored: ignoring it
     * would count every visitor behind a CDN as the CDN. A stored line that is not an address or range
     * is ignored by the resolver, so its proxy is not trusted and its visitors are counted as the
     * proxy; the owner is told which kind of line to fix.
     *
     * @return array{key:string,label:string,status:string,detail:string}
     */
    private function trustedProxies(bool $narrow, bool $wellFormed): array
    {
        return $this->check(
            'trusted_proxies',
            __('Trusted proxies name only your proxies', 'corex'),
            $narrow && $wellFormed,
            __('No trusted proxy range covers every address, and every line is an address or a range.', 'corex'),
            ! $narrow
                ? __('A trusted proxy range covers every address (/0), so any visitor can choose the address their failed sign-ins are counted by and get around the limit. List only your own proxies with wp corex security trusted-proxies, or clear the list with --clear.', 'corex')
                : __('A trusted proxy line is not an address or a range, so CoreX ignores it and does not trust that proxy: its visitors are counted as the proxy. List your proxies again with wp corex security trusted-proxies.', 'corex'),
        );
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
