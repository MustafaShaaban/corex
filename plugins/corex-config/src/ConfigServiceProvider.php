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
use Corex\Config\Activity\ActivityTable;
use Corex\Config\Activity\ActivityController;
use Corex\Config\Activity\WpActivityRepository;
use Corex\Config\Access\AbilityCompatibility;
use Corex\Config\Access\AccessController;
use Corex\Config\Access\AccessRequestRepository;
use Corex\Config\Access\AccessService;
use Corex\Config\Access\AccessTables;
use Corex\Config\Access\RolePluginCompatibility;
use Corex\Config\Access\RoleAbilityRepository;
use Corex\Config\Access\WpAccessUserDirectory;
use Corex\Config\Blog\AuthorAnalyticsService;
use Corex\Config\Blog\BlogAnalyticsService;
use Corex\Config\Blog\BlogProController;
use Corex\Config\Blog\BlogProServices;
use Corex\Config\Blog\CommentModerationService;
use Corex\Config\Blog\EditorialWorkflowService;
use Corex\Config\Blog\EditorialWorkflowStore;
use Corex\Config\Blog\ReadingEventRepository;
use Corex\Config\Blog\ReadingEventStore;
use Corex\Config\Blog\ReadingEventTable;
use Corex\Config\Blog\SocialSharingService;
use Corex\Config\Blog\SocialSharingSettingsStore;
use Corex\Config\Blog\WpEditorialWorkflowStore;
use Corex\Config\Blog\WpSocialSharingSettingsStore;
use Corex\Config\Jobs\ActionSchedulerJobDispatcher;
use Corex\Config\Jobs\CronJobDispatcher;
use Corex\Config\Jobs\JobController;
use Corex\Config\Jobs\JobRunner;
use Corex\Config\Jobs\JobTable;
use Corex\Config\Jobs\WpJobRepository;
use Corex\Config\Branding\AdminBranding;
use Corex\Config\Branding\BrandingService;
use Corex\Config\Data\DataAdminScreen;
use Corex\Config\Data\DataController;
use Corex\Config\Data\DataManagementController;
use Corex\Config\Data\DataManagementServices;
use Corex\Config\Data\DataRestGateway;
use Corex\Config\Data\DataAccessPolicy;
use Corex\Config\Data\DataMutationPreviewStore;
use Corex\Config\Data\DataMutationService;
use Corex\Config\Data\DataQueryService;
use Corex\Config\Data\DataRegistry;
use Corex\Config\Data\DataSourceService;
use Corex\Config\Data\SubmissionsReader;
use Corex\Config\Data\SubmissionsSource;
use Corex\Config\Data\TableDataSource;
use Corex\Config\Data\WpSubmissionsReader;
use Corex\Config\Data\WpTableDataReader;
use Corex\Config\Data\WpDataAccessPolicy;
use Corex\Config\Data\WpDataMutationPreviewStore;
use Corex\Config\DataModels\DataModelsCatalog;
use Corex\Config\DataModels\DataModelsScreen;
use Corex\Config\DataModels\DataImportJobHandler;
use Corex\Config\DataModels\DataImportJobQueue;
use Corex\Config\DataModels\DataImportService;
use Corex\Config\DataModels\DataImportStore;
use Corex\Config\DataModels\WpDataImportJobQueue;
use Corex\Config\DataModels\WpDataImportStore;
use Corex\Config\DataModels\DataExportArtifactWriter;
use Corex\Config\DataModels\DataExportJobHandler;
use Corex\Config\DataModels\DataExportJobQueue;
use Corex\Config\DataModels\DataExportService;
use Corex\Config\DataModels\DataExportStore;
use Corex\Config\DataModels\WpDataExportJobQueue;
use Corex\Config\DataModels\WpDataExportStore;
use Corex\Config\DataModels\MigrationJobHandler;
use Corex\Config\DataModels\MigrationJobQueue;
use Corex\Config\DataModels\MigrationPreviewStore;
use Corex\Config\DataModels\MigrationRunStore;
use Corex\Config\DataModels\MigrationService;
use Corex\Config\DataModels\WpMigrationJobQueue;
use Corex\Config\DataModels\WpMigrationPreviewStore;
use Corex\Config\DataModels\WpMigrationRunStore;
use Corex\Config\Email\EmailStudioScreen;
use Corex\Config\Forms\FormsFlowsScreen;
use Corex\Config\Security\OperationsSecurityScreen;
use Corex\Config\Options\OptionPageRegistry;
use Corex\Config\Options\OptionPageScreen;
use Corex\Config\Submissions\SubmissionAccessPolicy;
use Corex\Config\Submissions\SubmissionBulkPreviewStore;
use Corex\Config\Submissions\SubmissionBulkService;
use Corex\Config\Submissions\SubmissionExportCsvWriter;
use Corex\Config\Submissions\SubmissionExportJobHandler;
use Corex\Config\Submissions\SubmissionExportJobQueue;
use Corex\Config\Submissions\SubmissionExportService;
use Corex\Config\Submissions\SubmissionExportSource;
use Corex\Config\Submissions\SubmissionExportStore;
use Corex\Config\Submissions\SubmissionEmailService;
use Corex\Config\Submissions\SubmissionControllerServices;
use Corex\Config\Submissions\SubmissionInboxReader;
use Corex\Config\Submissions\SubmissionQueryService;
use Corex\Config\Submissions\SubmissionRestGateway;
use Corex\Config\Submissions\SubmissionTimelineRepository;
use Corex\Config\Submissions\SubmissionTimelineStore;
use Corex\Config\Submissions\SubmissionWorkflowService;
use Corex\Config\Submissions\SubmissionWorkflowStore;
use Corex\Config\Submissions\SubmissionsInboxScreen;
use Corex\Config\Submissions\SubmissionsController;
use Corex\Config\Submissions\WpSubmissionAccessPolicy;
use Corex\Config\Submissions\WpSubmissionBulkPreviewStore;
use Corex\Config\Submissions\WpSubmissionExportJobQueue;
use Corex\Config\Submissions\WpSubmissionExportStore;
use Corex\Config\Retention\SubmissionRetentionStore;
use Corex\Database\Schema\ManagedTables;
use Corex\Database\Schema\Migrator;
use Corex\Config\Insights\InsightRegistry;
use Corex\Config\Insights\InsightStore;
use Corex\Config\Insights\InsightWidgetFacts;
use Corex\Config\Insights\InsightWidgets;
use Corex\Config\Insights\InsightsController;
use Corex\Config\Insights\InsightsScreen;
use Corex\Config\Operations\OperationsModeStore;
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
use Corex\Activity\ActivityRepository;
use Corex\Activity\ActivityService;
use Corex\Access\AccessPolicy;
use Corex\Access\AccessRequestStore;
use Corex\Access\AccessUserDirectory;
use Corex\Access\CorexAbilityCatalog;
use Corex\Access\RoleAbilityStore;
use Corex\Jobs\JobDispatcher;
use Corex\Jobs\JobHandlerRegistry;
use Corex\Jobs\JobRepository;
use Corex\Jobs\JobService;
use Corex\Mail\SubmissionEmailGateway;
use Corex\Mail\UnavailableSubmissionEmailGateway;

