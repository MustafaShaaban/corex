<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

/**
 * Protects default login endpoints without moving or renaming WordPress core files.
 */
final readonly class LoginRouteGuard
{
    public function __construct(private LoginProtectionSettings $settings)
    {
    }

    public function register(): void
    {
        // Block direct access to the default endpoint (works via the login lifecycle).
        add_action('login_init', [$this, 'maybeBlockDefaultEndpoint'], 0);

        if (! $this->settings->enabled || $this->settings->customSlug === '') {
            return;
        }

        // Serve the core login handler when the custom slug is requested, and rewrite every
        // WordPress-generated login/logout URL to the custom slug so nothing points at the blocked
        // default endpoint. A rewrite rule cannot be used here: WordPress resolves rules to
        // index.php query vars, never to wp-login.php, so the slug would 404.
        add_action('init', [$this, 'maybeServeCustomLogin'], 1);
        add_filter('login_url', [$this, 'filterLoginUrl'], 20, 1);
        add_filter('site_url', [$this, 'filterSiteUrl'], 20, 2);
        add_filter('network_site_url', [$this, 'filterSiteUrl'], 20, 2);
    }

    /**
     * When the request path is the custom login slug, hand the request to the core login handler
     * exactly as if wp-login.php had been requested — without moving or renaming the core file.
     */
    public function maybeServeCustomLogin(): void
    {
        $requestUri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        $path = trim($this->normalizedPath($requestUri), '/');
        if ($path === '' || $path !== trim($this->settings->customSlug, '/')) {
            return;
        }

        global $pagenow;
        $pagenow = 'wp-login.php';
        require_once ABSPATH . 'wp-login.php';
        exit;
    }

    /** Point WordPress login links at the custom slug instead of the blocked default endpoint. */
    public function filterLoginUrl(string $url): string
    {
        return $this->rewriteLoginUrl($url);
    }

    /**
     * @param string $url
     * @param string $path
     */
    public function filterSiteUrl(string $url, mixed $path = ''): string
    {
        if (is_string($path) && str_contains($path, 'wp-login.php')) {
            return $this->rewriteLoginUrl($url);
        }

        return $url;
    }

    private function rewriteLoginUrl(string $url): string
    {
        return str_replace('wp-login.php', trim($this->settings->customSlug, '/') . '/', $url);
    }

    public function maybeBlockDefaultEndpoint(): void
    {
        $decision = $this->decision(
            (string) ($_SERVER['REQUEST_URI'] ?? '/wp-login.php'),
            function_exists('is_user_logged_in') && is_user_logged_in(),
            $this->unguarded(),
        );

        if (! $decision->blocked) {
            return;
        }

        nocache_headers();
        status_header($decision->statusCode);
        wp_die(
            esc_html__('Not found.', 'corex'),
            esc_html__('Not found', 'corex'),
            ['response' => $decision->statusCode],
        );
    }

    public function decision(string $requestUri, bool $authenticated, bool $unguarded = false): LoginRouteDecision
    {
        if (! $this->settings->enabled || ! $this->settings->blockDefaultEndpoints) {
            return new LoginRouteDecision(false, 200, 'disabled');
        }

        if ($authenticated || $unguarded) {
            return new LoginRouteDecision(false, 200, 'recovery_allowed');
        }

        $path = $this->normalizedPath($requestUri);
        if ($path === $this->customLoginPath() || $path === rtrim($this->customLoginPath(), '/')) {
            return new LoginRouteDecision(false, 200, 'custom_login_allowed');
        }

        if (in_array($path, ['/wp-login.php', '/wp-admin', '/wp-admin/'], true)) {
            return new LoginRouteDecision(true, 404, 'default_endpoint_blocked');
        }

        return new LoginRouteDecision(false, 200, 'unrelated_route');
    }

    public function customLoginPath(): string
    {
        return '/' . trim($this->settings->customSlug, '/') . '/';
    }

    public function movesCoreFiles(): bool
    {
        return false;
    }

    private function normalizedPath(string $requestUri): string
    {
        $path = (string) (parse_url($requestUri, PHP_URL_PATH) ?: '/');
        if ($path === '/wp-admin') {
            return $path;
        }

        return str_ends_with($path, '/') ? $path : $path;
    }

    private function unguarded(): bool
    {
        return defined('COREX_LOGIN_UNGUARD') && COREX_LOGIN_UNGUARD === true;
    }
}
