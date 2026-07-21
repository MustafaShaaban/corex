<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Admin;

defined('ABSPATH') || exit;

use Corex\Access\CorexAbility;

/**
 * Shared, presentation-only markup for CoreX-owned wp-admin pages.
 */
final class AdminPage
{
    public function open(string $section, string $title, string $description = '', string $crumbSuffix = ''): string
    {
        $descriptionHtml = $description === ''
            ? ''
            : '<p class="corex-admin__description">' . esc_html($description) . '</p>';

        $breadcrumb = $this->breadcrumb($section)
            . ($crumbSuffix === '' ? '' : ' / ' . $crumbSuffix);

        // Appearance (System/Light/Dark) is a CoreX setting surfaced through a filter so this
        // presentation class stays decoupled from the config layer. 'system' adds no attribute
        // and falls back to prefers-color-scheme; light/dark pin the theme explicitly.
        $appearance = (string) apply_filters('corex_admin_appearance', 'system');
        $themeAttr = in_array($appearance, ['light', 'dark'], true)
            ? ' data-corex-theme="' . esc_attr($appearance) . '"'
            : '';

        // Shell header actions (e.g. the notification bell) are contributed as trusted CoreX HTML —
        // each contributor escapes its own dynamic content, exactly like the WordPress admin bar.
        $actions = (string) apply_filters('corex_admin_header_actions', '');
        $actionsHtml = $actions === '' ? '' : '<div class="corex-admin__header-actions">' . $actions . '</div>';

        return sprintf(
            '<div class="wrap corex-admin corex-admin--%1$s"' . $themeAttr . '><div class="corex-admin__shell">%2$s'
            . '<main class="corex-admin__main" aria-labelledby="corex-page-title">'
            . '<header class="corex-admin__header"><div class="corex-admin__heading">'
            . '<p class="corex-admin__eyebrow">%3$s</p><h1 id="corex-page-title">%4$s</h1>%5$s'
            . '</div>%6$s</header><div class="corex-admin__content">',
            esc_attr($section),
            $this->rail($section),
            esc_html($breadcrumb),
            esc_html($title),
            $descriptionHtml,
            $actionsHtml,
        );
    }

    public function close(): string
    {
        return '</div></main></div></div>';
    }

    /**
     * The scoped CoreX product rail shown inside the shell (design: admin captures): a
     * `COREX FRAMEWORK` group with the four primary destinations, each with a real SVG
     * icon and an explicit active state. It is CoreX-owned chrome inside the framed
     * surface — the generic wp-admin menu outside it is never restyled.
     */
    private function rail(string $active): string
    {
        $nav = '';
        foreach ($this->railItems($active) as [$slug, $icon, $label, $isActive]) {
            $nav .= sprintf(
                '<a class="corex-admin__nav-item%1$s" href="%2$s"%3$s>'
                . '<span class="corex-admin__nav-icon corex-admin__nav-icon--%4$s" aria-hidden="true"></span>%5$s</a>',
                $isActive ? ' is-active' : '',
                esc_url(admin_url('admin.php?page=' . $slug)),
                $isActive ? ' aria-current="page"' : '',
                esc_attr($icon),
                esc_html($label),
            );
        }

        return '<aside class="corex-admin__rail">'
            . '<div class="corex-admin__brand">' . $this->mark() . '<span>Corex</span></div>'
            . '<p class="corex-admin__rail-group">' . esc_html__('COREX FRAMEWORK', 'corex') . '</p>'
            . '<nav class="corex-admin__nav" aria-label="' . esc_attr__('CoreX framework', 'corex') . '">'
            . $nav . '</nav></aside>';
    }

