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
        $items = [
            ['overview', 'corex-settings', __('Overview', 'corex')],
            ['addons', 'corex-addons', __('Add-ons', 'corex')],
            ['data', 'corex-data', __('Data', 'corex')],
            ['settings', 'corex-settings-config', __('Settings', 'corex')],
        ];

        $nav = '';
        foreach ($items as [$key, $slug, $label]) {
            $isActive = $key === $active;
            $nav .= sprintf(
                '<a class="corex-admin__nav-item%1$s" href="%2$s"%3$s>'
                . '<span class="corex-admin__nav-icon corex-admin__nav-icon--%4$s" aria-hidden="true"></span>%5$s</a>',
                $isActive ? ' is-active' : '',
                esc_url(admin_url('admin.php?page=' . $slug)),
                $isActive ? ' aria-current="page"' : '',
                esc_attr($key),
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
