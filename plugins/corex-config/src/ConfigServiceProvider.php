<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config;

defined('ABSPATH') || exit;

use Corex\Config\Branding\AdminBranding;
use Corex\Config\Branding\BrandingService;
use Corex\Config\Settings\AdminDashboard;
use Corex\Container\ContainerInterface;
use Corex\Foundation\ServiceProvider;
use Corex\Support\Config\ConfigInterface;

/**
 * The corex-config provider: Corex's product branding (and, later, the settings UI).
 * Registers the admin-branding hooks on boot.
 */
final class ConfigServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(
            BrandingService::class,
            static fn (ContainerInterface $c): BrandingService => new BrandingService(
                $c->make(ConfigInterface::class),
                plugins_url('assets/corex-logo.svg', dirname(__DIR__) . '/corex-config.php'),
            ),
        );

        $this->container->singleton(AdminBranding::class);
        $this->container->singleton(AdminDashboard::class);
    }

    public function boot(): void
    {
        $this->container->make(AdminBranding::class)->register();
        $this->container->make(AdminDashboard::class)->register();
    }
}
