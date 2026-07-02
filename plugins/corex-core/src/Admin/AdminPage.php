<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Admin;

defined('ABSPATH') || exit;

/**
 * Shared, presentation-only markup for CoreX-owned wp-admin pages.
 */
final class AdminPage
{
    public function open(string $section, string $title, string $description = ''): string
    {
        $descriptionHtml = $description === ''
            ? ''
            : '<p class="corex-admin__description">' . esc_html($description) . '</p>';

        // Appearance (System/Light/Dark) is a CoreX setting surfaced through a filter so this
        // presentation class stays decoupled from the config layer. 'system' adds no attribute
        // and falls back to prefers-color-scheme; light/dark pin the theme explicitly.
        $appearance = (string) apply_filters('corex_admin_appearance', 'system');
        $themeAttr = in_array($appearance, ['light', 'dark'], true)
            ? ' data-corex-theme="' . esc_attr($appearance) . '"'
            : '';

        return sprintf(
            '<div class="wrap corex-admin corex-admin--%1$s"' . $themeAttr . '><div class="corex-admin__shell">%2$s'
            . '<main class="corex-admin__main" aria-labelledby="corex-page-title">'
            . '<header class="corex-admin__header"><div class="corex-admin__heading">'
            . '<p class="corex-admin__eyebrow">%3$s</p><h1 id="corex-page-title">%4$s</h1>%5$s'
            . '</div></header><div class="corex-admin__content">',
            esc_attr($section),
            $this->rail($section),
            esc_html($this->breadcrumb($section)),
            esc_html($title),
            $descriptionHtml,
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
            'corex-data'                => ['data', 'data'],
            'corex-data-models'         => ['data', 'data-models'],
            'corex-operations-security' => ['security', 'operations-security'],
            'corex-access'              => ['access', 'access'],
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
            'corex-data'                => __('Data', 'corex'),
            'corex-data-models'         => __('Data Models', 'corex'),
            'corex-operations-security' => __('Operations & Security', 'corex'),
            'corex-access'              => __('Access & Abilities', 'corex'),
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
            'data' => __('Data', 'corex'),
            'settings' => __('Settings', 'corex'),
            'insights' => __('Insights', 'corex'),
            'setup' => __('Setup', 'corex'),
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

    public function permissionDenied(string $section): string
    {
        return $this->open(
            $section,
            __('Permission denied', 'corex'),
            __('This CoreX screen requires site-administration access.', 'corex'),
        ) . $this->state(
            'permission-denied',
            __('Permission denied', 'corex'),
            __('Ask a site administrator for the manage_options capability.', 'corex'),
        ) . $this->close();
    }
}