/**
 * The corex-config provider: Corex's product branding (and, later, the settings UI).
 * Registers the admin-branding hooks on boot.
 */
final class ConfigServiceProvider extends ServiceProvider
{
    private const FOUNDATION_SCHEMA_OPTION  = 'corex_product_foundation_schema_version';
    private const FOUNDATION_SCHEMA_VERSION = '2';

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

        // The shared append-only activity stream (spec 068) is the authoritative audit source for
        // every CoreX product area. Persistence remains behind the core repository contract.
        $this->container->singleton(ActivityTable::class);
        $this->container->singleton(ActivityController::class);
        $this->container->singleton(WpActivityRepository::class);
        $this->container->singleton(
            ActivityRepository::class,
            static fn (ContainerInterface $c): ActivityRepository => $c->make(WpActivityRepository::class),
        );
        $this->container->singleton(
            ActivityService::class,
            static fn (ContainerInterface $c): ActivityService => new ActivityService(
                $c->make(ActivityRepository::class),
            ),
        );

        $this->container->singleton(
            CorexAbilityCatalog::class,
            static fn (): CorexAbilityCatalog => CorexAbilityCatalog::defaults(),
        );
        $this->container->singleton(
            AccessPolicy::class,
            static fn (ContainerInterface $c): AccessPolicy => new AccessPolicy(
                $c->make(CorexAbilityCatalog::class),
            ),
        );
        $this->container->singleton(AccessTables::class);
        $this->container->singleton(RoleAbilityRepository::class);
        $this->container->singleton(
            RoleAbilityStore::class,
            static fn (ContainerInterface $c): RoleAbilityStore => $c->make(RoleAbilityRepository::class),
        );
        $this->container->singleton(AccessRequestRepository::class);
        $this->container->singleton(
            AccessRequestStore::class,
            static fn (ContainerInterface $c): AccessRequestStore => $c->make(AccessRequestRepository::class),
        );
        $this->container->singleton(WpAccessUserDirectory::class);
        $this->container->singleton(
            AccessUserDirectory::class,
            static fn (ContainerInterface $c): AccessUserDirectory => $c->make(WpAccessUserDirectory::class),
        );
        $this->container->singleton(
            AccessService::class,
            static fn (ContainerInterface $c): AccessService => new AccessService(
                $c->make(CorexAbilityCatalog::class),
                $c->make(AccessPolicy::class),
                $c->make(RoleAbilityStore::class),
                $c->make(AccessRequestStore::class),
                $c->make(AccessUserDirectory::class),
                $c->make(ActivityService::class),
                $c->has(\Corex\Mail\RoutedMailer::class) ? $c->make(\Corex\Mail\RoutedMailer::class) : null,
            ),
        );
        $this->container->singleton(AbilityCompatibility::class);
        $this->container->singleton(RolePluginCompatibility::class);
        $this->container->singleton(AccessController::class);

