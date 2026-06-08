<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex;

defined('ABSPATH') || exit;

use Corex\Foundation\Application;
use Corex\Foundation\CoreServiceProvider;
use Corex\Foundation\DataServiceProvider;
use RuntimeException;

/**
 * The framework entry point. Called once from corex-core.php; hooks the
 * bootstrap onto `plugins_loaded` so Corex self-initializes in every context
 * (front-end, admin, REST, WP-CLI, cron) independent of any theme (spec FR-001–003).
 */
final class Boot
{
    private static bool $booted = false;

    private static ?Application $app = null;

    public static function init(): void
    {
        add_action('plugins_loaded', [self::class, 'boot']);
    }

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;

        $debug = defined('WP_DEBUG') && WP_DEBUG;

        // Core service providers; modules and add-ons contribute their own (US2+).
        self::$app = new Application($debug, providers: [CoreServiceProvider::class, DataServiceProvider::class]);
        self::$app->boot();
    }

    public static function app(): Application
    {
        if (self::$app === null) {
            throw new RuntimeException('Corex has not booted yet; Boot::init() runs on plugins_loaded.');
        }

        return self::$app;
    }
}
