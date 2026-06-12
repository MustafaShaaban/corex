<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config;

defined('ABSPATH') || exit;

use Corex\Config\Addons\AddonsScreen;
use Corex\Config\Branding\AdminBranding;
use Corex\Config\Branding\BrandingService;
use Corex\Config\Data\DataAdminScreen;
use Corex\Config\Data\DataController;
use Corex\Config\Data\DataRegistry;
use Corex\Config\Data\SubmissionsSource;
use Corex\Config\Data\WpSubmissionsReader;
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
        $this->container->singleton(AddonsScreen::class);

        // Data screen: a registry seeded with the submissions source, the REST controller,
        // and the React screen (spec 030).
        $this->container->singleton(DataRegistry::class, function (ContainerInterface $c): DataRegistry {
            $registry = new DataRegistry();
            $registry->register(new SubmissionsSource(new WpSubmissionsReader()));

            return $registry;
        });
        $this->container->singleton(DataController::class);
        $this->container->singleton(DataAdminScreen::class);
    }

    public function boot(): void
    {
        $this->container->make(AdminBranding::class)->register();
        $this->container->make(AdminDashboard::class)->register();
        $this->container->make(AddonsScreen::class)->register();
        $this->container->make(DataAdminScreen::class)->register();

        add_action('rest_api_init', function (): void {
            $this->container->make(DataController::class)->register();
        });
    }
}