    /**
     * The rail entries, built from the live CoreX submenu (`$submenu['corex-settings']`) so the
     * inner rail can never disagree with the WordPress submenu — every registered CoreX page
     * (including Insights and the gated Setup Wizard, and any declarative option page) appears,
     * in the same order, with its matching icon. Falls back to the known core pages when no admin
     * menu context exists (e.g. unit tests).
     *
     * @return list<array{0:string,1:string,2:string,3:bool}> [slug, icon, label, isActive]
     */
    private function railItems(string $active): array
    {
        // slug => [icon mask, section key matching open()'s $active]. Every registered CoreX screen
        // (including the Spec 063 screens) has a distinct icon and a correct active section — no generic
        // option-page fallback for a real screen, and no dead entry point (spec 064).
        $meta = [
            'corex-settings'            => ['overview', 'overview'],
            'corex-addons'              => ['addons', 'addons'],
            'corex-forms'               => ['forms', 'forms'],
            'corex-submissions'         => ['submissions', 'submissions'],
            'corex-email-studio'        => ['mail', 'email'],
            // Spec 069: the standalone Data screen was retired — it rendered the same explorer as
            // this screen's Records tab — and its address redirects here.
            'corex-data-models'         => ['data', 'data-models'],
            'corex-operations-security' => ['security', 'operations-security'],
            'corex-access'              => ['access', 'access'],
            'corex-blog-pro'            => ['blog', 'blog-pro'],
            'corex-insights'            => ['insights', 'insights'],
            'corex-setup'               => ['setup', 'setup'],
            'corex-settings-config'     => ['settings', 'settings'],
        ];

        $items = [];
        global $submenu;

        if (isset($submenu['corex-settings']) && is_array($submenu['corex-settings'])) {
            foreach ($submenu['corex-settings'] as $entry) {
                $slug  = (string) ($entry[2] ?? '');
                $label = wp_strip_all_tags((string) ($entry[0] ?? ''));
                [$icon, $section] = $meta[$slug] ?? ['option-page', 'option-page'];
                $items[] = [$slug, $icon, $label, $section === $active];
            }

            return $items;
        }

        $labels = [
            'corex-settings'            => __('Overview', 'corex'),
            'corex-addons'              => __('Add-ons', 'corex'),
            'corex-forms'               => __('Forms & Flows', 'corex'),
            'corex-submissions'         => __('Submissions', 'corex'),
            'corex-email-studio'        => __('Email Studio', 'corex'),
            'corex-data-models'         => __('Data', 'corex'),
            'corex-operations-security' => __('Operations & Security', 'corex'),
            'corex-access'              => __('Access & Abilities', 'corex'),
            'corex-blog-pro'            => __('Blog Pro', 'corex'),
            'corex-insights'            => __('Insights', 'corex'),
            'corex-setup'               => __('Setup Wizard', 'corex'),
            'corex-settings-config'     => __('Settings', 'corex'),
        ];

        foreach ($meta as $slug => [$icon, $section]) {
            $items[] = [$slug, $icon, $labels[$slug], $section === $active];
        }

        return $items;
    }

    /**
     * The mono breadcrumb kicker shown in the topbar, e.g. "Corex / Overview".
     */
    private function breadcrumb(string $section): string
    {
        $labels = [
            'overview' => __('Overview', 'corex'),
            'addons' => __('Add-ons', 'corex'),
            'data-models' => __('Data', 'corex'),
            'forms' => __('Forms & Flows', 'corex'),
            'submissions' => __('Submissions', 'corex'),
            'email' => __('Email Studio', 'corex'),
            'operations-security' => __('Operations & Security', 'corex'),
            'access' => __('Access & Abilities', 'corex'),
            'blog-pro' => __('Blog Pro', 'corex'),
            'settings' => __('Settings', 'corex'),
            'insights' => __('Insights', 'corex'),
            'setup' => __('Setup Wizard', 'corex'),
            'option-page' => __('Options', 'corex'),
        ];

        return 'Corex / ' . ($labels[$section] ?? __('Framework', 'corex'));
    }

