<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Branding;

defined('ABSPATH') || exit;

use Closure;
use Corex\Multisite\SiteScoped;
use Corex\Support\Config\ConfigInterface;

/**
 * Resolves Corex's product branding: the logo URL (config `brand.logo_url` override →
 * the bundled default) and the login-logo CSS. Pure — it produces strings; the WP
 * hooks live in AdminBranding. This is the Corex *product* brand (admin/login), never
 * a client site's look.
 *
 * The bundled logo is a pair of fixed-colour lockups, one for a dark ground and one for a
 * light ground (spec 134, FR-020): the login draws the one that suits its card. A logo set
 * in CoreX Settings, Branding, replaces both.
 *
 * The bundled URL is site-scoped because `plugins_url()` derives it from the current
 * site's URL. The provider injects a lazy resolver and registers this singleton with
 * `SiteScope`; switching blogs clears only the memo, so the next `logoUrl()` call reads
 * the new site. Project plugins with site-derived singleton values copy these steps.
 */
final class BrandingService implements SiteScoped
{
    private ?string $defaultLogoUrl = null;

    private ?string $defaultLightLogoUrl = null;

    /**
     * @param Closure():string|string $defaultLogoUrlResolver      The bundled lockup for a dark ground.
     * @param Closure():string|string $defaultLightLogoUrlResolver The bundled lockup for a light ground.
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly Closure|string $defaultLogoUrlResolver,
        private readonly Closure|string $defaultLightLogoUrlResolver,
    ) {
    }

    /** The logo for a dark ground: the site's own logo, or the bundled lockup for a dark ground. */
    public function logoUrl(): string
    {
        return (string) ($this->config->get('brand.logo_url') ?: $this->defaultLogoUrl());
    }

    /** The logo for a light ground: the site's own logo, or the bundled lockup for a light ground. */
    public function lightLogoUrl(): string
    {
        return (string) ($this->config->get('brand.logo_url') ?: $this->defaultLightLogoUrl());
    }

    /** Whether CoreX Settings, Branding, holds the site's own logo, which replaces the lockup. */
    public function hasCustomLogo(): bool
    {
        return (string) $this->config->get('brand.logo_url') !== '';
    }

    public function forgetSiteState(int $siteId): void
    {
        $this->defaultLogoUrl = null;
        $this->defaultLightLogoUrl = null;
    }

    /**
     * The login's logo, for each card it is drawn on (spec 134, FR-020).
     *
     * The lockup for a dark ground is the default; the one for a light ground is taken where the
     * card takes the light colours: on a body pinned Light, and on a light computer when the body
     * is pinned to neither (the same selectors as `corex-admin-tokens.css`). A single logo, or the
     * same one twice (a logo set in Branding), writes no switch.
     *
     * @param string $logoUrl      The logo for a dark ground, as it is stored.
     * @param string $lightLogoUrl The logo for a light ground, as it is stored.
     */
    public function loginCss(string $logoUrl, string $lightLogoUrl): string
    {
        $css = sprintf('body.login.corex-login{--corex-admin-login-logo:url("%s")}', self::cssUrl($logoUrl));

        if ($lightLogoUrl === $logoUrl) {
            return $css;
        }

        $light = sprintf('{--corex-admin-login-logo:url("%s")}', self::cssUrl($lightLogoUrl));

        return $css
            . 'body.login.corex-login.corex-appearance-light' . $light
            . '@media (prefers-color-scheme: light){'
            . 'body.login.corex-login:not(.corex-appearance-light):not(.corex-appearance-dark)' . $light
            . '}';
    }

    public function configuredFooterText(): string
    {
        return (string) $this->config->get('brand.footer_text', '');
    }

    public function configuredLoginUrl(): string
    {
        return (string) $this->config->get('brand.login_url', '');
    }

    /**
     * The chosen CoreX admin appearance: 'system' (default — follows the OS scheme),
     * 'light', or 'dark'. Any unrecognized stored value resolves back to 'system'.
     */
    public function adminAppearance(): string
    {
        $mode = (string) $this->config->get('brand.admin_appearance', 'system');

        return in_array($mode, ['system', 'light', 'dark'], true) ? $mode : 'system';
    }

    private function defaultLogoUrl(): string
    {
        return $this->defaultLogoUrl ??= self::resolve($this->defaultLogoUrlResolver);
    }

    private function defaultLightLogoUrl(): string
    {
        return $this->defaultLightLogoUrl ??= self::resolve($this->defaultLightLogoUrlResolver);
    }

    /**
     * An address escaped for a double-quoted CSS string. `esc_url()` is for HTML: it writes `&` as
     * `&#038;`, which a stylesheet reads literally, so an address with two query parameters broke.
     * The address is cleaned as a URL, then its backslashes, quotes and line breaks are escaped as
     * CSS escapes them.
     */
    private static function cssUrl(string $url): string
    {
        return strtr(esc_url_raw($url), [
            '\\' => '\\\\',
            '"'  => '\\"',
            "\n" => '\\a ',
            "\r" => '\\d ',
            "\f" => '\\c ',
        ]);
    }

    /** @param Closure():string|string $resolver */
    private static function resolve(Closure|string $resolver): string
    {
        return $resolver instanceof Closure ? $resolver() : $resolver;
    }
}
