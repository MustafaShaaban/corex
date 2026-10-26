<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Access;

use Corex\Access\AbilityNames;
use Corex\Access\CorexAbility;
use Corex\Access\CorexAbilityCatalog;
use Corex\Access\RoleAbilityStore;
use Corex\Access\ScreenAbilities;
use Corex\Admin\AdminPage;
use Corex\Config\AdminUi\ScreenAsset;
use Corex\Multisite\PluginActivationInspector;
use Corex\Security\Admin\AdminGuard;
use Corex\Support\Facades\AdminDate;

defined('ABSPATH') || exit;

/**
 * The Access & Abilities screen (spec 065 baseline, spec 067 tabs, spec 117): Overview · Role matrix ·
 * Audit log · Access denied.
 *
 * Every CoreX screen asks for its own CoreX ability (spec 117), so this screen describes that model:
 * the overview counts CoreX abilities per role as the matrix resolves them, lists them by area, says how
 * the signed-in person holds theirs and which screen needs which; the matrix tab is a grid of resolved
 * states with a per-role editor ({@see AccessWorkspace.js}); the audit tab is one history of access
 * events ({@see AccessHistory}); the denied tab previews the page a refused person gets. It opens for
 * "Manage access".
 */
final class AccessScreen
{
    private const SLUG = 'corex-access';

    private string $hook = '';

