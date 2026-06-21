<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Foundation;

defined('ABSPATH') || exit;

use Corex\Container\ContainerInterface;
use Corex\Http\EnvelopeResponder;

/**
 * Registers the shared HTTP contract (spec 043): the {@see EnvelopeResponder} service
 * and the buildless `corex-runtime` script/style. The runtime is only **registered**
 * here — never enqueued globally; each surface that needs it (the form viewScript, the
 * Insights + Data admin screens) declares `corex-runtime` as a dependency, so it loads
 * exactly where a Corex form/screen renders and nowhere else (Principle VI).
 */
final class HttpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(
            EnvelopeResponder::class,
            static fn (ContainerInterface $container): EnvelopeResponder => new EnvelopeResponder(),
        );
    }

    public function boot(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'registerAssets']);
        add_action('admin_enqueue_scripts', [$this, 'registerAssets']);
        add_action('login_enqueue_scripts', [$this, 'registerAssets'], 5);
    }

    public function registerAssets(): void
    {
        $assets = plugins_url('assets', COREX_CORE_FILE);

        // Depends on wp-i18n only (lightweight). wp.apiFetch is feature-detected at runtime —
        // not a hard dependency — so a front-end form page never pulls in wp-api-fetch.
        wp_register_script(
            'corex-runtime',
            $assets . '/js/corex-runtime.js',
            ['wp-i18n'],
            $this->assetVersion('js/corex-runtime.js'),
            true,
        );
        wp_set_script_translations('corex-runtime', 'corex');

        wp_register_style(
            'corex-runtime',
            $assets . '/css/corex-runtime.css',
            [],
            $this->assetVersion('css/corex-runtime.css'),
        );

        // The scoped CoreX admin token adapter (spec 057 US4). Registered only —
        // each CoreX admin screen style declares `corex-admin-tokens` as a dependency,
        // so it loads on CoreX screens and never globally (Principle VI).
        wp_register_style(
            'corex-admin-tokens',
            $assets . '/css/corex-admin-tokens.css',
            [],
            $this->assetVersion('css/corex-admin-tokens.css'),
        );

        wp_register_style(
            'corex-admin-shell',
            $assets . '/css/corex-admin-shell.css',
            ['corex-admin-tokens'],
            $this->assetVersion('css/corex-admin-shell.css'),
        );

        wp_register_style(
            'corex-admin-login',
            $assets . '/css/corex-admin-login.css',
            ['corex-admin-tokens'],
            $this->assetVersion('css/corex-admin-login.css'),
        );

        // Presentation-only login enhancement (wraps the username field for its leading icon);
        // WordPress still owns all authentication behaviour.
        wp_register_script(
            'corex-admin-login',
            $assets . '/js/corex-login.js',
            [],
            $this->assetVersion('js/corex-login.js'),
            true,
        );
    }

    /**
     * Cache-busting version for a corex-core source asset: its filemtime, so any edit busts the
     * browser cache even between releases (these are hand-authored source CSS/JS, not built/
     * hashed bundles, and the framework version only changes on a release). Falls back to the
     * framework version when the file is unreadable. See DECISIONS — login/shell cache-bust.
     */
    private function assetVersion(string $relativePath): string
    {
        $path  = dirname(COREX_CORE_FILE) . '/assets/' . ltrim($relativePath, '/');
        $mtime = is_file($path) ? filemtime($path) : false;

        return $mtime !== false ? (string) $mtime : COREX_CORE_VERSION;
    }
}
