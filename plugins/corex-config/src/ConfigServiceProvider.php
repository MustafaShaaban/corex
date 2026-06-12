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
use Corex\Config\Insights\InsightRegistry;
use Corex\Config\Insights\InsightStore;
use Corex\Config\Insights\InsightsController;
use Corex\Config\Insights\InsightsScreen;
use Corex\Config\Insights\Normalizers\CloudflareNormalizer;
use Corex\Config\Insights\Normalizers\PsiNormalizer;
use Corex\Config\Insights\Providers\PerformanceProvider;
use Corex\Config\Insights\Providers\ReadinessProvider;
use Corex\Config\Insights\ReadinessScorer;
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

        // Insights: the provider registry (Performance over PSI + Readiness over native signals
        // and an optional Cloudflare scan), the cache, the REST controller, and the screen (spec
        // 037). Secrets come from config (settings/.env) and are never returned in a response.
        $this->container->singleton(InsightStore::class);
        $this->container->singleton(InsightRegistry::class, function (ContainerInterface $c): InsightRegistry {
            $config   = $c->make(ConfigInterface::class);
            $registry = new InsightRegistry();
            $registry->register(new PerformanceProvider(
                new PsiNormalizer(),
                (string) $config->get('insights.psi.key', ''),
            ));
            $registry->register(new ReadinessProvider(
                new ReadinessScorer(),
                new CloudflareNormalizer(),
                (string) $config->get('insights.cloudflare.token', ''),
                (string) $config->get('insights.cloudflare.account_id', ''),
            ));

            return $registry;
        });
        $this->container->singleton(InsightsController::class, static fn (ContainerInterface $c): InsightsController =>
            new InsightsController($c->make(InsightRegistry::class), $c->make(InsightStore::class)));
        $this->container->singleton(InsightsScreen::class);
    }

    public function boot(): void
    {
        $this->container->make(AdminBranding::class)->register();
        $this->container->make(AdminDashboard::class)->register();
        $this->container->make(AddonsScreen::class)->register();
        $this->container->make(DataAdminScreen::class)->register();
        $this->container->make(InsightsScreen::class)->register();

        add_action('rest_api_init', function (): void {
            $this->container->make(DataController::class)->register();
            $this->container->make(InsightsController::class)->register();
        });
    }
}
