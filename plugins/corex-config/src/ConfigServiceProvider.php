<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config;

defined('ABSPATH') || exit;

use Corex\Config\Addons\AddonsScreen;
use Corex\Config\Addons\KitActivationNotice;
use Corex\Config\Branding\AdminBranding;
use Corex\Config\Branding\BrandingService;
use Corex\Config\Data\DataAdminScreen;
use Corex\Config\Data\DataController;
use Corex\Config\Data\DataRegistry;
use Corex\Config\Data\SubmissionsReader;
use Corex\Config\Data\SubmissionsSource;
use Corex\Config\Data\TableDataSource;
use Corex\Config\Data\WpSubmissionsReader;
use Corex\Config\Data\WpTableDataReader;
use Corex\Config\Options\OptionPageRegistry;
use Corex\Config\Options\OptionPageScreen;
use Corex\Database\Schema\ManagedTables;
use Corex\Database\Schema\Migrator;
use Corex\Config\Insights\InsightRegistry;
use Corex\Config\Insights\InsightStore;
use Corex\Config\Insights\InsightsController;
use Corex\Config\Insights\InsightsScreen;
use Corex\Config\Insights\Normalizers\CloudflareNormalizer;
use Corex\Config\Insights\Normalizers\PsiNormalizer;
use Corex\Config\Insights\Providers\PerformanceProvider;
use Corex\Config\Insights\Providers\ReadinessProvider;
use Corex\Config\Insights\ReadinessScorer;
use Corex\Config\Insights\SiteUrlReachability;
use Corex\Config\Settings\AdminDashboard;
use Corex\Config\Settings\FieldSections;
use Corex\Config\Settings\SettingsRegistry;
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
        // The built-in settings screen autowires SettingsForm, which depends on the FieldSections
        // seam (spec 039) — bind it to the concrete SettingsRegistry so it resolves. (Option pages
        // construct SettingsForm explicitly with their own FieldSections, so they don't need this.)
        $this->container->bind(
            FieldSections::class,
            static fn (ContainerInterface $c): FieldSections => $c->make(SettingsRegistry::class),
        );

        $this->container->singleton(
            BrandingService::class,
            static fn (ContainerInterface $c): BrandingService => new BrandingService(
                $c->make(ConfigInterface::class),
                // The approved Core X product lockup (see assets/brand/logo-manifest.json).
                // A per-site `brand.logo_url` override still wins, so client identity is unaffected.
                plugins_url('assets/brand/corex-lockup.svg', dirname(__DIR__) . '/corex-config.php'),
            ),
        );

        $this->container->singleton(AdminBranding::class);
        $this->container->singleton(AdminDashboard::class);
        $this->container->singleton(AddonsScreen::class);

        // The submissions reader, shared by the Data source and the dashboard "Site status" card (spec 042).
        $this->container->singleton(SubmissionsReader::class, static fn (): WpSubmissionsReader => new WpSubmissionsReader());

        // Data screen: a registry seeded with the submissions source, the REST controller,
        // and the React screen (spec 030).
        $this->container->singleton(DataRegistry::class, function (ContainerInterface $c): DataRegistry {
            $registry = new DataRegistry();
            $registry->register(new SubmissionsSource($c->make(SubmissionsReader::class)));

            // Every table an app marked managed appears as its own source — no admin code (spec 038).
            $reader = new WpTableDataReader($c->make(Migrator::class));
            foreach ($c->make(ManagedTables::class)->all() as $table) {
                $registry->register(new TableDataSource($table, $reader));
            }

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
                new SiteUrlReachability(),
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

        // Custom option pages: a registry an app fills with declarative OptionPages, and the
        // screen that renders + saves each one with the shared SettingsForm/Store (spec 039).
        $this->container->singleton(OptionPageRegistry::class);
        $this->container->singleton(OptionPageScreen::class);
    }

    public function boot(): void
    {
        $this->container->make(AdminBranding::class)->register();
        $this->container->make(AdminDashboard::class)->register();
        $this->container->make(AddonsScreen::class)->register();
        $this->container->make(KitActivationNotice::class)->register();
        $this->container->make(DataAdminScreen::class)->register();
        $this->container->make(InsightsScreen::class)->register();
        $this->container->make(OptionPageScreen::class)->register();
        $this->container->make(\Corex\Config\Data\DataExportController::class)->register(); // CSV export (spec 045)

        add_action('rest_api_init', function (): void {
            $this->container->make(DataController::class)->register();
            $this->container->make(InsightsController::class)->register();
        });
    }
}
