<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Branding;

defined('ABSPATH') || exit;

/**
 * Applies the Corex product branding in wp-admin: the login-page logo, the login
 * link target, and the admin footer text. The logo URL is escaped; the rest is
 * static, configurable Corex branding (never client-site styling).
 */
final class AdminBranding
{
    public function __construct(private readonly BrandingService $branding)
    {
    }

    public function register(): void
    {
        add_action('login_enqueue_scripts', [$this, 'enqueueLoginAssets'], 20);
        add_filter('login_body_class', [$this, 'loginBodyClass']);
        add_filter('login_headerurl', [$this, 'loginUrl']);
        add_filter('admin_footer_text', [$this, 'footerText']);
        add_filter('corex_admin_appearance', [$this, 'appearance']);
    }

    /**
     * Resolves the CoreX admin appearance (System/Light/Dark) for the shell filter in
     * {@see \Corex\Admin\AdminPage}.
     */
    public function appearance(): string
    {
        return $this->branding->adminAppearance();
    }

    public function enqueueLoginAssets(): void
    {
        wp_enqueue_style('corex-admin-login');
        wp_add_inline_style(
            'corex-admin-login',
            $this->branding->loginCss(esc_url($this->branding->logoUrl())),
        );
    }

    /** @param list<string> $classes
     *  @return list<string>
     */
    public function loginBodyClass(array $classes): array
    {
        $classes[] = 'corex-login';

        $appearance = $this->branding->adminAppearance();
        if ($appearance === 'light' || $appearance === 'dark') {
            $classes[] = 'corex-appearance-' . $appearance;
        }

        return array_values(array_unique($classes));
    }

    public function loginUrl(string $url): string
    {
        return $this->branding->configuredLoginUrl() ?: home_url('/');
    }

    public function footerText(string $text): string
    {
        return esc_html($this->branding->configuredFooterText() ?: __('Powered by Corex', 'corex'));
    }
}
