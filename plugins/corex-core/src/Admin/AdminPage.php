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

        return sprintf(
            '<div class="wrap corex-admin corex-admin--%1$s"><main class="corex-admin__main" aria-labelledby="corex-page-title">'
            . '<header class="corex-admin__header"><div class="corex-admin__heading">'
            . '<p class="corex-admin__eyebrow">%2$s</p><h1 id="corex-page-title">%3$s</h1>%4$s'
            . '</div></header><div class="corex-admin__content">',
            esc_attr($section),
            esc_html__('COREX FRAMEWORK', 'corex'),
            esc_html($title),
            $descriptionHtml,
        );
    }

    public function close(): string
    {
        return '</div></main></div>';
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
