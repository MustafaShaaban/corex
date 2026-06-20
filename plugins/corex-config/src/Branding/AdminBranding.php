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
        add_action('login_head', [$this, 'loginLogo']);
        add_filter('login_headerurl', [$this, 'loginUrl']);
        add_filter('admin_footer_text', [$this, 'footerText']);
    }

    public function loginLogo(): void
    {
        // The login mark is the approved Core X product lockup (logo-manifest.json),
        // rendered as the WordPress login slot background. WordPress keeps the site
        // name as the link's accessible text, so the mark itself stays decorative.
        $css = $this->branding->loginCss(esc_url($this->branding->logoUrl()));

        printf('<style>%s</style>', $css);
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
