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
        // Secondary defence: block the default endpoint via the login lifecycle too.
        add_action('login_init', [$this, 'maybeBlockDefaultEndpoint'], 0);

        if (! $this->settings->enabled || $this->settings->customSlug === '') {
            return;
        }

        // The single early handler (on wp_loaded, before wp-admin's auth_redirect and before the
        // request is parsed, exactly like WPS Hide Login): it serves the custom slug and 404s the
        // default endpoints for logged-out visitors so /wp-admin and /wp-login.php are hidden rather
        // than redirected to (and thereby revealing) the custom login. A rewrite rule cannot be used:
        // WordPress resolves rules to index.php query vars, never to wp-login.php, so the slug 404s.
        add_action('wp_loaded', [$this, 'handleRequest'], 1);
        add_filter('login_url', [$this, 'filterLoginUrl'], 20, 1);
        add_filter('site_url', [$this, 'filterSiteUrl'], 20, 2);
        add_filter('network_site_url', [$this, 'filterSiteUrl'], 20, 2);
    }

    /**
     * Serve the custom slug, then hide the default endpoints from unauthenticated visitors.
     *
     * wp-login.php shares state across its code paths through variables it treats as global
     * ($user_login, $error, $action, $interim_login); because it is included from inside this method
     * they must be declared global here or PHP reports them as undefined. $pagenow is set so
     * conditional tags and hooks resolve to the login screen.
     */
    public function handleRequest(): void
    {
        $requestUri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        $path = $this->normalizedPath($requestUri);
        $slug = trim($this->settings->customSlug, '/');

        // 1. The custom slug hands off to the real core login handler.
        if ($slug !== '' && trim($path, '/') === $slug) {
            global $pagenow, $error, $interim_login, $action, $user_login;
            $pagenow = 'wp-login.php';
            require_once ABSPATH . 'wp-login.php';
            exit;
        }

        // 2. Hide the default endpoints so /wp-admin and /wp-login.php 404 for logged-out visitors
        //    instead of redirecting to the custom login and revealing it.
        $script = strtolower(basename((string) (parse_url($requestUri, PHP_URL_PATH) ?: '')));
        if ($this->hidesDefaultEndpoint(
            $path,
            $script,
            is_user_logged_in(),
            is_admin(),
            function_exists('wp_doing_ajax') && wp_doing_ajax(),
        )) {
            $this->deny(404);
        }
    }

    /**
     * Whether this request to a default login/admin endpoint must be hidden (404'd). Pure, so the
     * hiding rules are unit-testable. Recovery bypass, logged-in users, AJAX, admin-ajax.php, and
     * admin-post.php are never hidden; the custom slug is handled before this is reached.
     */
    public function hidesDefaultEndpoint(
        string $path,
        string $script,
        bool $loggedIn,
        bool $isAdmin,
        bool $ajax,
    ): bool {
        if (! $this->settings->enabled || ! $this->settings->blockDefaultEndpoints || $this->unguarded()) {
            return false;
        }
        if ($loggedIn || $ajax || in_array($script, ['admin-ajax.php', 'admin-post.php'], true)) {
            return false;
        }

        return str_contains($path, 'wp-login.php') || $isAdmin;
    }

    private function deny(int $statusCode): void
    {
        nocache_headers();
        status_header($statusCode);
        wp_die(
            esc_html__('Not found.', 'corex'),
            esc_html__('Not found', 'corex'),
            ['response' => $statusCode],
        );
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
