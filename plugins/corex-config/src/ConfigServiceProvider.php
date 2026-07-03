<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config;

defined('ABSPATH') || exit;

use Corex\Config\Addons\AddonsScreen;
use Corex\Config\AdminUi\CorexAdminAssets;
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
use Corex\Config\DataModels\DataModelsCatalog;
use Corex\Config\DataModels\DataModelsScreen;
use Corex\Config\Email\EmailStudio;
use Corex\Config\Email\EmailStudioScreen;
use Corex\Config\Forms\FormsFlowsScreen;
use Corex\Config\Forms\FormsOverview;
use Corex\Config\Overview\EnvironmentMode;
use Corex\Config\Security\OperationsSecurityScreen;
use Corex\Config\Options\OptionPageRegistry;
use Corex\Config\Options\OptionPageScreen;
use Corex\Config\Submissions\SubmissionsInboxScreen;
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
        $this->container->singleton(CorexAdminAssets::class);

        // The Overview dashboard renderer (spec 064): the single cohesive readiness grid built from real
        // state. Optional forms/provisioning deps resolve lazily via the container (Principle IX).
        $this->container->singleton(
            \Corex\Config\Overview\OverviewRenderer::class,
            static fn (ContainerInterface $c): \Corex\Config\Overview\OverviewRenderer => new \Corex\Config\Overview\OverviewRenderer(
                new \Corex\Config\Overview\OverviewModel(),
                $c->make(\Corex\Config\Operations\OperationsMode::class),
                $c->make(\Corex\Config\Operations\OperationsModeStore::class),
                $c->make(\Corex\Config\ControlPanel\ControlPanelStatus::class),
                $c->make(\Corex\Config\Security\HardeningChecks::class),
                $c->make(\Corex\Config\Data\SubmissionsReader::class),
                $c->make(\Corex\Config\Data\DataRegistry::class),
                $c->make(\Corex\Config\Addons\AddonRegistry::class),
                $c,
            ),
        );

        // Operations Mode (spec 065): the real, persisted operating mode + its audit store, shared so
        // the Overview badge, the Operations & Security screen, and the maintenance guard agree.
        $this->container->singleton(\Corex\Config\Operations\OperationsMode::class);
        $this->container->singleton(
            \Corex\Config\Operations\OperationsModeStore::class,
            static fn (ContainerInterface $c): \Corex\Config\Operations\OperationsModeStore =>
                new \Corex\Config\Operations\OperationsModeStore($c->make(\Corex\Config\Operations\OperationsMode::class)),
        );

        $this->container->singleton(AdminDashboard::class);
        $this->container->singleton(AddonsScreen::class);

        // Forms & Flows admin screen (spec 063): a read-only inventory of the real code-defined forms.
        // The FormRegistry is resolved lazily inside the screen so corex-config never hard-depends on
        // corex-forms (Principle IX); the container is passed for that lazy resolution.
        $this->container->singleton(
            FormsFlowsScreen::class,
            static fn (ContainerInterface $c): FormsFlowsScreen => new FormsFlowsScreen(
                $c->make(\Corex\Security\Admin\AdminGuard::class),
                $c->make(\Corex\Admin\AdminPage::class),
                new FormsOverview(),
                $c,
            ),
        );

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

        // Submission retention (spec 065): real, safe pruning with a dry-run preview.
        $this->container->singleton(\Corex\Config\Retention\RetentionSettings::class);
        $this->container->singleton(
            \Corex\Config\Retention\SubmissionRetention::class,
            static fn (ContainerInterface $c): \Corex\Config\Retention\SubmissionRetention =>
                new \Corex\Config\Retention\SubmissionRetention(
                    $c->make(\Corex\Config\Retention\RetentionSettings::class),
                    $c->make(SubmissionsReader::class),
                ),
        );
        $this->container->singleton(\Corex\Config\Retention\RetentionController::class);

        // Submissions Inbox (spec 063): a business-friendly view over the real corex_submission
        // records, reusing the shared SubmissionsReader.
        $this->container->singleton(SubmissionsInboxScreen::class);

        // Data Models catalog (spec 063): a truthful schema catalog over the real DataRegistry sources.
        // Spec 065 adds a real CSV import dry-run (validation only) + a truthful migration overview.
        $this->container->singleton(\Corex\Config\DataModels\DataImportValidator::class);
        $this->container->singleton(\Corex\Config\DataModels\DataModelsImportController::class);
        $this->container->singleton(DataModelsScreen::class);

        // Operations & Security overview (spec 063): real environment + real WordPress hardening checks.
        $this->container->singleton(OperationsSecurityScreen::class);

        // Access & Abilities (spec 065 baseline → spec 067 tabs): read-only role × capability surface,
        // a real denied-attempt audit log, and the menu-level designed HTTP 403 for CoreX pages.
        $this->container->singleton(\Corex\Config\Access\AccessAuditLog::class);
        $this->container->singleton(\Corex\Config\Access\AccessDeniedGate::class);
        $this->container->singleton(\Corex\Config\Access\AccessScreen::class);

        // Blog Pro reference surface (spec 067): visible, honestly-gated future add-on — real editorial/
        // comments/authors data + a clearly-labelled sample analytics layout. No fake live metrics.
        $this->container->singleton(\Corex\Config\Blog\BlogProModel::class);
        $this->container->singleton(\Corex\Config\Blog\BlogProScreen::class);

        // Email Studio (spec 063): a truthful overview of the transactional-email engine. Gated on the
        // optional corex-email add-on; TemplateRegistry is resolved lazily via the container so
        // corex-config never hard-depends on the add-on (Principle IX).
        $this->container->singleton(
            EmailStudioScreen::class,
            static fn (ContainerInterface $c): EmailStudioScreen => new EmailStudioScreen(
                $c->make(\Corex\Security\Admin\AdminGuard::class),
                $c->make(\Corex\Admin\AdminPage::class),
                new EmailStudio(),
                new EnvironmentMode(),
                $c,
            ),
        );
    }

    public function boot(): void
    {
        $this->container->make(AdminBranding::class)->register();
        $this->container->make(CorexAdminAssets::class)->register();
        $this->container->make(AdminDashboard::class)->register();
        $this->container->make(AddonsScreen::class)->register();
        $this->container->make(FormsFlowsScreen::class)->register();
        $this->container->make(SubmissionsInboxScreen::class)->register();
        $this->container->make(\Corex\Config\Retention\RetentionController::class)->register();
        $this->container->make(DataModelsScreen::class)->register();
        $this->container->make(\Corex\Config\DataModels\DataModelsImportController::class)->register();
        $this->container->make(OperationsSecurityScreen::class)->register();
        $this->container->make(\Corex\Config\Access\AccessAuditLog::class)->register();
        $this->container->make(\Corex\Config\Access\AccessDeniedGate::class)->register();
        $this->container->make(\Corex\Config\Access\AccessScreen::class)->register();
        $this->container->make(\Corex\Config\Blog\BlogProScreen::class)->register();
        $this->container->make(\Corex\Config\Operations\OperationsModeController::class)->register();
        $this->container->make(\Corex\Config\Operations\MaintenanceGuard::class)->register();
        $this->container->make(EmailStudioScreen::class)->register();
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