    /**
     * The five-square CoreX mark, inline so it inherits the rail text colour and the brass
     * core token. Kept as a tiny semantic UI glyph; the exported logo masters are real files.
     */
    private function mark(): string
    {
        return '<svg viewBox="0 0 48 48" fill="none" aria-hidden="true" focusable="false">'
            . '<rect x="3" y="3" width="12" height="12" rx="2.5" fill="currentColor"/>'
            . '<rect x="33" y="3" width="12" height="12" rx="2.5" fill="currentColor"/>'
            . '<rect x="18" y="18" width="12" height="12" rx="2.5" fill="var(--corex-admin-action)"/>'
            . '<rect x="3" y="33" width="12" height="12" rx="2.5" fill="currentColor"/>'
            . '<rect x="33" y="33" width="12" height="12" rx="2.5" fill="currentColor"/></svg>';
    }

    public function state(string $tone, string $title, string $message): string
    {
        $role = in_array($tone, ['error', 'permission-denied'], true) ? 'alert' : 'status';
        $icon = match ($tone) {
            'success' => 'yes-alt',
            'warning' => 'warning',
            'error', 'permission-denied' => 'dismiss',
            'loading' => 'update',
            default => 'info-outline',
        };

        return sprintf(
            '<section class="corex-state corex-state--%1$s" role="%2$s">'
            . '<span class="dashicons dashicons-%3$s" aria-hidden="true"></span>'
            . '<div><h2>%4$s</h2><p>%5$s</p></div></section>',
            esc_attr($tone),
            esc_attr($role),
            esc_attr($icon),
            esc_html($title),
            esc_html($message),
        );
    }

    /**
     * A tokenized CoreX tab strip (no browser-default links). Each tab links to the same page with a
     * `tab` query arg; the active tab carries `is-active` + `aria-current`. Shared by every multi-tab
     * CoreX screen so tabs look and behave identically.
     *
     * @param array<string,string> $tabs   key => label, in display order
     */
    public function tabs(string $pageSlug, array $tabs, string $active, string $label = 'Sections'): string
    {
        $items = '';
        foreach ($tabs as $key => $tabLabel) {
            $isActive = $key === $active;
            $items .= sprintf(
                '<a class="corex-admin__tab%1$s" href="%2$s"%3$s>%4$s</a>',
                $isActive ? ' is-active' : '',
                esc_url(add_query_arg(['page' => $pageSlug, 'tab' => $key], admin_url('admin.php'))),
                $isActive ? ' aria-current="page"' : '',
                esc_html($tabLabel),
            );
        }

        return '<nav class="corex-admin__tabs" aria-label="' . esc_attr($label) . '">' . $items . '</nav>';
    }

    public function permissionDenied(string $section): string
    {
        /**
         * A CoreX screen refused the current user (in-page defense-in-depth; the menu-level
         * capability gate normally refuses first). The access audit log records this event.
         *
         * @param string $section The refused screen's section key.
         */
        do_action('corex_admin_access_denied', $section);

        return $this->open(
            $section,
            __('Access denied', 'corex'),
            __('This CoreX screen requires site-administration access.', 'corex'),
        ) . $this->deniedSurface($section) . $this->close();
    }

    /**
     * The designed denied surface framed as an explicit preview — what the Access screen's
     * "Access denied" tab embeds for administrators. Never fires the audit event: the viewer
     * has access; nothing was denied.
     */
    public function deniedPreview(string $section = 'access'): string
    {
        return '<section class="corex-denied corex-denied--preview">'
            . '<p class="corex-denied__preview">'
            . esc_html__('Preview — this is the state a user without access sees.', 'corex') . '</p>'
            . $this->deniedBody($section)
            . '</section>';
    }

    /**
     * The designed access-denied surface (design: "Corex Access & Abilities" → Access denied):
     * lock mark, the missing capability named precisely, a real way back, and a real request-access
     * form that submits to the CoreX access request workflow.
     * Rendered for real by {@see permissionDenied()}.
     */
    public function deniedSurface(string $section = 'access'): string
    {
        return '<section class="corex-denied">' . $this->deniedBody($section) . '</section>';
    }

