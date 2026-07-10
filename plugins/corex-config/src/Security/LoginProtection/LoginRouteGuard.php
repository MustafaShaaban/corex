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
        add_action('init', [$this, 'registerRewrite']);
        add_action('login_init', [$this, 'maybeBlockDefaultEndpoint'], 0);
    }

    public function registerRewrite(): void
    {
        if (! $this->settings->enabled || $this->settings->customSlug === '' || ! function_exists('add_rewrite_rule')) {
            return;
        }

        add_rewrite_rule('^' . preg_quote($this->settings->customSlug, '/') . '/?$', 'wp-login.php', 'top');
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
