<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Access;

use Corex\Admin\AdminPage;
use Corex\Access\RoleAbilityStore;
use Corex\Config\AdminUi\ScreenAsset;
use Corex\Security\Admin\AdminGuard;

defined('ABSPATH') || exit;

/**
 * The Access & Abilities screen (spec 065 baseline → spec 067 tabs; design: "Corex Access &
 * Abilities"): tabs Overview · Role matrix · Audit log · Access denied. Everything shown is REAL —
 * roles/capabilities read from WordPress, user counts from count_users(), permissions-plugin conflicts
 * from the active plugins, audit entries from recorded denied attempts ({@see AccessAuditLog}), and
 * the denied tab previews the exact surface a refused user gets. CoreX-owned ability states are editable
 * through the guarded REST workflow; native WordPress/platform capabilities remain compatibility inputs.
 * Access is AdminGuard-gated.
 */
final class AccessScreen
{
    private string $hook = '';

    public function __construct(
        private readonly AdminGuard $guard,
        private readonly AdminPage $page,
        private readonly AccessMatrix $matrix,
        private readonly AccessAuditLog $audit,
        private readonly RoleAbilityStore $roleAbilities,
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
            __('CoreX Access & Abilities', 'corex'),
            __('Access & Abilities', 'corex'),
            'manage_options',
            'corex-access',
            [$this, 'render'],
            32,
        );
    }

    public function maybeEnqueue(string $hook): void
    {
        if ($hook !== $this->hook || $this->hook === '') {
            return;
        }

        wp_enqueue_style(
            'corex-access',
            plugins_url('assets/access.css', COREX_CONFIG_FILE),
            ['corex-admin-shell'],
            ScreenAsset::version(dirname(COREX_CONFIG_FILE) . '/assets/access.css'),
        );
        $base = dirname(__DIR__, 2);
        $asset = is_file($base . '/build/admin/index.asset.php')
            ? require $base . '/build/admin/index.asset.php'
            : ['dependencies' => [], 'version' => 'dev'];
        wp_enqueue_script(
            'corex-access',
            plugins_url('build/admin/index.js', $base . '/corex-config.php'),
            [...$asset['dependencies'], 'corex-runtime'],
            $asset['version'],
            true,
        );
        wp_localize_script('corex-access', 'corexAccess', [
            'restUrl' => esc_url_raw(rest_url('corex/v1/access')),
            'nonce' => wp_create_nonce('wp_rest'),
            'matrix' => $this->matrix->editableCorexMatrix($this->editableRoles(), $this->roleEffects(), $this->activePlugins()),
            'requests' => [],
            'audit' => $this->audit->entries(),
        ]);
        wp_set_script_translations('corex-access', 'corex');
    }

    public function render(): void
    {
        if (! $this->guard->authorized()) {
            echo $this->page->permissionDenied('access');

            return;
        }

        $active = $this->activeTab();

        echo $this->page->open(
            'access',
            __('CoreX Access & Abilities', 'corex'),
            __('Who can access CoreX and manage content on this site — read from the real WordPress roles.', 'corex'),
            $this->tabsList()[$active],
        );

        echo $this->page->tabs('corex-access', $this->tabsList(), $active, __('Access sections', 'corex'));

        echo match ($active) {
            'matrix' => $this->matrixTab(),
            'audit'  => $this->auditTab(),
            'denied' => $this->deniedTab(),
            default  => $this->overviewTab(),
        };

        echo $this->page->close();
    }

    /**
     * @return array<string,string>
     */
    private function tabsList(): array
    {
        return [
            'overview' => __('Overview', 'corex'),
            'matrix'   => __('Role matrix', 'corex'),
            'audit'    => __('Audit log', 'corex'),
            'denied'   => __('Access denied', 'corex'),
        ];
    }

    private function activeTab(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab selection.
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';

        return array_key_exists($tab, $this->tabsList()) ? $tab : 'overview';
    }

    /**
     * Overview tab (design): real permissions-plugin conflict notice, role summary cards, the
     * tracked capability groups with risk labels, plus the existing truthful requirement/permission
     * cards and the CoreX-owned ability editing statement.
     */
    private function overviewTab(): string
    {
        $roles = $this->roles();

        return $this->conflictNotice()
            . $this->roleCards($this->matrix->roleSummaries($roles, $this->userCounts()))
            . $this->groupsCard()
            . $this->requirementsCard()
            . $this->currentUserCard()
            . $this->deferralNote();
    }

    /**
     * Role matrix tab (design): the state legend, the real role × capability matrix, and the
     * no-lockout guarantee.
     */
    private function matrixTab(): string
    {
        return $this->legend()
            . '<div id="corex-access-app"></div>'
            . $this->matrixCard($this->matrix->build($this->roles()))
            . $this->lockoutNote();
    }

    /**
     * Audit log tab (design): the recorded access events of the last 30 days, or an honest empty
     * state naming exactly what gets recorded — CoreX makes no role/capability changes, so no
     * grant/revoke history can exist.
     */
    private function auditTab(): string
    {
        $entries = $this->audit->entries();

        $head = '<header class="corex-access__head corex-access__head--split">'
            . '<div class="corex-access__head-titles"><h2>' . esc_html__('Access audit log', 'corex') . '</h2>'
            . '<span class="corex-access__window">' . esc_html__('LAST 30 DAYS', 'corex') . '</span></div>'
            . '<span class="corex-access__muted">'
            . esc_html__('Records denied CoreX access attempts', 'corex') . '</span></header>';

        if ($entries === []) {
            return '<section class="corex-surface corex-access__card">' . $head
                . '<p class="corex-access__muted">'
                . esc_html__('No access events in the last 30 days. CoreX records permission-denied attempts, access requests, and access decisions here.', 'corex')
                . '</p></section>';
        }

        // Prime the user cache once instead of one lookup per row (the log caps at 100 entries).
        $userIds = array_values(array_unique(array_filter(array_column($entries, 'user'))));
        if ($userIds !== [] && function_exists('cache_users')) {
            cache_users($userIds);
        }

        $rows = '';
        foreach ($entries as $entry) {
            $rows .= $this->auditRow($entry);
        }

        return '<section class="corex-surface corex-access__card">' . $head
            . '<ul class="corex-access__audit">' . $rows . '</ul></section>';
    }

    /**
     * Access denied tab (design): a labelled preview of the real denied surface, plus how the real
     * refusal behaves (menu-level HTTP 403 + audit entry).
     */
    private function deniedTab(): string
    {
        return '<div class="corex-access__preview-note corex-surface">'
            . '<p class="corex-access__note-title">' . esc_html__('How a real refusal behaves', 'corex') . '</p>'
            . '<p class="corex-access__note-text">'
            . esc_html__('A signed-in user without the manage_options capability who opens any CoreX screen is refused at the WordPress menu gate with a real HTTP 403, sees this state, and the attempt is recorded in the audit log tab.', 'corex')
            . '</p></div>'
            . $this->page->deniedPreview();
    }

    /**
     * The compatibility notice — shown ONLY when a known role/capability-manager plugin is really
     * active (design: overview warning).
     */
    private function conflictNotice(): string
    {
        $conflicts = $this->matrix->conflicts($this->activePlugins());
        if ($conflicts === []) {
            return '';
        }

        return '<div class="corex-access__conflict corex-surface" role="status">'
            . '<p class="corex-access__note-title">' . esc_html__('Another permissions plugin is active.', 'corex') . '</p>'
            . '<p class="corex-access__note-text">'
            . sprintf(
                /* translators: %s: the detected role/capability plugin name(s). */
                esc_html__('CoreX detected %s. Native platform capabilities stay read-only; CoreX-owned abilities remain editable here.', 'corex'),
                esc_html(implode(', ', $conflicts)),
            )
            . '</p></div>';
    }

    /**
     * @param list<array{key:string,name:string,isCore:bool,users:int,granted:int,total:int}> $summaries
     */
    private function roleCards(array $summaries): string
    {
        $cards = '';
        foreach ($summaries as $summary) {
            $originLabel = $summary['isCore'] ? __('CORE', 'corex') : __('CUSTOM', 'corex');
            $cards .= '<article class="corex-surface corex-access__role">'
                . '<header class="corex-access__role-head"><h3>' . esc_html($summary['name']) . '</h3>'
                . '<span class="corex-access__origin' . ($summary['isCore'] ? '' : ' is-custom') . '">'
                . esc_html($originLabel) . '</span></header>'
                . '<p class="corex-access__role-users">' . esc_html(sprintf(
                    /* translators: %s: number of users in the role. */
                    _n('%s user', '%s users', $summary['users'], 'corex'),
                    number_format_i18n($summary['users']),
                )) . '</p>'
                . '<p class="corex-access__role-granted"><strong>' . esc_html((string) $summary['granted'])
                . '</strong><span>' . esc_html(sprintf(
                    /* translators: %s: total number of tracked abilities. */
                    __('/ %s tracked abilities', 'corex'),
                    number_format_i18n($summary['total']),
                )) . '</span></p></article>';
        }

        return '<div class="corex-access__roles">' . $cards . '</div>';
    }

    /**
     * The tracked capability groups with their real capability, risk label, and code-lock state.
     */
    private function groupsCard(): string
    {
        $groups = $this->matrix->groups();

        $rows = '';
        foreach ($groups as $group) {
            $risk = $group['risk'] === 'high'
                ? '<span class="corex-access__chip is-danger">' . esc_html__('High risk', 'corex') . '</span>'
                : '<span class="corex-access__chip">' . esc_html__('Standard', 'corex') . '</span>';
            $locked = $group['locked']
                ? '<span class="corex-access__chip is-locked">' . esc_html__('Locked by code', 'corex') . '</span>'
                : '';

            $rows .= '<li class="corex-access__group' . ($group['risk'] === 'high' ? ' is-high' : '') . '">'
                . '<span class="corex-access__group-dot" aria-hidden="true"></span>'
                . '<span class="corex-access__group-label"><code>' . esc_html($group['cap']) . '</code> · '
                . esc_html($group['label']) . '</span>'
                . '<span class="corex-access__group-chips">' . $locked . $risk . '</span></li>';
        }

        return '<section class="corex-surface corex-access__card">'
            . '<header class="corex-access__head corex-access__head--split">'
            . '<h2>' . esc_html__('Tracked capability groups', 'corex') . '</h2>'
            . '<span class="corex-access__muted">' . esc_html(sprintf(
                /* translators: %s: number of tracked abilities. */
                __('%s real WordPress capabilities · CoreX invents none', 'corex'),
                number_format_i18n(count($groups)),
            )) . '</span></header>'
            . '<ul class="corex-access__groups">' . $rows . '</ul></section>';
    }

    private function legend(): string
    {
        return '<div class="corex-access__legend">'
            . '<span class="corex-access__legend-item"><span class="corex-access__mark is-yes" aria-hidden="true">&#10003;</span>'
            . esc_html__('Allowed — the role really holds the capability', 'corex') . '</span>'
            . '<span class="corex-access__legend-item"><span class="corex-access__mark is-no" aria-hidden="true">&#8211;</span>'
            . esc_html__('Not granted', 'corex') . '</span>'
            . '<span class="corex-access__legend-item"><span class="corex-access__mark is-locked" aria-hidden="true">&#128274;</span>'
            . esc_html__('Locked by code — the requirement is hard-coded in CoreX', 'corex') . '</span></div>';
    }

    private function lockoutNote(): string
    {
        return '<div class="corex-access__note corex-surface">'
            . '<p class="corex-access__note-title">' . esc_html__('CoreX cannot lock you out.', 'corex') . '</p>'
            . '<p class="corex-access__note-text">'
            . esc_html__('CoreX admin access is hard-gated in code on manage_options, so locked definitions cannot remove an administrator’s access. Editable states apply only to CoreX-owned abilities.', 'corex')
            . '</p></div>';
    }

    private function currentUserCard(): string
    {
        $rows = '';
        foreach ($this->matrix->forUser($this->currentUserCaps()) as $ability) {
            $rows .= '<li class="corex-access__you is-' . ($ability['granted'] ? 'yes' : 'no') . '">'
                . '<span>' . esc_html($ability['label']) . '</span>'
                . '<span class="corex-access__flag">'
                . esc_html($ability['granted'] ? __('Yes', 'corex') : __('No', 'corex')) . '</span></li>';
        }

        return '<section class="corex-surface corex-access__card">'
            . '<header class="corex-access__head"><h2>' . esc_html__('Your permissions', 'corex') . '</h2></header>'
            . '<ul class="corex-access__you-list">' . $rows . '</ul></section>';
    }

    /**
     * @param array{roles:list<array{key:string,name:string}>,rows:list<array{key:string,label:string,cap:string,risk:string,locked:bool,cells:array<string,bool>}>} $matrix
     */
    private function matrixCard(array $matrix): string
    {
        $head = '<th>' . esc_html__('Capability', 'corex') . '</th>';
        foreach ($matrix['roles'] as $role) {
            $head .= '<th>' . esc_html($role['name']) . '</th>';
        }

        $body = '';
        foreach ($matrix['rows'] as $row) {
            $locked = $row['locked']
                ? ' <span class="corex-access__chip is-locked">' . esc_html__('Locked by code', 'corex') . '</span>'
                : '';
            $cells = '<th scope="row">' . esc_html($row['label']) . $locked
                . '<code class="corex-access__cap">' . esc_html($row['cap']) . '</code></th>';
            foreach ($matrix['roles'] as $role) {
                $granted = ! empty($row['cells'][$role['key']]);
                $cells  .= '<td class="is-' . ($granted ? 'yes' : 'no') . '">'
                    . '<span class="screen-reader-text">'
                    . esc_html($granted ? __('Granted', 'corex') : __('Not granted', 'corex')) . '</span>'
                    . '<span class="corex-access__mark is-' . ($granted ? 'yes' : 'no') . '" aria-hidden="true">'
                    . ($granted ? '&#10003;' : '&#8211;') . '</span></td>';
            }
            $body .= '<tr>' . $cells . '</tr>';
        }

        return '<section class="corex-surface corex-access__card">'
            . '<header class="corex-access__head"><h2>' . esc_html__('Role capability matrix', 'corex') . '</h2></header>'
            . '<div class="corex-access__scroll"><table class="corex-access__matrix"><thead><tr>' . $head
            . '</tr></thead><tbody>' . $body . '</tbody></table></div></section>';
    }

    private function requirementsCard(): string
    {
        $screens = [
            __('All CoreX admin screens', 'corex'),
            __('Change operations mode', 'corex'),
            __('Prune submission data', 'corex'),
            __('Export submissions / data', 'corex'),
        ];

        $rows = '';
        foreach ($screens as $screen) {
            $rows .= '<li><span>' . esc_html($screen) . '</span><code>manage_options</code></li>';
        }

        return '<section class="corex-surface corex-access__card">'
            . '<header class="corex-access__head"><h2>' . esc_html__('Screen access requirements', 'corex') . '</h2></header>'
            . '<p class="corex-access__muted">'
            . esc_html__('CoreX admin and every dangerous action require the WordPress manage_options capability (typically Administrators).', 'corex')
            . '</p><ul class="corex-access__reqs">' . $rows . '</ul></section>';
    }

    private function deferralNote(): string
    {
        return '<div class="corex-access__note corex-surface">'
            . '<p class="corex-access__note-title">' . esc_html__('Editing roles', 'corex') . '</p>'
            . '<p class="corex-access__note-text">'
            . esc_html__(
                'CoreX manages CoreX-owned abilities here. Native WordPress and third-party capability editing remains with WordPress or a dedicated role plugin, preventing conflicting platform rules.',
                'corex',
            )
            . '</p></div>';
    }

    /**
     * @param array{time:int,user:int,kind:string,section:string} $entry
     */
    private function auditRow(array $entry): string
    {
        $who  = $this->userLabel($entry['user']);
        $what = $this->sectionLabel($entry['section']);

        $text = sprintf(
            /* translators: 1: user name, 2: CoreX screen name. */
            __('Access denied: %1$s tried to open %2$s', 'corex'),
            $who,
            $what,
        );

        $when = $entry['time'] > 0
            ? sprintf(
                /* translators: %s: human-readable time difference, e.g. "2 hours". */
                __('%s ago', 'corex'),
                human_time_diff($entry['time'], time()),
            )
            : __('Unknown time', 'corex');

        return '<li class="corex-access__event">'
            . '<span class="corex-access__event-dot" aria-hidden="true"></span>'
            . '<span class="corex-access__event-body"><span class="corex-access__event-text">'
            . esc_html($text) . '</span>'
            . '<span class="corex-access__event-meta">' . esc_html($who . ' · ' . $when) . '</span></span>'
            . '<span class="corex-access__chip is-warning">' . esc_html__('DENIED', 'corex') . '</span></li>';
    }

    private function userLabel(int $userId): string
    {
        if ($userId > 0) {
            $user = get_userdata($userId);
            if ($user !== false && $user->display_name !== '') {
                return $user->display_name;
            }

            return sprintf(
                /* translators: %d: WordPress user ID. */
                __('User #%d', 'corex'),
                $userId,
            );
        }

        return __('Signed-out visitor', 'corex');
    }

    /**
     * A readable name for a recorded section — accepts both the AdminPage section keys and the
     * `corex-*` page slugs recorded by the menu-level gate.
     */
    private function sectionLabel(string $section): string
    {
        $labels = [
            'overview'                  => __('Overview', 'corex'),
            'corex-settings'            => __('Overview', 'corex'),
            'addons'                    => __('Add-ons', 'corex'),
            'corex-addons'              => __('Add-ons', 'corex'),
            'forms'                     => __('Forms & Flows', 'corex'),
            'corex-forms'               => __('Forms & Flows', 'corex'),
            'submissions'               => __('Submissions', 'corex'),
            'corex-submissions'         => __('Submissions', 'corex'),
            'email'                     => __('Email Studio', 'corex'),
            'corex-email-studio'        => __('Email Studio', 'corex'),
            'data'                      => __('Data', 'corex'),
            'corex-data'                => __('Data', 'corex'),
            'data-models'               => __('Data Models', 'corex'),
            'corex-data-models'         => __('Data Models', 'corex'),
            'operations-security'       => __('Operations & Security', 'corex'),
            'corex-operations-security' => __('Operations & Security', 'corex'),
            'access'                    => __('Access & Abilities', 'corex'),
            'corex-access'              => __('Access & Abilities', 'corex'),
            'blog-pro'                  => __('Blog Pro', 'corex'),
            'corex-blog-pro'            => __('Blog Pro', 'corex'),
            'settings'                  => __('Settings', 'corex'),
            'corex-settings-config'     => __('Settings', 'corex'),
            'insights'                  => __('Insights', 'corex'),
            'corex-insights'            => __('Insights', 'corex'),
            'setup'                     => __('Setup Wizard', 'corex'),
            'corex-setup'               => __('Setup Wizard', 'corex'),
        ];

        return $labels[$section] ?? $section;
    }

    /**
     * The real WordPress roles + their capabilities.
     *
     * @return list<array{key:string,name:string,caps:array<string,bool>}>
     */
    private function roles(): array
    {
        $roles = function_exists('wp_roles') ? wp_roles() : null;
        if (! $roles instanceof \WP_Roles) {
            return [];
        }

        $out = [];
        foreach ($roles->roles as $key => $role) {
            $out[] = [
                'key'  => (string) $key,
                'name' => translate_user_role((string) ($role['name'] ?? $key)),
                'caps' => array_map('boolval', (array) ($role['capabilities'] ?? [])),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{key:string,name:string}>
     */
    private function editableRoles(): array
    {
        return array_map(
            static fn (array $role): array => ['key' => $role['key'], 'name' => $role['name']],
            $this->roles(),
        );
    }

    /**
     * @return array<string,array<string,string>>
     */
    private function roleEffects(): array
    {
        $effects = [];
        foreach ($this->roles() as $role) {
            $effects[$role['key']] = $this->roleAbilities->effectsForRole($role['key']);
        }

        return $effects;
    }

    /**
     * The REAL per-role user counts from WordPress.
     *
     * @return array<string,int>
     */
    private function userCounts(): array
    {
        if (! function_exists('count_users')) {
            return [];
        }

        $counts = count_users();

        return array_map('intval', (array) ($counts['avail_roles'] ?? []));
    }

    /**
     * @return list<string>
     */
    private function activePlugins(): array
    {
        $plugins = get_option('active_plugins', []);

        return is_array($plugins) ? array_map('strval', array_values($plugins)) : [];
    }

    /**
     * @return array<string,bool>
     */
    private function currentUserCaps(): array
    {
        $user = wp_get_current_user();

        return array_map('boolval', (array) ($user->allcaps ?? []));
    }
}