        $this->container->singleton(JobTable::class);
        $this->container->singleton(WpJobRepository::class);
        $this->container->singleton(
            JobRepository::class,
            static fn (ContainerInterface $c): JobRepository => $c->make(WpJobRepository::class),
        );
        $this->container->singleton(ActionSchedulerJobDispatcher::class);
        $this->container->singleton(CronJobDispatcher::class);
        $this->container->singleton(
            JobDispatcher::class,
            static function (ContainerInterface $c): JobDispatcher {
                $actionScheduler = $c->make(ActionSchedulerJobDispatcher::class);

                return $actionScheduler->available()
                    ? $actionScheduler
                    : $c->make(CronJobDispatcher::class);
            },
        );
        $this->container->singleton(JobHandlerRegistry::class);
        $this->container->singleton(
            JobService::class,
            static fn (ContainerInterface $c): JobService => new JobService(
                $c->make(JobRepository::class),
                $c->make(JobDispatcher::class),
            ),
        );
        $this->container->singleton(JobRunner::class);
        $this->container->singleton(JobController::class);

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
        $this->container->singleton(\Corex\Config\Security\HardeningChecks::class);
        $this->container->singleton(\Corex\Config\Operations\ProductionReadinessSnapshotFactory::class);
        $this->container->singleton(\Corex\Config\Operations\ProductionLaunchService::class);
        $this->container->singleton(\Corex\Config\Security\LoginProtection\LoginAttemptTable::class);
        $this->container->singleton(\Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore::class);
        $this->container->bind(
            \Corex\Config\Security\LoginProtection\LoginProtectionSettings::class,
            static fn (ContainerInterface $c): \Corex\Config\Security\LoginProtection\LoginProtectionSettings =>
                $c->make(\Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore::class)->current(),
        );
        $this->container->singleton(\Corex\Config\Security\LoginProtection\LoginProtectionPolicy::class);
        $this->container->singleton(\Corex\Config\Security\LoginProtection\LoginProtectionService::class);
        $this->container->singleton(\Corex\Config\Security\LoginProtection\ClientIpResolver::class);
        $this->container->singleton(\Corex\Config\Security\LoginProtection\LoginRouteGuard::class);
        $this->container->singleton(\Corex\Config\Security\LoginProtection\WpLoginAttemptStore::class);
        $this->container->singleton(
            \Corex\Config\Security\LoginProtection\LoginAttemptStore::class,
            static fn (ContainerInterface $c): \Corex\Config\Security\LoginProtection\WpLoginAttemptStore =>
                $c->make(\Corex\Config\Security\LoginProtection\WpLoginAttemptStore::class),
        );
        $this->container->singleton(
            \Corex\Config\Security\LoginProtection\LoginLockoutStore::class,
            static fn (ContainerInterface $c): \Corex\Config\Security\LoginProtection\WpLoginAttemptStore =>
                $c->make(\Corex\Config\Security\LoginProtection\WpLoginAttemptStore::class),
        );

        $this->container->singleton(AdminDashboard::class);
        $this->container->singleton(AddonsScreen::class);

        // Forms & Flows client mount; runtime activation remains dependency-gated.
        $this->container->singleton(
            FormsFlowsScreen::class,
            static fn (ContainerInterface $c): FormsFlowsScreen => new FormsFlowsScreen(
                $c->make(\Corex\Security\Admin\AdminGuard::class),
                $c->make(\Corex\Admin\AdminPage::class),
            ),
        );

