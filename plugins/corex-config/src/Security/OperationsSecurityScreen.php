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
use Corex\Config\Operations\PreviewAccess;
use Corex\Config\Operations\PreviewLinkController;
use Corex\Config\Operations\PreviewLinkResult;
use Corex\Config\Operations\PreviewLinkService;
use Corex\Config\Operations\ProductionReadinessSnapshotFactory;
use Corex\Config\Security\LoginProtection\LoginAttemptRecord;
use Corex\Config\Security\LoginProtection\LoginLockoutReader;
use Corex\Config\Security\LoginProtection\LoginProtectionSettings;
use Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore;
use Corex\Config\Security\LoginProtection\LoginUrl;
use Corex\Security\Admin\AdminGuard;
use Corex\Cache\Status\CacheStatusReport;
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
        private readonly CacheStatusReport $cacheStatus,
        private readonly PreviewAccess $preview,
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

    /**
     * The screen's sections, in the order an operator meets them.
     *
     * An allow-list rather than a free `?tab=`: an unrecognised value falls back to the overview
     * rather than rendering nothing, and nothing here can be reached that is not listed.
     *
     * `cache` was deliberately absent until spec 078 had something to put in it: a section
     * promising cache management and delivering a heading would have been exactly the dead end the
     * product mandate forbids.
     *
     * @return array<string, string>
     */
    private function sections(): array
    {
        return [
            'overview'  => __('Overview', 'corex'),
            'environment' => __('Environment & Maintenance', 'corex'),
            'login'     => __('Login Protection', 'corex'),
            'hardening' => __('Hardening', 'corex'),
            'activity'  => __('Activity', 'corex'),
            'cache'     => __('Cache & Performance', 'corex'),
        ];
    }

    /** The section asked for, when it is one we have. Otherwise the overview. */
    private function activeSection(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view state.
        $requested = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';

        return array_key_exists($requested, $this->sections()) ? $requested : 'overview';
    }

    public function render(): void
    {
        if (! $this->guard->authorized()) {
            echo $this->page->permissionDenied('operations-security');

            return;
        }

        $checks   = $this->checks->checks($this->facts());
        $warnings = $this->checks->warnings($checks);
        $active   = $this->activeSection();

        echo $this->page->open(
            'operations-security',
            __('CoreX Operations & Security', 'corex'),
            __('The operating mode, WordPress hardening status, and security posture for this site.', 'corex'),
        );

        // Links, not an ARIA tablist. `AdminPage::tabs()` renders `?tab=` anchors with
        // `aria-current="page"`, which means the address reflects the section, Back and Forward
        // work, a redirect can land on the right one, and every bit of it works with JavaScript
        // off — none of which a tablist gives without writing focus management first.
        echo $this->page->tabs(
            'corex-operations-security',
            $this->sections(),
            $active,
            __('Operations sections', 'corex'),
        );

        echo $this->statusNotice();
        echo '<div class="corex-opsec__sections">';
        echo $this->section($active, $checks, $warnings);
        echo '</div>';
        echo $this->page->close();
    }

    /**
     * One section's content. Each is a real surface with real state — none renders empty.
     *
     * The React island moves with its section rather than mounting once for the whole screen: its
     * panels belong to three different sections now, so the mount node names the section and the
     * app renders only that section's panels. One app, one state module, three homes.
     *
     * @param list<array<string,mixed>> $checks
     */
    private function section(string $active, array $checks, int $warnings): string
    {
        return match ($active) {
            'environment' => $this->securityApp('environment') . $this->modeCard()
                . $this->previewLinkCard() . $this->auditCard(),
            'login'       => $this->securityApp('login'),
            'hardening'   => $this->checksCard($checks, $warnings),
            'activity'    => $this->securityApp('activity'),
            'cache'       => $this->cacheCard(),
            default       => $this->overviewCard($checks, $warnings),
        };
    }


    /**
     * Cache & Performance (spec 078, FR-016).
     *
     * Every layer, its real state, and — where CoreX can act — a control that performs a real
     * operation. Where it cannot, the reason is on screen rather than a disabled button with no
     * explanation, because "why is this greyed out" is the question a disabled control always asks
     * and rarely answers.
     */
    private function cacheCard(): string
    {
        $rows = '';

        foreach ($this->cacheStatus->layers() as $layer) {
            $checked = $this->dateTime->format(
                $layer->checkedAt,
                AdminDateTime::FULL,
                __('Not checked', 'corex'),
            );

            $action = $layer->manageable && $layer->safeToClear
                ? '<button type="button" class="button corex-cache__action" data-corex-cache-clear="'
                    . esc_attr($layer->key) . '">' . esc_html__('Clear', 'corex') . '</button>'
                : '<span class="corex-cache__no-action">' . esc_html__('No action here', 'corex') . '</span>';

            $rows .= '<li class="corex-cache__layer is-' . esc_attr($layer->state->value) . '">'
                . '<div class="corex-cache__layer-head">'
                . '<span class="corex-cache__layer-name">' . esc_html($layer->name) . '</span>'
                . '<span class="corex-cache__layer-state">' . esc_html($layer->state->label()) . '</span>'
                . '</div>'
                . '<p class="corex-cache__layer-purpose">' . esc_html($layer->purpose) . '</p>'
                . ($layer->detail !== ''
                    ? '<p class="corex-cache__layer-detail">' . esc_html($layer->detail) . '</p>'
                    : '')
                . '<p class="corex-cache__layer-meta">'
                . ($layer->provider !== ''
                    ? '<span>' . esc_html($layer->provider) . '</span>'
                    : '')
                . '<span>' . esc_html__('Checked', 'corex') . ' ' . $checked->toHtml() . '</span>'
                . $action
                . '</p>'
                . '</li>';
        }

        return '<section class="corex-surface corex-cache">'
            . '<p class="corex-admin__eyebrow">' . esc_html__('CACHE & PERFORMANCE', 'corex') . '</p>'
            . '<h2>' . esc_html__('What is caching on this site', 'corex') . '</h2>'
            . '<p class="corex-opsec__detail">'
            . esc_html__('Each layer is checked rather than assumed. CoreX’s own caches are safe to clear; rate limits and pending confirmations are never removed by a cache action.', 'corex')
            . '</p>'
            . '<ul class="corex-cache__layers">' . $rows . '</ul>'
            . '</section>';
    }

    /** The React mount, told which section it is standing in. */
    private function securityApp(string $section): string
    {
        return '<div id="corex-security-app" aria-live="polite" data-section="'
            . esc_attr($section) . '"></div>';
    }

    /**
     * The overview: everything an operator needs to decide whether to look further (FR-005).
     *
     * Every value is read, never assumed — and each links to the section that owns it, so this
     * summarises rather than duplicating. The environment and the mode appear together here because
     * this is the first place someone looks to answer "is this site live", and the two answers to
     * that question have to be visible at the same time.
     *
     * @param list<array<string,mixed>> $checks
     */
    private function overviewCard(array $checks, int $warnings): string
    {
        $current  = $this->store->current();
        $env      = $this->modes->describe($current);
        $snapshot = $this->readiness->fromCurrentSite(new DateTimeImmutable('now'));
        $blockers = count($snapshot->blockingKeys());
        $policy   = $this->loginSettings->current();
        $lockouts = $this->lockouts->recentLockouts(new DateTimeImmutable('now'));
        $active   = 0;
        $now      = new DateTimeImmutable('now');
        foreach ($lockouts as $record) {
            if ($record->lockedUntil !== null && $record->lockedUntil > $now) {
                $active++;
            }
        }

        $rows = [
            [
                __('Operations mode', 'corex'),
                $env['label'],
                'environment',
            ],
            [
                __('WordPress environment', 'corex'),
                $this->environmentType(),
                'environment',
            ],
            [
                __('Production readiness', 'corex'),
                $blockers === 0
                    ? __('No blockers', 'corex')
                    : sprintf(
                        /* translators: %d: number of blocking readiness checks. */
                        _n('%d blocker', '%d blockers', $blockers, 'corex'),
                        $blockers,
                    ),
                'environment',
            ],
            [
                __('Public site', 'corex'),
                $this->publicSiteSummary($current),
                'environment',
            ],
            [
                __('Login protection', 'corex'),
                $policy->enabled ? __('On', 'corex') : __('Off', 'corex'),
                'login',
            ],
            [
                __('Default login endpoints', 'corex'),
                $policy->blockDefaultEndpoints
                    ? __('Hidden', 'corex')
                    : __('Reachable', 'corex'),
                'login',
            ],
            [
                __('Active lockouts', 'corex'),
                (string) $active,
                'login',
            ],
            [
                __('Hardening warnings', 'corex'),
                (string) $warnings,
                'hardening',
            ],
        ];

        $items = '';
        foreach ($rows as [$label, $value, $section]) {
            $items .= '<li class="corex-opsec__summary-item">'
                . '<span class="corex-opsec__summary-label">' . esc_html($label) . '</span>'
                . '<span class="corex-opsec__summary-value">' . esc_html($value) . '</span>'
                . '<a class="corex-opsec__summary-link" href="' . esc_url($this->sectionUrl($section)) . '">'
                . esc_html__('Open', 'corex') . '</a></li>';
        }

        return '<section class="corex-surface corex-opsec__summary">'
            . '<p class="corex-admin__eyebrow">' . esc_html__('AT A GLANCE', 'corex') . '</p>'
            . '<h2>' . esc_html__('Operations summary', 'corex') . '</h2>'
            . $this->environmentConflictNotice()
            . '<ul class="corex-opsec__summary-list">' . $items . '</ul>'
            . '</section>';
    }

    /**
     * What a visitor is getting right now, in one line.
     *
     * This row used to be "Maintenance: Off", which answered a question about one mode. With two
     * modes that close the site (spec 101) the question an operator is actually asking here is
     * whether the public can see it, and that has three answers.
     */
    private function publicSiteSummary(string $mode): string
    {
        return match ($mode) {
            OperationsMode::MAINTENANCE => __('Visitors see the maintenance page', 'corex'),
            OperationsMode::COMING_SOON => __('Visitors see the coming-soon page', 'corex'),
            default                     => __('Open to visitors', 'corex'),
        };
    }

    private function sectionUrl(string $section): string
    {
        return add_query_arg(
            ['page' => 'corex-operations-security', 'tab' => $section],
            admin_url('admin.php'),
        );
    }

    /** What the host and configuration declare, which CoreX reads and never writes. */
    private function environmentType(): string
    {
        return function_exists('wp_get_environment_type')
            ? (string) wp_get_environment_type()
            : '';
    }

    /**
     * Said out loud when the declared mode and the WordPress environment disagree (FR-014).
     *
     * Only when the mode was actually **declared**: a site that has merely inherited its mode from
     * `wp_get_environment_type()` cannot conflict with it, and warning there would be noise on
     * every fresh install.
     *
     * The wording is careful not to imply CoreX can change the environment. It cannot, and saying
     * otherwise would invite someone to try to fix a hosting setting from this screen.
     */
    private function environmentConflictNotice(): string
    {
        $environment = $this->environmentType();
        $mode        = $this->store->current();

        if (! $this->store->isDeclared() || $environment === '' || $environment === $mode) {
            return '';
        }

        return $this->page->state(
            'warning',
            __('Environment and mode differ', 'corex'),
            sprintf(
                /* translators: 1: WordPress environment type, 2: declared CoreX operations mode. */
                __('WordPress reports this as a %1$s environment, while CoreX is set to %2$s. Both are shown because they mean different things: the environment is declared by your hosting or wp-config.php, and the CoreX mode is what you have told CoreX about how this site should behave. Changing the mode here does not change the environment.', 'corex'),
                $environment,
                $mode,
            ),
        );
    }

    /** A PRG success/error notice after a mode change (read-only query args; no state change here). */
    private function statusNotice(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status display after a PRG redirect.
        $status = isset($_GET['corex_status']) ? sanitize_key(wp_unslash($_GET['corex_status'])) : '';
        if ($status === '') {
            return '';
        }

        if (str_starts_with($status, 'preview_')) {
            return $this->previewLinkNotice(substr($status, strlen('preview_')));
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
     * What happened to the preview link, after an action that made no new one. A new link is not
     * reported here: it is shown once by {@see PreviewLinkController}, on the response that made it.
     */
    private function previewLinkNotice(string $status): string
    {
        [$tone, $message] = match ($status) {
            PreviewLinkResult::REVOKED => ['success', __('Preview link revoked. Anybody who was using it now sees the coming-soon page.', 'corex')],
            PreviewLinkResult::EXISTS  => ['info', __('A preview link already exists, and it was left as it is. Regenerate it to replace it.', 'corex')],
            PreviewLinkResult::NONE    => ['info', __('There is no preview link to change.', 'corex')],
            PreviewLinkResult::NOT_COMING_SOON => ['warning', __('A preview link exists only while the site is in Coming soon.', 'corex')],
            PreviewLinkResult::INVALID => ['error', __('That is not something that can be done to the preview link.', 'corex')],
            default                    => ['', ''],
        };

        return $message === '' ? '' : $this->page->state($tone, __('Preview link', 'corex'), $message);
    }

    /**
     * The preview link (spec 101, US4): whether one exists, when it was made and by whom, and the
     * controls for it. Present only while the site is in Coming soon, because a link exists only
     * then.
     *
     * The link itself is never here. What is stored cannot be turned back into it (FR-013), so
     * the card says that one exists and offers to replace it; the address is shown once, by
     * {@see PreviewLinkController}, when it is made.
     */
    private function previewLinkCard(): string
    {
        if ($this->store->current() !== OperationsMode::COMING_SOON) {
            return '';
        }

        $issued = $this->preview->issued();

        if ($issued === null) {
            $state    = '<p class="corex-opsec__detail">' . esc_html__('No preview link exists.', 'corex') . '</p>';
            $controls = $this->previewLinkForm(PreviewLinkService::CREATE, __('Create preview link', 'corex'), true);
        } else {
            $user = $issued['user'] > 0 ? get_userdata($issued['user']) : false;
            $when = $this->dateTime->format($issued['time'], AdminDateTime::FULL, __('Time not recorded', 'corex'));

            $state = '<p class="corex-opsec__detail">' . esc_html__('A preview link exists.', 'corex') . ' '
                . esc_html__('Created by', 'corex') . ' '
                . esc_html($user ? $user->display_name : __('system', 'corex')) . ' · ' . $when->toHtml() . '</p>'
                . '<p class="corex-opsec__detail">'
                . esc_html__('The link was shown once, when it was made, and cannot be shown again. If it is lost, regenerate it: the old one stops working.', 'corex')
                . '</p>';
            $controls = $this->previewLinkForm(PreviewLinkService::REGENERATE, __('Regenerate link', 'corex'), true)
                . $this->previewLinkForm(PreviewLinkService::REVOKE, __('Revoke link', 'corex'), false);
        }

        return '<section class="corex-surface corex-opsec__preview">'
            . '<p class="corex-admin__eyebrow">' . esc_html__('PREVIEW LINK', 'corex') . '</p>'
            . '<h2>' . esc_html__('Show the site to somebody without an account', 'corex') . '</h2>'
            . '<p class="corex-opsec__detail">'
            . esc_html__('One secret link. Whoever opens it sees the real site in that browser for 14 days, with a banner saying it is a private preview. It gives no access to the admin, and it stops working when you regenerate or revoke it, or when the site leaves Coming soon.', 'corex')
            . '</p>'
            . $state
            . '<div class="corex-opsec__preview-actions">' . $controls . '</div>'
            . '</section>';
    }

    /**
     * One button, as its own form: each action on the link is a separate, nonce-gated POST.
     */
    private function previewLinkForm(string $operation, string $label, bool $primary): string
    {
        return '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'
            . '<input type="hidden" name="action" value="' . esc_attr(PreviewLinkController::ACTION) . '" />'
            . '<input type="hidden" name="' . esc_attr(PreviewLinkController::OPERATION) . '" value="' . esc_attr($operation) . '" />'
            . wp_nonce_field(PreviewLinkController::ACTION, PreviewLinkController::NONCE, true, false)
            . '<button type="submit" class="button' . ($primary ? ' button-primary' : '') . '">'
            . esc_html($label) . '</button>'
            . '</form>';
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

        // The mode the form is offering is the one selected, not the one the site is in. They
        // differ when a submission came back for a missing confirmation: the block and the
        // confirmation on screen are the proposed mode's, and the value submitted has to be too.
        // A script used to move the select on load, which left a window — and the whole of the
        // no-script path — in which ticking the box and submitting applied the wrong mode.
        $proposed = $this->proposedMode($current);

        $options = '';
        foreach ($this->modes->all() as $mode) {
            $meta = $this->modes->describe($mode);
            $options .= sprintf(
                '<option value="%1$s"%2$s>%3$s</option>',
                esc_attr($mode),
                selected($mode, $proposed, false),
                esc_html($meta['label']),
            );
        }

        $inherited = $declared ? '' :
            '<p class="corex-opsec__detail">' . esc_html__('Inherited from the WordPress environment type — declare a mode to override it.', 'corex') . '</p>';

        // Nothing is being proposed while the selection is the mode the site has declared. The
        // panel says so in one line and asks for nothing: it used to draw that mode's consequences
        // and its confirmation beside a disabled button, a confirmation of a change that is not one.
        //
        // Declared, not merely current. A site that inherits its mode from the WordPress
        // environment type has not stated one, and choosing that same mode states it — a change
        // the store records (see OperationsModeStore::set()), so the form has to offer it.
        $unchanged = $declared && $proposed === $current;

        return '<section class="corex-surface corex-opsec__env is-' . esc_attr($env['tone']) . '">'
            . '<p class="corex-admin__eyebrow">' . esc_html__('OPERATIONS MODE', 'corex') . '</p>'
            . '<div class="corex-opsec__mode-now"><h2>' . esc_html($env['label']) . '</h2>'
            . '<span class="corex-opsec__mode-pill">' . esc_html__('Current mode', 'corex') . '</span></div>'
            // The one sentence about what this mode does. The summary and consequences below
            // belong to a mode being proposed, and are never drawn for this one as well.
            . '<p class="corex-opsec__detail">' . esc_html($env['detail']) . '</p>' . $inherited
            . $this->cautions($current)
            . '<form class="corex-opsec__mode-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '"'
            . ' data-corex-mode-form data-current-mode="' . esc_attr($current) . '"'
            . ' data-mode-declared="' . ($declared ? '1' : '0') . '">'
            . '<input type="hidden" name="action" value="' . esc_attr(OperationsModeController::ACTION) . '" />'
            . wp_nonce_field(OperationsModeController::ACTION, OperationsModeController::NONCE, true, false)
            . '<div class="corex-opsec__mode-field">'
            . '<label class="corex-opsec__mode-label" for="corex-mode-select">' . esc_html__('Change mode', 'corex') . '</label>'
            // Both hooks: `data-corex-select` keeps the approved CoreX control (DECISIONS #141),
            // and `data-corex-mode-select` is what the disclosure binds to. They compose because
            // the upgrade keeps the native <select> as the submitted value and dispatches `change`
            // on it — so a listener bound here still hears the custom control's selections.
            . '<select id="corex-mode-select" name="corex_mode" data-corex-select data-corex-mode-select>'
            . $options . '</select></div>'
            . '<p class="corex-opsec__detail corex-opsec__mode-same" data-corex-mode-same' . ($unchanged ? '' : ' hidden') . '>'
            . esc_html__('This is the mode the site is in. Choose another to see what changing to it would do.', 'corex') . '</p>'
            . $this->modeBlocks($proposed, $current, $declared, $blockers)
            // Enabled as rendered: without the script this button is the only way to propose a
            // mode at all. The script disables it while the selection is the current mode.
            . '<button type="submit" class="button button-primary" data-corex-mode-apply>'
            . esc_html__('Apply mode', 'corex') . '</button>'
            . '</form></section>';
    }

    /**
     * What the current mode leaves exposed or still asks of the operator — the warnings its one
     * sentence does not already make. Drawn as cautions, apart from the informational lines.
     */
    private function cautions(string $mode): string
    {
        $items = '';
        foreach ($this->modes->cautions($mode) as $caution) {
            // The icon and the tone say "caution" to somebody looking. The hidden word says it to
            // somebody listening, who would otherwise hear one more sentence in a list.
            $items .= '<li><span class="dashicons dashicons-warning" aria-hidden="true"></span>'
                . '<span><span class="screen-reader-text">' . esc_html__('Caution:', 'corex') . ' </span>'
                . esc_html($caution) . '</span></li>';
        }

        return $items === '' ? '' : '<ul class="corex-opsec__cautions">' . $items . '</ul>';
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
     * All of them are rendered and all but one hidden, rather than fetching the right one over REST when
     * the selection changes. A fetch would add a route, a spinner and a failure mode to a form that
     * has none of those, to save markup nobody is paying for.
     *
     * The inputs in hidden blocks are `disabled`, which is the part that matters: a disabled control
     * is not submitted, so the server can never receive a confirmation belonging to a mode the
     * operator did not choose. Hiding alone would still post the field.
     *
     * A block is a proposal, so a declared mode's own block is never the visible one: selecting the
     * mode the site has declared proposes nothing, and there is nothing to describe or confirm. An
     * inherited mode's block is offered, as the declaration it would be.
     *
     * No `<div>` inside a block. The form is one column, and the tests that read a block out of the
     * page stop at the first closing one.
     *
     * @param list<string> $blockers Readiness blockers, for the production block.
     */
    private function modeBlocks(string $proposed, string $current, bool $declared, array $blockers): string
    {
        $blocks = '';

        foreach ($this->disclosure->describeAll() as $described) {
            $mode     = $described['mode'];
            $isCurrent = $mode === $current;
            $isActive  = $mode === $proposed && ! ($declared && $isCurrent);
            $inert    = $isActive ? '' : ' disabled';

            $label   = $this->modes->describe($mode)['label'];
            $heading = $isCurrent
                /* translators: %s: the name of the operations mode the site already follows, e.g. "Production". */
                ? sprintf(__('Declaring %s', 'corex'), $label)
                /* translators: %s: the name of an operations mode, e.g. "Coming soon". */
                : sprintf(__('Switching to %s', 'corex'), $label);

            $blocks .= '<div class="corex-opsec__mode-block" data-mode="' . esc_attr($mode) . '"'
                . ($isActive ? '' : ' hidden') . '>'
                . '<h3 class="corex-opsec__mode-heading">' . esc_html($heading) . '</h3>'
                . '<p class="corex-opsec__detail">' . esc_html($described['summary']) . '</p>'
                . $this->bulletList($described['consequences'])
                . $this->moreAboutMode($described['reference'])
                . $this->confirmationControl($described['confirmation'], $mode, $blockers, $inert)
                . '</div>';
        }

        return $blocks;
    }

    /**
     * How the mode works and how to leave it: true and worth having, and not what has to be read
     * before every change. Closed until asked for.
     *
     * @param list<string> $reference
     */
    private function moreAboutMode(array $reference): string
    {
        if ($reference === []) {
            return '';
        }

        return '<details class="corex-opsec__mode-more"><summary>'
            . esc_html__('More about this mode', 'corex') . '</summary>'
            . $this->bulletList($reference) . '</details>';
    }

    /**
     * @param list<string> $lines
     */
    private function bulletList(array $lines): string
    {
        $items = '';
        foreach ($lines as $line) {
            $items .= '<li>' . esc_html($line) . '</li>';
        }

        return $items === '' ? '' : '<ul class="corex-opsec__mode-consequences">' . $items . '</ul>';
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
            // One field name for both modes that ask for it: only the proposed mode's block is
            // enabled, so only its checkbox is ever submitted. The sentence is the mode's own —
            // an acknowledgement that names the wrong consequence acknowledges nothing.
            $sentence = $mode === OperationsMode::COMING_SOON
                ? __('I understand visitors will see the coming-soon page instead of the site.', 'corex')
                : __('I understand maintenance affects real visitors.', 'corex');

            return '<label class="corex-opsec__mode-confirm">'
                . '<input type="checkbox" name="corex_confirm" value="1"' . $inert . ' /> '
                . esc_html($sentence) . '</label>';
        }

        return '';
    }

    /**
     * The mode-change audit log (spec 065): the real recent history from {@see OperationsModeStore},
     * or an honest empty state. No fabricated activity.
     *
     * Since spec 101 it also lists what was done to the preview link, as rows of their own: who
     * created, regenerated or revoked it, and when. Never the link.
     */
    private function auditCard(): string
    {
        $history = $this->store->timeline(8);

        if ($history === []) {
            return '<section class="corex-surface corex-opsec__audit">'
                . '<header class="corex-opsec__checks-head"><h2>' . esc_html__('Mode and preview link history', 'corex') . '</h2></header>'
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

            $what = $entry['event'] !== ''
                ? esc_html($this->eventLabel($entry['event']))
                : '<code>' . esc_html($entry['from']) . '</code> &rarr; <code>' . esc_html($entry['to']) . '</code>';

            $rows .= '<li class="corex-opsec__audit-row">'
                . '<span class="corex-opsec__audit-change">' . $what . '</span>'
                . '<span class="corex-opsec__audit-meta">' . esc_html($who) . ' · '
                . $when->toHtml() . '</span></li>';
        }

        return '<section class="corex-surface corex-opsec__audit">'
            . '<header class="corex-opsec__checks-head"><h2>' . esc_html__('Mode and preview link history', 'corex') . '</h2></header>'
            . '<ul class="corex-opsec__audit-list">' . $rows . '</ul></section>';
    }

    /** The operator's words for an event the store records under a fixed name. */
    private function eventLabel(string $event): string
    {
        return match ($event) {
            OperationsModeStore::EVENT_PREVIEW_CREATED     => __('Preview link created', 'corex'),
            OperationsModeStore::EVENT_PREVIEW_REGENERATED => __('Preview link regenerated', 'corex'),
            OperationsModeStore::EVENT_PREVIEW_REVOKED     => __('Preview link revoked', 'corex'),
            default                                        => $event,
        };
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