    private function deniedBody(string $section): string
    {
        $ability = $this->requestAbilityFor($section);

        return '<span class="corex-denied__icon" aria-hidden="true">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" '
            . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
            . '<rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg></span>'
            . '<h2 class="corex-denied__title">' . esc_html__('You don’t have access to this area', 'corex') . '</h2>'
            . '<p class="corex-denied__text">'
            . sprintf(
                /* translators: %s: the required WordPress capability. */
                esc_html__('Your role doesn’t include the %s capability CoreX requires. Ask a site administrator to grant it through a roles plugin if you need this screen.', 'corex'),
                '<code>manage_options</code>',
            )
            . '</p><div class="corex-denied__actions">'
            . '<a class="button button-primary" href="' . esc_url(admin_url()) . '">'
            . esc_html__('Back to Dashboard', 'corex') . '</a>'
            . '</div><form class="corex-denied__request" method="post" action="'
            . esc_url(rest_url('corex/v1/access/requests')) . '">'
            . '<input type="hidden" name="_wpnonce" value="' . esc_attr(wp_create_nonce('wp_rest')) . '" />'
            . '<input type="hidden" name="ability" value="' . esc_attr($ability) . '" />'
            . '<label for="corex-denied-request-reason">' . esc_html__('Why do you need access?', 'corex') . '</label>'
            . '<textarea id="corex-denied-request-reason" name="reason" rows="3" maxlength="2000" required>'
            . '</textarea><button type="submit" class="button">'
            . esc_html__('Request access', 'corex') . '</button></form>'
            . '<p class="corex-denied__reason">'
            . esc_html__('Your request is tracked in CoreX Access & Abilities so an administrator can approve or deny it.', 'corex')
            . '</p><p class="corex-denied__meta">'
            . esc_html__('Denied attempts are recorded in the access audit log.', 'corex')
            . '</p>';
    }

    private function requestAbilityFor(string $section): string
    {
        return [
            'overview'                  => CorexAbility::MANAGE_ADMIN,
            'corex-settings'            => CorexAbility::MANAGE_ADMIN,
            'addons'                    => CorexAbility::MANAGE_ADMIN,
            'corex-addons'              => CorexAbility::MANAGE_ADMIN,
            'forms'                     => CorexAbility::MANAGE_FORMS,
            'corex-forms'               => CorexAbility::MANAGE_FORMS,
            'submissions'               => CorexAbility::MANAGE_SUBMISSIONS,
            'corex-submissions'         => CorexAbility::MANAGE_SUBMISSIONS,
            'email'                     => CorexAbility::MANAGE_EMAIL,
            'corex-email-studio'        => CorexAbility::MANAGE_EMAIL,
            // Records browsing needs MANAGE_DATA and everything else on the screen needs
            // MANAGE_DATA_MODELS; either one opens it. Someone seeing the denied surface holds
            // neither, and records is the common need, so that is what they are offered.
            'data-models'               => CorexAbility::MANAGE_DATA,
            'corex-data-models'         => CorexAbility::MANAGE_DATA,
            'operations-security'       => CorexAbility::MANAGE_OPERATIONS,
            'corex-operations-security' => CorexAbility::MANAGE_OPERATIONS,
            'notifications'             => CorexAbility::MANAGE_NOTIFICATIONS,
            'corex-notifications'       => CorexAbility::MANAGE_NOTIFICATIONS,
            'access'                    => CorexAbility::MANAGE_ACCESS,
            'corex-access'              => CorexAbility::MANAGE_ACCESS,
            'blog-pro'                  => CorexAbility::MANAGE_BLOG,
            'corex-blog-pro'            => CorexAbility::MANAGE_BLOG,
            'settings'                  => CorexAbility::MANAGE_SETTINGS,
            'corex-settings-config'     => CorexAbility::MANAGE_SETTINGS,
            'setup'                     => CorexAbility::MANAGE_SETUP,
            'corex-setup'               => CorexAbility::MANAGE_SETUP,
        ][$section] ?? CorexAbility::MANAGE_ADMIN;
    }
}