    public function __construct(
        private readonly AdminGuard $guard,
        private readonly AdminPage $page,
        private readonly AccessMatrix $matrix,
        private readonly AccessHistory $history,
        private readonly RoleAbilityStore $roleAbilities,
        private readonly PendingAccessRequests $pending,
        private readonly PluginActivationInspector $pluginActivationInspector,
        private readonly AccessGrantPresenter $grants,
        private readonly CorexAbilityCatalog $catalog,
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
            CorexAbility::MANAGE_ACCESS,
            self::SLUG,
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
            // Who is confirming a role change: the server checks the confirmation against it.
            'actorId' => get_current_user_id(),
            'matrix' => $this->matrix->editableCorexMatrix($this->roles(), $this->roleEffects(), $this->activePlugins()),
            'requests' => $this->pending->all(),
            // The grants made by approval that stand (spec 112, FR-005).
            'grants' => $this->grants->grants(),
        ]);
        wp_set_script_translations('corex-access', 'corex');
    }

    public function render(): void
    {
        if (! $this->guard->authorized(CorexAbility::MANAGE_ACCESS)) {
            echo $this->page->permissionDenied('access');

            return;
        }

        $active = $this->activeTab();

        echo $this->page->open(
            'access',
            __('CoreX Access & Abilities', 'corex'),
            __('Who can use each CoreX screen: the CoreX abilities each role holds, the grants made to people, and every access event.', 'corex'),
            $this->tabsList()[$active],
        );

        echo $this->page->tabs(self::SLUG, $this->tabsList(), $active, __('Access sections', 'corex'));

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
     * Overview tab (US-ACC-03): the role plugin notice, people waiting, any Deny kept but not applied,
     * the role cards, the abilities by area, the screens and what they need, and your own abilities.
     */
    private function overviewTab(): string
    {
        $roles   = $this->roles();
        $effects = $this->roleEffects();

        return $this->conflictNotice()
            . $this->waitingCard()
            . $this->ignoredDeniesCard($this->matrix->ignoredDenies($roles, $effects))
            . $this->roleCards($this->matrix->roleSummaries($roles, $this->userCounts(), $effects))
            . $this->groupsCard()
            . $this->requirementsCard()
            . $this->currentUserCard($effects)
            . $this->deferralNote();
    }

    /**
     * People waiting, on the tab an administrator actually lands on (spec 079).
     *
     * The panel that decides requests lives inside Role matrix, so without this a pending request
     * is two clicks away and nothing says it is there. Renders nothing when nobody is waiting.
     */
    private function waitingCard(): string
    {
        $waiting = count($this->pending->all());
        if ($waiting === 0) {
            return '';
        }

        return '<section class="corex-surface corex-access__waiting">'
            . '<h2 class="corex-access__waiting-title">'
            . esc_html(sprintf(
                /* translators: %s: how many people are waiting for access. */
                _n('%s person is waiting for access', '%s people are waiting for access', $waiting, 'corex'),
                number_format_i18n($waiting),
            ))
            . '</h2><p class="corex-access__muted">'
            . esc_html__('Each request names who asked, what they asked for and why. Approving one asks for a note and an expiry, then grants the ability until it ends.', 'corex')
            . '</p><a class="button button-primary" href="'
            . esc_url(admin_url('admin.php?page=corex-access&tab=matrix')) . '">'
            . esc_html__('Review requests', 'corex') . '</a></section>';
    }

    /**
     * Each Deny stored for a role that administers the site, listed once with why it does nothing
     * (spec 117, FR-011). Renders nothing when there is none.
     *
     * @param list<array{role:string,ability:string}> $ignored
     */
    private function ignoredDeniesCard(array $ignored): string
    {
        if ($ignored === []) {
            return '';
        }

        $rows = '';
        foreach ($ignored as $deny) {
            $rows .= '<li>' . esc_html(sprintf(
                /* translators: 1: a role's name, 2: the name of a CoreX ability. */
                __('%1$s: Deny of “%2$s”', 'corex'),
                $deny['role'],
                $deny['ability'],
            )) . '</li>';
        }

        return '<section class="corex-surface corex-access__card corex-access__ignored" role="note">'
            . '<header class="corex-access__head"><h2>' . esc_html__('Stored but not applied', 'corex') . '</h2></header>'
            . '<p class="corex-access__muted">'
            . esc_html__('These were set in the role matrix before every CoreX screen checked its ability. Site administrators always hold every CoreX ability, so they are kept and ignored: nobody who administers the site loses anything.', 'corex')
            . '</p><ul class="corex-access__reqs">' . $rows . '</ul></section>';
    }

    /**
     * Role matrix tab (US-ACC-02): the five states, the grid with its per-role editor, the requests
     * and grants, and why a site administrator cannot be locked out.
     */
    private function matrixTab(): string
    {
        return $this->legend()
            . '<div id="corex-access-app"></div>'
            . $this->lockoutNote();
    }

    /**
     * Audit log tab (US-ACC-04): every access event, newest first, by day, 25 a page, filtered by kind
     * and by person.
     */
    private function auditTab(): string
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters and paging.
        $kind   = isset($_GET['kind']) ? sanitize_key(wp_unslash($_GET['kind'])) : '';
        $person = isset($_GET['person']) ? absint(wp_unslash($_GET['person'])) : 0;
        $page   = isset($_GET['paged']) ? absint(wp_unslash($_GET['paged'])) : 1;
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        $kinds = $this->history->kinds();
        $kind  = array_key_exists($kind, $kinds) ? $kind : '';
        $found = $this->history->page($kind, $person, $page);

        $head = '<header class="corex-access__head corex-access__head--split">'
            . '<h2>' . esc_html__('Access audit log', 'corex') . '</h2>'
            . '<span class="corex-access__muted">' . esc_html(sprintf(
                /* translators: %s: how many entries match. */
                _n('%s entry', '%s entries', $found['total'], 'corex'),
                number_format_i18n($found['total']),
            )) . '</span></header>';

        if ($found['total'] === 0 && $kind === '' && $person < 1) {
            return '<section class="corex-surface corex-access__card">' . $head . $this->auditEmpty() . '</section>';
        }

        return '<section class="corex-surface corex-access__card">' . $head
            . $this->auditFilters($kinds, $found['people'], $kind, $person)
            . ($found['entries'] === []
                ? '<p class="corex-access__muted">' . esc_html__('Nothing matches these filters.', 'corex') . '</p>'
                : $this->auditDays($found['entries']))
            . $this->auditPager($found['page'], $found['pages'], $kind, $person)
            . '</section>';
    }

    /** What the history records and for how long (FR-022). */
    private function auditEmpty(): string
    {
        return '<p class="corex-access__muted">'
            . esc_html__('Nothing has been recorded yet. CoreX lists here: role changes and refused ones, access requests and their approval, refusal, cancellation or expiry, grants revoked or ended, and refused visits to CoreX screens and actions.', 'corex')
            . '</p><p class="corex-access__muted">'
            . esc_html(sprintf(
                /* translators: 1: days role changes, requests and grants are kept, 2: days refusals are kept. */
                __('Role changes, requests and grants are kept for %1$s days; refusals for %2$s days.', 'corex'),
                number_format_i18n(AccessHistory::ACTIVITY_DAYS),
                number_format_i18n(AccessHistory::REFUSAL_DAYS),
            ))
            . '</p>';
    }

    /**
     * @param array<string,string> $kinds
     * @param array<int,string>    $people
     */
    private function auditFilters(array $kinds, array $people, string $kind, int $person): string
    {
        $kindOptions = '<option value="">' . esc_html__('Every kind', 'corex') . '</option>';
        foreach ($kinds as $key => $label) {
            $kindOptions .= '<option value="' . esc_attr($key) . '"' . selected($kind, $key, false) . '>' . esc_html($label) . '</option>';
        }

        $personOptions = '<option value="0">' . esc_html__('Everyone', 'corex') . '</option>';
        foreach ($people as $id => $name) {
            $personOptions .= '<option value="' . esc_attr((string) $id) . '"' . selected($person, $id, false) . '>' . esc_html($name) . '</option>';
        }

        return '<form class="corex-access__filters" method="get" action="' . esc_url(admin_url('admin.php')) . '">'
            . '<input type="hidden" name="page" value="' . esc_attr(self::SLUG) . '" />'
            . '<input type="hidden" name="tab" value="audit" />'
            . '<label for="corex-audit-kind">' . esc_html__('Kind', 'corex') . '</label>'
            . '<select id="corex-audit-kind" name="kind" data-corex-select>' . $kindOptions . '</select>'
            . '<label for="corex-audit-person">' . esc_html__('Person', 'corex') . '</label>'
            . '<select id="corex-audit-person" name="person" data-corex-select>' . $personOptions . '</select>'
            . '<button type="submit" class="button">' . esc_html__('Filter', 'corex') . '</button></form>';
    }

    /**
     * The entries under a heading for each day.
     *
     * @param list<array{at:int,first:int,count:int,kind:string,kindLabel:string,who:string,whoId:int,subjectId:int,text:string}> $entries
     */
    private function auditDays(array $entries): string
    {
        $html = '';
        $day  = '';
        foreach ($entries as $entry) {
            $date = AdminDate::date($entry['at'])->human;
            if ($date !== $day) {
                $html .= ($day === '' ? '' : '</ul>') . '<h3 class="corex-access__day">' . esc_html($date) . '</h3><ul class="corex-access__audit">';
                $day   = $date;
            }
            $html .= $this->auditRow($entry);
        }

        return $html . '</ul>';
    }

    /**
     * @param array{at:int,first:int,count:int,kind:string,kindLabel:string,who:string,whoId:int,subjectId:int,text:string} $entry
     */
    private function auditRow(array $entry): string
    {
        $last = AdminDate::full($entry['at'])->toHtml('corex-access__event-when');
        $when = $entry['count'] === 1
            ? $last
            : sprintf(
                /* translators: 1: how many times, 2: the first time, 3: the last time. */
                esc_html(_n('%1$s time, from %2$s to %3$s', '%1$s times, from %2$s to %3$s', $entry['count'], 'corex')),
                esc_html(number_format_i18n($entry['count'])),
                AdminDate::full($entry['first'])->toHtml('corex-access__event-when'),
                $last,
            );
        $tone = in_array($entry['kind'], ['refused', 'role_refused', 'denied'], true) ? ' is-warning' : '';

        return '<li class="corex-access__event">'
            . '<span class="corex-access__event-dot" aria-hidden="true"></span>'
            . '<span class="corex-access__event-body"><span class="corex-access__event-text">'
            . '<strong>' . esc_html($entry['who']) . '</strong> ' . esc_html($entry['text']) . '</span>'
            . '<span class="corex-access__event-meta">' . $when . '</span></span>'
            . '<span class="corex-access__chip' . $tone . '">' . esc_html($entry['kindLabel']) . '</span></li>';
    }

    private function auditPager(int $page, int $pages, string $kind, int $person): string
    {
        if ($pages < 2) {
            return '';
        }

        $link = static fn (int $to): string => esc_url(add_query_arg(
            array_filter(['page' => self::SLUG, 'tab' => 'audit', 'kind' => $kind, 'person' => $person ?: null, 'paged' => $to]),
            admin_url('admin.php'),
        ));

        return '<nav class="corex-access__pager" aria-label="' . esc_attr__('Audit log pages', 'corex') . '">'
            . ($page > 1 ? '<a class="button" href="' . $link($page - 1) . '">' . esc_html__('Newer', 'corex') . '</a>' : '')
            . '<span class="corex-access__muted">' . esc_html(sprintf(
                /* translators: 1: this page, 2: how many pages. */
                __('Page %1$s of %2$s', 'corex'),
                number_format_i18n($page),
                number_format_i18n($pages),
            )) . '</span>'
            . ($page < $pages ? '<a class="button" href="' . $link($page + 1) . '">' . esc_html__('Older', 'corex') . '</a>' : '')
            . '</nav>';
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
            . esc_html__('A signed-in person without the CoreX ability a screen needs who opens it is refused with HTTP 403, sees this page with a way to ask for the ability, and the attempt is counted in the audit log.', 'corex')
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
                esc_html__('CoreX detected %s. CoreX manages only its own abilities here; WordPress capabilities stay with that plugin.', 'corex'),
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
            $originLabel = $summary['isCore'] ? __('WordPress', 'corex') : __('Added by a plugin or the site', 'corex');
            $cards .= '<article class="corex-surface corex-access__role">'
                . '<header class="corex-access__role-head"><h3>' . esc_html($summary['name']) . '</h3>'
                . '<span class="corex-access__origin' . ($summary['isCore'] ? '' : ' is-custom') . '">'
                . esc_html($originLabel) . '</span></header>'
                . '<p class="corex-access__role-users">' . esc_html(sprintf(
                    /* translators: %s: number of users in the role. */
                    _n('%s user', '%s users', $summary['users'], 'corex'),
                    number_format_i18n($summary['users']),
                )) . '</p>'
                . '<p class="corex-access__role-granted"><strong>' . esc_html(number_format_i18n($summary['granted']))
                . '</strong><span>' . esc_html(sprintf(
                    /* translators: %s: how many CoreX abilities there are. */
                    _n('/ %s CoreX ability', '/ %s CoreX abilities', $summary['total'], 'corex'),
                    number_format_i18n($summary['total']),
                )) . '</span></p></article>';
        }

        return '<div class="corex-access__roles">' . $cards . '</div>';
    }

    /**
     * Every CoreX ability by area, with its risk in words and whether it is locked (US-ACC-03).
     */
    private function groupsCard(): string
    {
        $count = 0;
        $areas = '';
        foreach ($this->matrix->abilityGroups() as $area => $abilities) {
            $rows = '';
            foreach ($abilities as $ability) {
                $count++;
                $high   = in_array($ability['risk'], [CorexAbility::RISK_CRITICAL, CorexAbility::RISK_DANGEROUS], true);
                $locked = $ability['locked']
                    ? '<span class="corex-access__chip is-locked">' . esc_html__('Locked', 'corex') . '</span>'
                    : '';
                $rows .= '<li class="corex-access__group' . ($high ? ' is-high' : '') . '">'
                    . '<span class="corex-access__group-dot" aria-hidden="true"></span>'
                    . '<span class="corex-access__group-label" title="' . esc_attr($ability['key']) . '"><strong>'
                    . esc_html($ability['label']) . '</strong> · ' . esc_html($ability['description']) . '</span>'
                    . '<span class="corex-access__group-chips">' . $locked
                    . '<span class="corex-access__chip' . ($high ? ' is-danger' : '') . '">' . esc_html($ability['riskLabel']) . '</span>'
                    . '</span></li>';
            }
            $areas .= '<h3 class="corex-access__area">' . esc_html($area) . '</h3><ul class="corex-access__groups">' . $rows . '</ul>';
        }

        return '<section class="corex-surface corex-access__card">'
            . '<header class="corex-access__head corex-access__head--split">'
            . '<h2>' . esc_html__('CoreX abilities', 'corex') . '</h2>'
            . '<span class="corex-access__muted">' . esc_html(sprintf(
                /* translators: %s: how many CoreX abilities there are. */
                _n('%s ability, by area', '%s abilities, by area', $count, 'corex'),
                number_format_i18n($count),
            )) . '</span></header>' . $areas . '</section>';
    }

    private function legend(): string
    {
        $items = '';
        foreach (self::states() as $state => [$mark, $label]) {
            $items .= '<span class="corex-access__legend-item"><span class="corex-access__mark is-' . esc_attr($state)
                . '" aria-hidden="true">' . $mark . '</span>' . esc_html($label) . '</span>';
        }

        return '<div class="corex-access__legend" role="note" aria-label="' . esc_attr__('What the cells say', 'corex') . '">' . $items . '</div>';
    }

    /**
     * The five states a cell can be in, with the mark the grid draws for each.
     *
     * @return array<string,array{0:string,1:string}>
     */
    private static function states(): array
    {
        return [
            AccessMatrix::STATE_ALLOWED_SET   => ['&#10003;', __('Allowed (set here)', 'corex')],
            AccessMatrix::STATE_DENIED_SET    => ['&#10005;', __('Denied (set here)', 'corex')],
            AccessMatrix::STATE_ALLOWED_ADMIN => ['&#9733;', __('Allowed through site administration (inherited)', 'corex')],
            AccessMatrix::STATE_NOT_GRANTED   => ['&#8211;', __('Not granted (inherited)', 'corex')],
            AccessMatrix::STATE_LOCKED        => ['&#128274;', __('Locked', 'corex')],
        ];
    }

    private function lockoutNote(): string
    {
        return '<div class="corex-access__note corex-surface">'
            . '<p class="corex-access__note-title">' . esc_html__('Site administrators keep every CoreX ability.', 'corex') . '</p>'
            . '<p class="corex-access__note-text">'
            . esc_html__('Anyone who can administer the site holds every CoreX ability, and the matrix cannot deny a role that does. A change that would leave no administrator with full access, or take a critical ability from you, is refused.', 'corex')
            . '</p></div>';
    }

    /**
     * The CoreX abilities you hold and how (US-ACC-03).
     *
     * @param array<string,array<string,string>> $effects
     */
    private function currentUserCard(array $effects): string
    {
        $user  = wp_get_current_user();
        $roles = array_values(array_filter(
            $this->roles(),
            static fn (array $role): bool => in_array($role['key'], (array) $user->roles, true),
        ));
        $held = [];
        foreach ($this->catalog->all() as $ability) {
            $held[$ability->key] = current_user_can($ability->key);
        }

        $rows = '';
        foreach ($this->matrix->forUser($held, $roles, $effects, $this->ownGrants((int) $user->ID)) as $ability) {
            $rows .= '<li class="corex-access__you is-yes"><span title="' . esc_attr($ability['key']) . '">' . esc_html($ability['label']) . '</span>'
                . '<span class="corex-access__flag">' . $this->howHeld($ability) . '</span></li>';
        }

        return '<section class="corex-surface corex-access__card">'
            . '<header class="corex-access__head"><h2>' . esc_html__('Your permissions', 'corex') . '</h2></header>'
            . ($rows === ''
                ? '<p class="corex-access__muted">' . esc_html__('You hold no CoreX ability.', 'corex') . '</p>'
                : '<ul class="corex-access__you-list">' . $rows . '</ul>')
            . '</section>';
    }

    /** @param array{key:string,label:string,how:string,role:string|null,until:string|null} $ability */
    private function howHeld(array $ability): string
    {
        return match ($ability['how']) {
            AccessMatrix::HELD_SITE_ADMINISTRATION => esc_html__('Site administration', 'corex'),
            AccessMatrix::HELD_ROLE => esc_html(sprintf(
                /* translators: %s: a role's name. */
                __('Your role, %s', 'corex'),
                (string) $ability['role'],
            )),
            AccessMatrix::HELD_GRANT => $ability['until'] !== null
                ? sprintf(
                    /* translators: %s: when the grant ends. */
                    esc_html__('Granted to you until %s', 'corex'),
                    AdminDate::date($ability['until'])->toHtml(),
                )
                : esc_html__('Granted to you', 'corex'),
            default => esc_html__('Your account', 'corex'),
        };
    }

    /**
     * The abilities granted to this person by approval, with when each grant ends.
     *
     * @return array<string,string|null> ability key => end (ISO 8601) or null
     */
    private function ownGrants(int $userId): array
    {
        $own = [];
        foreach ($this->grants->grants() as $grant) {
            if ((int) $grant['person']['id'] !== $userId || ! $grant['in_effect']) {
                continue;
            }

            foreach ($grant['abilities'] as $ability) {
                $own[$ability['key']] = $grant['expires_at'];
            }
        }

        return $own;
    }

    /**
     * Each CoreX screen and the ability it needs (US-ACC-01), with the screens that also open
     * read-only for "See CoreX screens without changing them".
     */
    private function requirementsCard(): string
    {
        $rows = '';
        foreach (ScreenAbilities::all() as $slug => $screen) {
            $needs = AbilityNames::label($screen['ability']);
            if ($slug === 'corex-data-models') {
                $needs = sprintf(
                    /* translators: 1: and 2: the names of two CoreX abilities. */
                    __('%1$s or %2$s', 'corex'),
                    $needs,
                    AbilityNames::label(CorexAbility::MANAGE_DATA_MODELS),
                );
            }
            if ($screen['readOnly']) {
                $needs = sprintf(
                    /* translators: 1: the ability that opens the screen, 2: the ability that opens it read-only. */
                    __('%1$s (read-only with %2$s)', 'corex'),
                    $needs,
                    AbilityNames::label(CorexAbility::VIEW_SCREENS),
                );
            }

            $rows .= '<li><span>' . esc_html(ScreenNames::of($slug)) . '</span><span title="' . esc_attr($screen['ability']) . '">'
                . esc_html($needs) . '</span></li>';
        }
        $rows .= '<li><span>' . esc_html__('Switching to Production or Maintenance', 'corex') . '</span><span>'
            . esc_html(sprintf(
                /* translators: 1: and 2: the names of two CoreX abilities. */
                __('%1$s and %2$s', 'corex'),
                AbilityNames::label(CorexAbility::MANAGE_OPERATIONS),
                AbilityNames::label(CorexAbility::RUN_DANGEROUS_ACTIONS),
            )) . '</span></li>'
            . '<li><span>' . esc_html__('Guides', 'corex') . '</span><span>'
            . esc_html__('Anyone signed in; each guide follows its screen', 'corex') . '</span></li>';

        return '<section class="corex-surface corex-access__card">'
            . '<header class="corex-access__head"><h2>' . esc_html__('Screen access requirements', 'corex') . '</h2></header>'
            . '<p class="corex-access__muted">'
            . esc_html__('Each CoreX screen opens for the people who hold its ability: through their role in the matrix, a grant approved for them, or site administration.', 'corex')
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
        return $this->pluginActivationInspector->activePluginFiles();
    }
}