        // The submissions reader, shared by the Data source and the dashboard "Site status" card (spec 042).
        $this->container->singleton(WpSubmissionsReader::class);
        $this->container->singleton(
            SubmissionsReader::class,
            static fn (ContainerInterface $c): WpSubmissionsReader => $c->make(WpSubmissionsReader::class),
        );
        $this->container->singleton(
            SubmissionInboxReader::class,
            static fn (ContainerInterface $c): WpSubmissionsReader => $c->make(WpSubmissionsReader::class),
        );
        $this->container->singleton(
            SubmissionWorkflowStore::class,
            static fn (ContainerInterface $c): WpSubmissionsReader => $c->make(WpSubmissionsReader::class),
        );
        $this->container->singleton(
            SubmissionRetentionStore::class,
            static fn (ContainerInterface $c): WpSubmissionsReader => $c->make(WpSubmissionsReader::class),
        );
        $this->container->singleton(
            SubmissionExportSource::class,
            static fn (ContainerInterface $c): WpSubmissionsReader => $c->make(WpSubmissionsReader::class),
        );

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
        $this->container->singleton(DataAccessPolicy::class, static fn (): WpDataAccessPolicy => new WpDataAccessPolicy());
        $this->container->singleton(DataSourceService::class);
        $this->container->singleton(DataQueryService::class);
        $this->container->singleton(WpDataMutationPreviewStore::class);
        $this->container->singleton(
            DataMutationPreviewStore::class,
            static fn (ContainerInterface $c): WpDataMutationPreviewStore => $c->make(WpDataMutationPreviewStore::class),
        );
        $this->container->singleton(DataMutationService::class);
        $this->container->singleton(DataRestGateway::class);
        $this->container->singleton(DataManagementServices::class);
        $this->container->singleton(DataManagementController::class);
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
        $this->container->singleton(InsightWidgets::class);
        $this->container->singleton(InsightWidgetFacts::class, static fn (ContainerInterface $c): InsightWidgetFacts =>
            new InsightWidgetFacts(
                $c->make(ConfigInterface::class),
                $c->make(InsightStore::class),
                $c->make(ActivityService::class),
                $c->make(OperationsModeStore::class),
                $c->make(SubmissionsReader::class),
                $c,
            ));
        $this->container->singleton(InsightsController::class, static fn (ContainerInterface $c): InsightsController =>
            new InsightsController(
                $c->make(InsightRegistry::class),
                $c->make(InsightStore::class),
                'corex_insights',
                null,
                $c->make(InsightWidgets::class),
                $c->make(InsightWidgetFacts::class),
            ));
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
                    $c->make(SubmissionRetentionStore::class),
                ),
        );
        $this->container->singleton(\Corex\Config\Retention\RetentionController::class);

        // Submissions Inbox (spec 063): a business-friendly view over the real corex_submission
        // records, reusing the shared SubmissionsReader.
        $this->container->singleton(SubmissionAccessPolicy::class, static fn (): WpSubmissionAccessPolicy => new WpSubmissionAccessPolicy());
        $this->container->singleton(SubmissionTimelineRepository::class);
        $this->container->singleton(
            SubmissionTimelineStore::class,
            static fn (ContainerInterface $c): SubmissionTimelineRepository => $c->make(SubmissionTimelineRepository::class),
        );
        $this->container->singleton(SubmissionQueryService::class);
        $this->container->singleton(SubmissionWorkflowService::class);
        $this->container->singleton(
            SubmissionEmailGateway::class,
            static fn (): UnavailableSubmissionEmailGateway => new UnavailableSubmissionEmailGateway(),
        );
        $this->container->singleton(SubmissionEmailService::class);
        $this->container->singleton(WpSubmissionBulkPreviewStore::class);
        $this->container->singleton(
            SubmissionBulkPreviewStore::class,
            static fn (ContainerInterface $c): WpSubmissionBulkPreviewStore => $c->make(WpSubmissionBulkPreviewStore::class),
        );
        $this->container->singleton(SubmissionBulkService::class);
        $this->container->singleton(WpSubmissionExportStore::class);
        $this->container->singleton(
            SubmissionExportStore::class,
            static fn (ContainerInterface $c): WpSubmissionExportStore => $c->make(WpSubmissionExportStore::class),
        );
        $this->container->singleton(WpSubmissionExportJobQueue::class);
        $this->container->singleton(
            SubmissionExportJobQueue::class,
            static fn (ContainerInterface $c): WpSubmissionExportJobQueue => $c->make(WpSubmissionExportJobQueue::class),
        );
        $this->container->singleton(SubmissionExportCsvWriter::class);
        $this->container->singleton(SubmissionExportJobHandler::class);
        $this->container->singleton(SubmissionExportService::class);
        $this->container->singleton(SubmissionControllerServices::class);
        $this->container->singleton(SubmissionRestGateway::class);
        $this->container->singleton(SubmissionsController::class);
        $this->container->singleton(SubmissionsInboxScreen::class);

        // Data Models catalog (spec 063): a truthful schema catalog over the real DataRegistry sources.
        // Spec 068 adds immutable CSV dry-runs and bounded write-adapter commits.
        $this->container->singleton(\Corex\Config\DataModels\DataImportValidator::class);
        $this->container->singleton(WpDataImportStore::class);
        $this->container->singleton(
            DataImportStore::class,
            static fn (ContainerInterface $c): WpDataImportStore => $c->make(WpDataImportStore::class),
        );
        $this->container->singleton(WpDataImportJobQueue::class);
        $this->container->singleton(
            DataImportJobQueue::class,
            static fn (ContainerInterface $c): WpDataImportJobQueue => $c->make(WpDataImportJobQueue::class),
        );
        $this->container->singleton(DataImportService::class);
        $this->container->singleton(DataImportJobHandler::class);
        $this->container->singleton(WpDataExportStore::class);
        $this->container->singleton(
            DataExportStore::class,
            static fn (ContainerInterface $c): WpDataExportStore => $c->make(WpDataExportStore::class),
        );
        $this->container->singleton(WpDataExportJobQueue::class);
        $this->container->singleton(
            DataExportJobQueue::class,
            static fn (ContainerInterface $c): WpDataExportJobQueue => $c->make(WpDataExportJobQueue::class),
        );
        $this->container->singleton(DataExportArtifactWriter::class);
        $this->container->singleton(DataExportService::class);
        $this->container->singleton(DataExportJobHandler::class);
        $this->container->singleton(WpMigrationPreviewStore::class);
        $this->container->singleton(
            MigrationPreviewStore::class,
            static fn (ContainerInterface $c): WpMigrationPreviewStore => $c->make(WpMigrationPreviewStore::class),
        );
        $this->container->singleton(WpMigrationRunStore::class);
        $this->container->singleton(
            MigrationRunStore::class,
            static fn (ContainerInterface $c): WpMigrationRunStore => $c->make(WpMigrationRunStore::class),
        );
        $this->container->singleton(WpMigrationJobQueue::class);
        $this->container->singleton(
            MigrationJobQueue::class,
            static fn (ContainerInterface $c): WpMigrationJobQueue => $c->make(WpMigrationJobQueue::class),
        );
        $this->container->singleton(MigrationService::class);
        $this->container->singleton(MigrationJobHandler::class);
        $this->container->singleton(\Corex\Config\DataModels\DataModelsImportController::class);
        $this->container->singleton(DataModelsScreen::class);

        // Operations & Security overview (spec 063): real environment + real WordPress hardening checks.
        $this->container->singleton(OperationsSecurityScreen::class);

        // Access & Abilities (spec 065 baseline → spec 067 tabs): read-only role × capability surface,
        // a real denied-attempt audit log, and the menu-level designed HTTP 403 for CoreX pages.
        $this->container->singleton(\Corex\Config\Access\AccessAuditLog::class);
        $this->container->singleton(\Corex\Config\Access\AccessDeniedGate::class);
        $this->container->singleton(\Corex\Config\Access\AccessScreen::class);

        // Blog Pro analytics (spec 068): consented first-party events persist only pseudonymous
        // visitor hashes and aggregate through this injected store.
        $this->container->singleton(ReadingEventTable::class);
        $this->container->singleton(ReadingEventRepository::class);
        $this->container->singleton(
            ReadingEventStore::class,
            static fn (ContainerInterface $c): ReadingEventRepository => $c->make(ReadingEventRepository::class),
        );
        $this->container->singleton(BlogAnalyticsService::class);
        $this->container->singleton(WpEditorialWorkflowStore::class);
        $this->container->singleton(
            EditorialWorkflowStore::class,
            static fn (ContainerInterface $c): WpEditorialWorkflowStore => $c->make(WpEditorialWorkflowStore::class),
        );
        $this->container->singleton(EditorialWorkflowService::class);
        $this->container->singleton(CommentModerationService::class);
        $this->container->singleton(AuthorAnalyticsService::class);
        $this->container->singleton(WpSocialSharingSettingsStore::class);
        $this->container->singleton(
            SocialSharingSettingsStore::class,
            static fn (ContainerInterface $c): WpSocialSharingSettingsStore => $c->make(WpSocialSharingSettingsStore::class),
        );
        $this->container->singleton(SocialSharingService::class);
        $this->container->singleton(BlogProServices::class);
        $this->container->singleton(BlogProController::class);

        // Blog Pro screen stays registered while Phase 8 replaces the remaining reference UI.
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
            ),
        );
    }

    public function boot(): void
    {
        $activityTable = $this->container->make(ActivityTable::class);
        $this->container->make(ManagedTables::class)->register($activityTable->managed());

        $accessTables = $this->container->make(AccessTables::class);
        foreach ($accessTables->managed() as $managedTable) {
            $this->container->make(ManagedTables::class)->register($managedTable);
        }
        $jobTable = $this->container->make(JobTable::class);
        $loginAttemptTable = $this->container->make(\Corex\Config\Security\LoginProtection\LoginAttemptTable::class);
        $readingEventTable = $this->container->make(ReadingEventTable::class);
        $this->container->make(ManagedTables::class)->register($jobTable->managed());
        $this->container->make(ManagedTables::class)->register($loginAttemptTable->managed());
        $this->container->make(ManagedTables::class)->register($readingEventTable->managed());
        $this->installFoundationSchema([
            $activityTable->schema(),
            ...$accessTables->schemas(),
            $jobTable->schema(),
            $loginAttemptTable->schema(),
            $readingEventTable->schema(),
        ]);
        $this->container->make(AbilityCompatibility::class)->register();
        add_action('init', [$this->container->make(WpSubmissionExportStore::class), 'registerPostType']);
        add_action('init', [$this->container->make(WpDataImportStore::class), 'registerPostType']);
        add_action('init', [$this->container->make(WpDataExportStore::class), 'registerPostType']);
        add_action('init', [$this->container->make(WpMigrationRunStore::class), 'registerPostType']);
        $this->container->make(JobHandlerRegistry::class)->register(
            $this->container->make(SubmissionExportJobHandler::class),
        );
        $this->container->make(JobHandlerRegistry::class)->register(
            $this->container->make(DataImportJobHandler::class),
        );
        $this->container->make(JobHandlerRegistry::class)->register(
            $this->container->make(DataExportJobHandler::class),
        );
        $this->container->make(JobHandlerRegistry::class)->register(
            $this->container->make(MigrationJobHandler::class),
        );
        $this->container->make(JobRunner::class)->register();
        add_action('rest_api_init', function (): void {
            $this->container->make(SubmissionsController::class)->register();
        });
        add_action('rest_api_init', function (): void {
            $this->container->make(AccessController::class)->register();
        });

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
        $this->container->make(\Corex\Config\Security\LoginProtection\LoginRouteGuard::class)->register();
        $this->container->make(EmailStudioScreen::class)->register();
        $this->container->make(KitActivationNotice::class)->register();
        $this->container->make(DataAdminScreen::class)->register();
        $this->container->make(InsightsScreen::class)->register();
        $this->container->make(OptionPageScreen::class)->register();
        $this->container->make(\Corex\Config\Data\DataExportController::class)->register(); // CSV export (spec 045)

        add_action('rest_api_init', function (): void {
            $this->container->make(ActivityController::class)->register();
            $this->container->make(JobController::class)->register();
            $this->container->make(DataManagementController::class)->register();
            $this->container->make(InsightsController::class)->register();
            $this->container->make(BlogProController::class)->register();
        });
    }

    /** @param list<\Corex\Database\Schema\Table> $schemas */
    private function installFoundationSchema(array $schemas): void
    {
        $installedVersion = get_option(self::FOUNDATION_SCHEMA_OPTION, '');

        if ($installedVersion === self::FOUNDATION_SCHEMA_VERSION) {
            return;
        }

        if (! is_file(ABSPATH . 'wp-admin/includes/upgrade.php')) {
            return;
        }

        $migrator = $this->container->make(Migrator::class);
        foreach ($schemas as $schema) {
            $migrator->create($schema);
        }

        foreach ($schemas as $schema) {
            if (! $migrator->exists($schema->name)) {
                return;
            }
        }

        update_option(self::FOUNDATION_SCHEMA_OPTION, self::FOUNDATION_SCHEMA_VERSION, false);
    }
}
