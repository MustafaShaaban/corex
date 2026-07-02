<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security;

use Corex\Admin\AdminPage;
use Corex\Config\Overview\EnvironmentMode;
use Corex\Security\Admin\AdminGuard;

defined('ABSPATH') || exit;

/**
 * The Operations & Security overview (spec 063, Phase 4): a truthful, read-only surface that reports the
 * REAL operating environment and REAL, locally-verified WordPress hardening checks. It does NOT ship the
 * dangerous mutation features the design envisions — an operations-mode switch, a login-URL/rate-limit
 * guard, or a capability editor — because none of those exist in the codebase and faking them (or a
 * mode that "changed" when it did not) would violate the truthfulness rule. Those are shown as honest
 * future capabilities. Read-only: no state change, so no nonce; access is AdminGuard-gated.
 */
final class OperationsSecurityScreen
{
    private string $hook = '';

    public function __construct(
        private readonly AdminGuard $guard,
        private readonly AdminPage $page,
        private readonly HardeningChecks $checks,
        private readonly EnvironmentMode $mode,
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'maybeEnqueue']);
    }

    public function menu(): void
    {
        $this->hook = (string) add_submenu_page(
            'corex-settings',
            __('CoreX Operations & Security', 'corex'),
            __('Operations & Security', 'corex'),
            'manage_options',
            'corex-operations-security',
            [$this, 'render'],
            30,
        );
    }

    public function maybeEnqueue(string $hook): void
    {
        if ($hook !== $this->hook || $this->hook === '') {
            return;
        }

        wp_enqueue_style(
            'corex-operations-security',
            plugins_url('assets/operations-security.css', COREX_CONFIG_FILE),
            ['corex-admin-shell'],
            '1.0.0',
        );
    }

    public function render(): void
    {
        if (! $this->guard->authorized()) {
            echo $this->page->permissionDenied('operations-security');

            return;
        }

        $checks   = $this->checks->checks($this->facts());
        $warnings = $this->checks->warnings($checks);

        echo $this->page->open(
            'operations-security',
            __('CoreX Operations & Security', 'corex'),
            __('The real operating environment and WordPress hardening status for this site.', 'corex'),
        );

        echo $this->environmentCard();
        echo $this->checksCard($checks, $warnings);
        echo $this->deferralNote();
        echo $this->page->close();
    }

    private function environmentCard(): string
    {
        $env = $this->mode->resolve(
            function_exists('wp_get_environment_type') ? (string) wp_get_environment_type() : 'production',
        );

        return '<section class="corex-surface corex-opsec__env is-' . esc_attr($env['tone']) . '">'
            . '<p class="corex-admin__eyebrow">' . esc_html__('ENVIRONMENT', 'corex') . '</p>'
            . '<h2>' . esc_html($env['label']) . '</h2>'
            . '<p class="corex-opsec__detail">' . esc_html($env['detail']) . '</p></section>';
    }

    /**
     * @param list<array{key:string,label:string,status:string,detail:string}> $checks
     */
    private function checksCard(array $checks, int $warnings): string
    {
        $summary = $warnings === 0
            ? '<span class="corex-opsec__ok">' . esc_html__('All hardening checks pass.', 'corex') . '</span>'
            : '<span class="corex-opsec__warn">' . sprintf(
                /* translators: %d: number of hardening checks needing attention */
                esc_html(_n('%d check needs attention.', '%d checks need attention.', $warnings, 'corex')),
                (int) $warnings,
            ) . '</span>';

        $rows = '';
        foreach ($checks as $check) {
            $rows .= '<li class="corex-opsec__check is-' . esc_attr($check['status']) . '">'
                . '<span class="corex-opsec__check-label">' . esc_html($check['label']) . '</span>'
                . '<span class="corex-opsec__check-status">' . esc_html($this->statusLabel($check['status'])) . '</span>'
                . '<span class="corex-opsec__check-detail">' . esc_html($check['detail']) . '</span></li>';
        }

        return '<section class="corex-surface corex-opsec__checks">'
            . '<header class="corex-opsec__checks-head"><h2>' . esc_html__('Hardening checks', 'corex') . '</h2>'
            . $summary . '</header><ul class="corex-opsec__list">' . $rows . '</ul></section>';
    }

    private function statusLabel(string $status): string
    {
        return $status === HardeningChecks::PASS ? __('Pass', 'corex') : __('Review', 'corex');
    }

    /**
     * Honest deferral: an operations-mode switch, a login-protection guard (custom login URL / rate
     * limiting), and a capability/role editor are future capabilities — stated, never faked. Naming them
     * here avoids a dead entry point while making clear nothing changes site behaviour yet.
     */
    private function deferralNote(): string
    {
        return '<div class="corex-opsec__note corex-surface">'
            . '<p class="corex-opsec__note-title">' . esc_html__('Coming later', 'corex') . '</p>'
            . '<p class="corex-opsec__note-text">'
            . esc_html__(
                'Switching operations mode (maintenance / coming-soon / read-only), login protection (a custom login URL and failed-login rate limiting, always with a reversible CLI/config recovery path), and a CoreX capability/role matrix are planned future capabilities. They are not enabled yet — this screen only reports real state and never renames WordPress core files or changes site behaviour.',
                'corex',
            )
            . '</p></div>';
    }

    /**
     * The REAL, locally-verified hardening facts. All read constants/functions — no network, no writes.
     *
     * @return array{ssl:bool,fileEditDisabled:bool,debugDisplayOff:bool,defaultAdminAbsent:bool}
     */
    private function facts(): array
    {
        // WordPress displays PHP errors to the page when WP_DEBUG is on AND WP_DEBUG_DISPLAY is not
        // explicitly false (its default when undefined is to display). Mirror that exactly.
        $debugOn        = defined('WP_DEBUG') && WP_DEBUG === true;
        $displaySet     = defined('WP_DEBUG_DISPLAY');
        $displayEnabled = $debugOn && (! $displaySet || WP_DEBUG_DISPLAY !== false);

        return [
            'ssl'                => is_ssl()
                || (function_exists('force_ssl_admin') && force_ssl_admin())
                || str_starts_with((string) home_url(), 'https://'),
            'fileEditDisabled'   => defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT === true,
            'debugDisplayOff'    => ! $displayEnabled,
            'defaultAdminAbsent' => username_exists('admin') === null,
        ];
    }
}
