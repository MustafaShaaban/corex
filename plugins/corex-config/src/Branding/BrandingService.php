<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Branding;

defined('ABSPATH') || exit;

use Corex\Support\Config\ConfigInterface;

/**
 * Resolves Corex's product branding: the logo URL (config `brand.logo_url` override →
 * the bundled default) and the login-logo CSS. Pure — it produces strings; the WP
 * hooks live in AdminBranding. This is the Corex *product* brand (admin/login), never
 * a client site's look.
 */
final class BrandingService
{
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly string $defaultLogoUrl,
    ) {
    }

    public function logoUrl(): string
    {
        return (string) ($this->config->get('brand.logo_url') ?: $this->defaultLogoUrl);
    }

    public function loginCss(string $logoUrl): string
    {
        return sprintf(
            'body.login.corex-login{--corex-admin-login-logo:url("%s")}',
            $logoUrl
        );
    }

    public function configuredFooterText(): string
    {
        return (string) $this->config->get('brand.footer_text', '');
    }

    public function configuredLoginUrl(): string
    {
        return (string) $this->config->get('brand.login_url', '');
    }
}
