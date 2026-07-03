<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security;

use Corex\Admin\AdminPage;
use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OperationsModeController;
use Corex\Config\Operations\OperationsModeStore;
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
        private readonly OperationsMode $modes,
        private readonly OperationsModeStore $store,
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
            __('The operating mode, WordPress hardening status, and security posture for this site.', 'corex'),
        );

        echo $this->statusNotice();
        echo $this->modeCard();
        echo $this->checksCard($checks, $warnings);
        echo $this->auditCard();
        echo $this->deferralNote();
        echo $this->page->close();
    }

    /** A PRG success/error notice after a mode change (read-only query args; no state change here). */
    private function statusNotice(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status display after a PRG redirect.
        $status = isset($_GET['corex_status']) ? sanitize_key(wp_unslash($_GET['corex_status'])) : '';
        if ($status === '') {
            return '';
        }

        [$tone, $message] = match ($status) {
            'saved'   => ['success', __('Operations mode updated.', 'corex')],
            'confirm' => ['warning', __('That mode needs confirmation — tick the confirmation box and try again.', 'corex')],
            'invalid' => ['error', __('That is not a valid operations mode.', 'corex')],
            default   => ['', ''],
        };

        return $message === '' ? '' : $this->page->state($tone, __('Operations mode', 'corex'), $message);
    }

    /**
     * The real operations-mode control: current mode + a capability + nonce-gated selector with a
     * confirmation box for production/maintenance, plus the mode-specific warnings. Persisted by
     * {@see OperationsModeStore}; applied by {@see OperationsModeController}. Never a fake switch.
     */
    private function modeCard(): string
    {
        $current  = $this->store->current();
        $env      = $this->modes->describe($current);
        $declared = $this->store->isDeclared();

        $options = '';
        foreach ($this->modes->all() as $mode) {
            $meta = $this->modes->describe($mode);
            $options .= sprintf(
                '<option value="%1$s"%2$s>%3$s</option>',
                esc_attr($mode),
                selected($mode, $current, false),
                esc_html($meta['label']),
            );
        }

        $warnings = '';
        foreach ($this->modes->warnings($current) as $warning) {
            $warnings .= '<li>' . esc_html($warning) . '</li>';
        }

        $inherited = $declared ? '' :
            '<p class="corex-opsec__detail">' . esc_html__('Inherited from the WordPress environment type — declare a mode to override it.', 'corex') . '</p>';

        return '<section class="corex-surface corex-opsec__env is-' . esc_attr($env['tone']) . '">'
            . '<p class="corex-admin__eyebrow">' . esc_html__('OPERATIONS MODE', 'corex') . '</p>'
            . '<h2>' . esc_html($env['label']) . '</h2>'
            . '<p class="corex-opsec__detail">' . esc_html($env['detail']) . '</p>' . $inherited
            . '<ul class="corex-opsec__warnings">' . $warnings . '</ul>'
            . '<form class="corex-opsec__mode-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'
            . '<input type="hidden" name="action" value="' . esc_attr(OperationsModeController::ACTION) . '" />'
            . wp_nonce_field(OperationsModeController::ACTION, OperationsModeController::NONCE, true, false)
            . '<label class="corex-opsec__mode-label" for="corex-mode-select">' . esc_html__('Change mode', 'corex') . '</label>'
            . '<select id="corex-mode-select" name="corex_mode">' . $options . '</select>'
            . '<label class="corex-opsec__mode-confirm"><input type="checkbox" name="corex_confirm" value="1" /> '
            . esc_html__('I understand production and maintenance affect real visitors.', 'corex') . '</label>'
            . '<button type="submit" class="button button-primary">' . esc_html__('Apply mode', 'corex') . '</button>'
            . '</form></section>';
    }

    /**
     * The mode-change audit log (spec 065): the real recent history from {@see OperationsModeStore},
     * or an honest empty state. No fabricated activity.
     */
    private function auditCard(): string
    {
        $history = $this->store->history(8);

        if ($history === []) {
            return '<section class="corex-surface corex-opsec__audit">'
                . '<header class="corex-opsec__checks-head"><h2>' . esc_html__('Mode change history', 'corex') . '</h2></header>'
                . '<p class="corex-opsec__detail">' . esc_html__('No operations-mode changes recorded yet.', 'corex')
                . '</p></section>';
        }

        $rows = '';
        foreach ($history as $entry) {
            $user = $entry['user'] > 0 ? get_userdata($entry['user']) : false;
            $who  = $user ? $user->display_name : __('system', 'corex');
            $when = $entry['time'] > 0
                ? wp_date((string) get_option('date_format') . ' ' . (string) get_option('time_format'), $entry['time'])
                : '';

            $rows .= '<li class="corex-opsec__audit-row">'
                . '<span class="corex-opsec__audit-change"><code>' . esc_html($entry['from']) . '</code> &rarr; <code>'
                . esc_html($entry['to']) . '</code></span>'
                . '<span class="corex-opsec__audit-meta">' . esc_html($who) . ' · ' . esc_html($when) . '</span></li>';
        }

        return '<section class="corex-surface corex-opsec__audit">'
            . '<header class="corex-opsec__checks-head"><h2>' . esc_html__('Mode change history', 'corex') . '</h2></header>'
            . '<ul class="corex-opsec__audit-list">' . $rows . '</ul></section>';
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
     * Honest deferral for the remaining security sub-features: a custom login URL + failed-login rate
     * limiting (always with a reversible CLI/config recovery path) are the safe login-protection
     * foundation still to land; the CoreX capability/role matrix arrives as the Access & Abilities
     * baseline. Stated, never faked; this screen never renames WordPress core files.
     */
    private function deferralNote(): string
    {
        return '<div class="corex-opsec__note corex-surface">'
            . '<p class="corex-opsec__note-title">' . esc_html__('Login protection', 'corex') . '</p>'
            . '<p class="corex-opsec__note-text">'
            . esc_html__(
                'A custom login URL and failed-login rate limiting — always with a reversible CLI/config recovery path so you cannot be locked out — are the safe login-protection foundation still to land. CoreX never renames WordPress core files.',
                'corex',
            )
            . '</p></div>';
    }

    /**
     * The REAL, locally-verified hardening facts, gathered by the shared {@see HardeningFacts} boundary
     * so the Overview readiness panel and this screen never compute the same signal two ways.
     *
     * @return array{ssl:bool,fileEditDisabled:bool,debugDisplayOff:bool,defaultAdminAbsent:bool}
     */
    private function facts(): array
    {
        return HardeningFacts::gather();
    }
}
