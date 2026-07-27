<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security;

use Corex\Admin\AdminPage;
use Corex\Config\AdminUi\ScreenAsset;
use Corex\Config\Operations\ModeDisclosure;
use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OperationsModeController;
use Corex\Config\Operations\OperationsModeStore;
use Corex\Config\Operations\ProductionReadinessSnapshotFactory;
use Corex\Config\Security\LoginProtection\LoginAttemptRecord;
use Corex\Config\Security\LoginProtection\LoginLockoutReader;
use Corex\Config\Security\LoginProtection\LoginProtectionSettings;
use Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore;
use Corex\Config\Security\LoginProtection\LoginUrl;
use Corex\Security\Admin\AdminGuard;
use Corex\Support\DateTime\AdminDateTime;
use DateTimeImmutable;

defined('ABSPATH') || exit;

/**
 * The Operations & Security overview reports the REAL operating environment and locally-verified
 * WordPress hardening checks. The operations-mode switch is real, nonce/capability-gated, and
 * Production launch uses the same readiness evidence shown on the page.
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
        private readonly ProductionReadinessSnapshotFactory $readiness,
        private readonly LoginProtectionSettingsStore $loginSettings,
        private readonly LoginLockoutReader $lockouts,
        private readonly AdminDateTime $dateTime,
        private readonly ModeDisclosure $disclosure,
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
            ScreenAsset::version(dirname(COREX_CONFIG_FILE) . '/assets/operations-security.css'),
        );
        $base = dirname(__DIR__, 2);
        $asset = is_file($base . '/build/admin/index.asset.php')
            ? require $base . '/build/admin/index.asset.php'
            : ['dependencies' => [], 'version' => 'dev'];
        wp_enqueue_script(
            'corex-operations-security',
            plugins_url('build/admin/index.js', $base . '/corex-config.php'),
            [...$asset['dependencies'], 'corex-runtime'],
            $asset['version'],
            true,
        );
        wp_localize_script('corex-operations-security', 'corexSecurity', $this->securityConfig());
        wp_set_script_translations('corex-operations-security', 'corex');

        // The mode form's disclosure. Deliberately a separate, buildless file rather than part of
        // the React bundle: the form it enhances is server-rendered, and it must keep working when
        // this script does not load at all (FR-013). Tying it to the app would make a React failure
        // a form failure.
        wp_enqueue_script(
            'corex-operations-mode',
            plugins_url('assets/operations-mode.js', COREX_CONFIG_FILE),
            [],
            ScreenAsset::version(dirname(COREX_CONFIG_FILE) . '/assets/operations-mode.js'),
            true,
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
        echo '<div id="corex-security-app" aria-live="polite"></div>';
        // The real, nonce-gated mode control follows the readiness checklist the app renders, so the
        // evidence is read before the action it gates. The app used to also render a mode selector
        // of its own that applied nothing; it is gone, and this is the only one on the page.
        echo $this->modeCard();
        echo $this->checksCard($checks, $warnings);
        echo $this->auditCard();
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
            // Distinct from 'saved' on purpose: nothing was written and nothing was logged, and a
            // success message over a change that did not happen teaches the operator that this
            // notice means nothing — on the screen where it has to mean something.
            'unchanged' => ['info', __('No change — the site was already in that mode.', 'corex')],
            'blocked' => ['error', __('Production launch is blocked by readiness checks. Resolve them, or type PRODUCTION to override intentionally.', 'corex')],
            'production_confirm' => ['warning', __('Production mode needs typed confirmation. Type PRODUCTION and try again.', 'corex')],
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
        $snapshot = $this->readiness->fromCurrentSite(new DateTimeImmutable('now'));
        $blockers = $snapshot->blockingKeys();

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
            . '<form class="corex-opsec__mode-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '"'
            . ' data-corex-mode-form data-current-mode="' . esc_attr($current) . '">'
            . '<input type="hidden" name="action" value="' . esc_attr(OperationsModeController::ACTION) . '" />'
            . wp_nonce_field(OperationsModeController::ACTION, OperationsModeController::NONCE, true, false)
            . '<label class="corex-opsec__mode-label" for="corex-mode-select">' . esc_html__('Change mode', 'corex') . '</label>'
            // Both hooks: `data-corex-select` keeps the approved CoreX control (DECISIONS #141),
            // and `data-corex-mode-select` is what the disclosure binds to. They compose because
            // the upgrade keeps the native <select> as the submitted value and dispatches `change`
            // on it — so a listener bound here still hears the custom control's selections.
            . '<select id="corex-mode-select" name="corex_mode" data-corex-select data-corex-mode-select>'
            . $options . '</select>'
            . $this->modeBlocks($this->proposedMode($current), $blockers)
            . '<button type="submit" class="button button-primary" data-corex-mode-apply>'
            . esc_html__('Apply mode', 'corex') . '</button>'
            . '</form></section>';
    }

    /**
     * The mode the form is currently offering to apply.
     *
     * `?mode=` when it names a real mode, otherwise whatever the site is in. The query argument is
     * what makes the form work without JavaScript: a submission that fails for a missing
     * confirmation redirects back proposing the same mode, and the confirmation it needs is then on
     * screen. Read-only, so no nonce — it selects which description is visible and nothing else.
     */
    private function proposedMode(string $current): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view state.
        $requested = isset($_GET['mode']) ? sanitize_key(wp_unslash($_GET['mode'])) : '';

        return $this->modes->isValid($requested) ? $requested : $current;
    }

    /**
     * One block per mode: what it means, what follows from it, and the confirmation it needs.
     *
     * All four are rendered and three are hidden, rather than fetching the right one over REST when
     * the selection changes. A fetch would add a route, a spinner and a failure mode to a form that
     * has none of those, to save markup nobody is paying for.
     *
     * The inputs in hidden blocks are `disabled`, which is the part that matters: a disabled control
     * is not submitted, so the server can never receive a confirmation belonging to a mode the
     * operator did not choose. Hiding alone would still post the field.
     *
     * @param list<string> $blockers Readiness blockers, for the production block.
     */
    private function modeBlocks(string $proposed, array $blockers): string
    {
        $blocks = '';

        foreach ($this->disclosure->describeAll() as $described) {
            $mode     = $described['mode'];
            $isActive = $mode === $proposed;
            $inert    = $isActive ? '' : ' disabled';

            $consequences = '';
            foreach ($described['consequences'] as $consequence) {
                $consequences .= '<li>' . esc_html($consequence) . '</li>';
            }

            $blocks .= '<div class="corex-opsec__mode-block" data-mode="' . esc_attr($mode) . '"'
                . ($isActive ? '' : ' hidden') . '>'
                . '<p class="corex-opsec__detail">' . esc_html($described['summary']) . '</p>'
                . '<ul class="corex-opsec__mode-consequences">' . $consequences . '</ul>'
                . $this->confirmationControl($described['confirmation'], $mode, $blockers, $inert)
                . '</div>';
        }

        return $blocks;
    }

    /**
     * The one confirmation this mode asks for — and nothing belonging to any other mode.
     */
    private function confirmationControl(
        string $confirmation,
        string $mode,
        array $blockers,
        string $inert,
    ): string {
        if ($confirmation === ModeDisclosure::CONFIRM_PHRASE) {
            $gate = $blockers === []
                ? esc_html__('Production launch is ready. Type PRODUCTION to confirm the live-mode change.', 'corex')
                : sprintf(
                    /* translators: %d: number of blocking readiness checks */
                    esc_html(_n('%d blocking readiness check must be resolved or intentionally overridden by typing PRODUCTION.', '%d blocking readiness checks must be resolved or intentionally overridden by typing PRODUCTION.', count($blockers), 'corex')),
                    count($blockers),
                );

            return '<label class="corex-opsec__mode-label" for="corex-production-confirm">'
                . esc_html__('Production confirmation', 'corex') . '</label>'
                . '<input id="corex-production-confirm" type="text" name="corex_confirm_phrase" value=""'
                . ' autocomplete="off" placeholder="' . esc_attr__('Type PRODUCTION', 'corex') . '"' . $inert . ' />'
                . '<p class="corex-opsec__detail">' . $gate . '</p>';
        }

        if ($confirmation === ModeDisclosure::CONFIRM_ACKNOWLEDGEMENT) {
            return '<label class="corex-opsec__mode-confirm">'
                . '<input type="checkbox" name="corex_confirm" value="1"' . $inert . ' /> '
                . esc_html__('I understand maintenance affects real visitors.', 'corex') . '</label>';
        }

        return '';
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
            // The `> 0` guard is gone because `Instant` now owns that rule: a non-positive
            // timestamp is an absence everywhere, not a check each call site remembers to make.
            $when = $this->dateTime->format(
                $entry['time'],
                AdminDateTime::FULL,
                __('Time not recorded', 'corex'),
            );

            $rows .= '<li class="corex-opsec__audit-row">'
                . '<span class="corex-opsec__audit-change"><code>' . esc_html($entry['from']) . '</code> &rarr; <code>'
                . esc_html($entry['to']) . '</code></span>'
                . '<span class="corex-opsec__audit-meta">' . esc_html($who) . ' · '
                . $when->toHtml() . '</span></li>';
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
     * @return array<string,mixed>
     */
    private function securityConfig(): array
    {
        $settings = $this->loginSettings->current();

        return [
            'restUrl' => esc_url_raw(rest_url('corex/v1/security')),
            'nonce' => wp_create_nonce('wp_rest'),
            'mode' => $this->store->current(),
            'readiness' => $this->readinessPayload(),
            'loginPolicy' => $this->loginPolicyPayload($settings),
            'lockouts' => $this->lockoutsPayload(),
            'activity' => $this->activityPayload(),
            'recoveryCommand' => 'wp corex security reset-login',
        ];
    }

    /**
     * Real lockouts from the evidence table.
     *
     * This was hardcoded to an empty array, so the panel reported "no lockouts" no matter what had
     * actually been recorded — a control that always says the same thing tells the operator nothing.
     *
     * Identities are stored as SHA-256 by design (LoginAttemptRecord), so there is no name to show.
     * A short fingerprint is enough to tell two lockouts apart and to line one up with the audit
     * log, and the account is named only when the attempt matched a real user, which is already
     * recorded. Nothing here is reconstructed or guessed.
     *
     * @return list<array<string,mixed>>
     */
    private function lockoutsPayload(): array
    {
        $now = new DateTimeImmutable('now');

        return array_map(function (LoginAttemptRecord $record) use ($now): array {
            $lockedUntil = $record->lockedUntil;

            return [
                'id' => substr($record->identityHash, 0, 12) . '-' . ($lockedUntil?->format('U') ?? '0'),
                'identity' => substr($record->identityHash, 0, 12),
                'network' => substr($record->networkHash, 0, 12),
                'account' => $this->accountName($record->userId),
                'reason' => $record->reasonCode,
                'active' => $lockedUntil !== null && $lockedUntil > $now,
                'locked_until' => $lockedUntil === null ? '' : $this->machineDate($lockedUntil),
            ];
        }, $this->lockouts->recentLockouts($now));
    }

    /** The account a lockout hit, when the attempt matched a real user. */
    private function accountName(?int $userId): string
    {
        if ($userId === null || $userId < 1) {
            return '';
        }

        $user = get_userdata($userId);

        return $user === false ? '' : $user->user_login;
    }

    /**
     * The canonical machine value for a lockout expiry, for the React app to present.
     *
     * This used to return a *display* string built from the site's date/time options, with
     * `$date->format('c')` as its fallback — so when `wp_date()` returned an empty string the
     * screen showed a raw ISO timestamp to the operator. Sending the canonical value and letting
     * `CorexTime` render it means one formatter decides how a date looks, and the fallback path
     * cannot produce a different answer from the happy path (FR-015).
     */
    private function machineDate(DateTimeImmutable $date): string
    {
        return gmdate(DATE_ATOM, $date->getTimestamp());
    }

    /**
     * @return array{target_hash:string,blocking_keys:list<string>,checks:list<array<string,mixed>>}
     */
    private function readinessPayload(): array
    {
        $snapshot = $this->readiness->fromCurrentSite(new DateTimeImmutable('now'));

        return [
            'target_hash' => $snapshot->targetHash(),
            'blocking_keys' => $snapshot->blockingKeys(),
            'checks' => array_map(static fn (array $check): array => [
                'key' => $check['key'],
                'label' => $check['label'],
                'status' => $check['state'] === 'pass' ? 'pass' : 'review',
                'detail' => $check['summary'],
                'blocking' => $check['state'] === 'blocking',
                'resolution_url' => $check['resolution_url'],
            ], $snapshot->checks()),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function loginPolicyPayload(LoginProtectionSettings $settings): array
    {
        $stored = get_option(LoginProtectionSettingsStore::OPTION, []);
        $storedSlug = is_array($stored) ? (string) ($stored['custom_slug'] ?? '') : '';

        return [
            'enabled' => $settings->enabled,
            'block_default_endpoints' => $settings->blockDefaultEndpoints,
            'custom_slug' => $settings->customSlug,
            // The address the owner must actually use. Showing it is the difference between a
            // setting and a usable instruction — and it comes from the same resolver the guard
            // serves, so the screen cannot describe an address the site does not answer.
            'login_url' => esc_url_raw(LoginUrl::forSettings($settings)),
            // Whether the slug in use is the one that was stored. It will not be if a hand-edited
            // option, a migration, or an older CoreX left an unusable value: the store falls back
            // to a working default rather than lock the owner out, but silently substituting an
            // address and still reporting "protected" is exactly the discrepancy FR-011a forbids.
            'slug_substituted' => $storedSlug !== '' && sanitize_title($storedSlug) !== $settings->customSlug,
            'stored_slug' => $storedSlug,
            'max_attempts' => $settings->threshold,
            'window_seconds' => $settings->windowSeconds,
            'lockout_seconds' => $settings->lockoutSeconds,
            'trusted_proxies' => $settings->trustedProxyRanges,
            'retention_days' => $settings->retainDays,
            'successful_login_logging' => $settings->successfulLoginLogging,
        ];
    }

    /**
     * @return list<array{id:string,kind:string,label:string,tone:string,occurred_at:string}>
     */
    private function activityPayload(): array
    {
        return array_map(function (array $entry): array {
            $label = sprintf(
                /* translators: 1: old operations mode, 2: new operations mode. */
                __('Operations mode changed from %1$s to %2$s', 'corex'),
                (string) $entry['from'],
                (string) $entry['to'],
            );

            return [
                'id' => (string) ($entry['time'] . '-' . $entry['to']),
                'kind' => 'operations.mode.changed',
                'label' => $label,
                'tone' => 'info',
                // `gmdate`, not `wp_date`: both name the same instant, but `wp_date(DATE_ATOM)`
                // writes it with the site's offset while every other machine value in CoreX —
                // including `machineDate()` two methods up, and the whole persistence layer — is
                // UTC. Two conventions in one payload is a question a future reader has to answer
                // before they can trust either.
                'occurred_at' => $entry['time'] > 0
                    ? gmdate(DATE_ATOM, (int) $entry['time'])
                    : '',
            ];
        }, $this->store->history(8));
    }

    /**
     * The REAL, locally-verified hardening facts, gathered by the shared {@see HardeningFacts} boundary
     * so the Overview readiness panel and this screen never compute the same signal two ways.
     *
     * @return array{ssl:bool,fileEditDisabled:bool,debugDisplayOff:bool,defaultAdminAbsent:bool,indexingAllowed:bool,authSaltsConfigured:bool}
     */
    private function facts(): array
    {
        return HardeningFacts::gather();
    }
}
