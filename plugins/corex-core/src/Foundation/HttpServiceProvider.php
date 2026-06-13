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
            COREX_CORE_VERSION,
            true,
        );
        wp_set_script_translations('corex-runtime', 'corex');

        wp_register_style(
            'corex-runtime',
            $assets . '/css/corex-runtime.css',
            [],
            COREX_CORE_VERSION,
        );
    }
}
