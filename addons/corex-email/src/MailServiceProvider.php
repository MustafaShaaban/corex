<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email;

defined('ABSPATH') || exit;

use Corex\Container\ContainerInterface;
use Corex\Email\Driver\MailDriver;
use Corex\Email\Driver\WpMailDriver;
use Corex\Email\Capture\CapturedEmailRepository;
use Corex\Email\Delivery\DeliveryPolicy;
use Corex\Email\Delivery\EmailAttemptRepository;
use Corex\Email\Log\EmailLogRepository;
use Corex\Email\Log\EmailLogStore;
use Corex\Email\Recipients\RecipientResolver;
use Corex\Email\Recipients\UserDirectory;
use Corex\Email\Recipients\WpUserDirectory;
use Corex\Email\Security\HeaderGuard;
use Corex\Email\Routing\EmailRouteDispatcher;
use Corex\Email\Routing\EmailRouteMessageFactory;
use Corex\Email\Routing\EmailRouteRepository;
use Corex\Email\Routing\EmailRouteService;
use Corex\Email\Studio\EmailLayoutRepository;
use Corex\Email\Studio\EmailPartialRepository;
use Corex\Email\Studio\EmailStudioController;
use Corex\Email\Studio\EmailStudioSubmissionGateway;
use Corex\Email\Studio\EmailStudioRepositories;
use Corex\Email\Studio\EmailStudioService;
use Corex\Email\Studio\EmailStudioStore;
use Corex\Email\Studio\EmailTemplateRepository;
use Corex\Email\Studio\EmailTemplateCatalog;
use Corex\Email\Studio\EmailTemplateService;
use Corex\Email\Studio\WpEmailStudioStore;
use Corex\Email\Template\Layout;
use Corex\Email\Template\TemplateRegistry;
use Corex\Email\Template\TemplateRenderer;
use Corex\Email\Templates\ContactNotificationTemplate;
use Corex\Email\Queue\ActionSchedulerDispatcher;
use Corex\Email\Queue\MailQueueDispatcher;
use Corex\Email\Queue\MailQueueGate;
use Corex\Email\Queue\QueuedMailer;
use Corex\Foundation\ServiceProvider;
use Corex\Mail\Mailer;
use Corex\Mail\RoutedMailer;
use Corex\Mail\MailTemplateCatalog;
use Corex\Mail\SubmissionEmailGateway;
use Corex\Support\BootLogger;
use Corex\Support\Config\ConfigInterface;
use Corex\Support\Config\FeatureFlags;

/**
 * Boots the mail engine: binds the headless cores (renderer, header guard, recipient
 * resolver) and the boundary (driver, log repository, user directory + the
 * MailService), binds the corex-core Mailer seam to this engine, and on the boot pass
 * registers the email-log CPT and the default templates.
 */
final class MailServiceProvider extends ServiceProvider
{
    private const STUDIO_DEFAULTS_VERSION = '3';

    public function register(): void
    {
        $this->container->singleton(TemplateRegistry::class);
        $this->container->singleton(HeaderGuard::class);
        $this->container->singleton(DeliveryPolicy::class);

        $this->container->singleton(WpEmailStudioStore::class);
        $this->container->singleton(
            EmailStudioStore::class,
            static fn (ContainerInterface $c): EmailStudioStore => $c->make(WpEmailStudioStore::class),
        );
        $this->container->singleton(EmailTemplateRepository::class);
        $this->container->singleton(EmailTemplateCatalog::class);
        $this->container->singleton(
            MailTemplateCatalog::class,
            static fn (ContainerInterface $c): MailTemplateCatalog => $c->make(EmailTemplateCatalog::class),
        );
        $this->container->singleton(EmailLayoutRepository::class);
        $this->container->singleton(EmailPartialRepository::class);
        $this->container->singleton(CapturedEmailRepository::class);
        $this->container->singleton(EmailAttemptRepository::class);
        $this->container->singleton(EmailRouteRepository::class);
        $this->container->singleton(
            EmailTemplateService::class,
            static function (ContainerInterface $c): EmailTemplateService {
                $defaults = self::defaultMailConfig();
                $schema = $c->make(ConfigInterface::class)->get(
                    'mail.variables',
                    $defaults['variables'] ?? [],
                );

                return new EmailTemplateService(
                    is_array($schema) ? $schema : [],
                    $c->make(EmailPartialRepository::class),
                    $c->make(Layout::class),
                );
            },
        );

        $this->container->singleton(
            Layout::class,
            static fn (ContainerInterface $c): Layout => new Layout(self::brand()),
        );

        $this->container->singleton(
            TemplateRenderer::class,
            static fn (ContainerInterface $c): TemplateRenderer => new TemplateRenderer($c->make(Layout::class)),
        );

        $this->container->singleton(UserDirectory::class, WpUserDirectory::class);

        $this->container->singleton(
            RecipientResolver::class,
            static fn (ContainerInterface $c): RecipientResolver => new RecipientResolver($c->make(UserDirectory::class)),
        );

        $this->container->singleton(
            MailDriver::class,
            static fn (ContainerInterface $c): MailDriver => new WpMailDriver($c->make(ConfigInterface::class)),
        );

        // EmailLogRepository autowires its data-layer dependencies (field driver, hydrator, executor).
        $this->container->singleton(EmailLogStore::class, EmailLogRepository::class);

        $this->container->singleton(
            MailService::class,
            static fn (ContainerInterface $c): MailService => new MailService(
                $c->make(MailDriver::class),
                $c->make(EmailLogStore::class),
                $c->make(HeaderGuard::class),
                $c->make(BootLogger::class),
            ),
        );

        // The immediate engine (sends inline). The queue worker also uses this.
        $this->container->singleton(
            RequestMailer::class,
            static fn (ContainerInterface $c): RequestMailer => new RequestMailer(
                $c->make(TemplateRegistry::class),
                $c->make(TemplateRenderer::class),
                $c->make(RecipientResolver::class),
                $c->make(MailService::class),
            ),
        );

        $this->container->singleton(
            MailQueueDispatcher::class,
            static fn (ContainerInterface $c): MailQueueDispatcher => new ActionSchedulerDispatcher(
                $c->make(RequestMailer::class),
            ),
        );

        // The corex-core Mailer seam → the queued decorator (detect-and-defer for Forms,
        // etc.). It queues only when Action Scheduler is present AND the mail_queue flag
        // is on; otherwise it sends inline, exactly as before.
        $this->container->singleton(
            Mailer::class,
            static fn (ContainerInterface $c): Mailer => new QueuedMailer(
                $c->make(RequestMailer::class),
                new MailQueueGate($c->make(FeatureFlags::class)),
                $c->make(MailQueueDispatcher::class),
            ),
        );

        $this->container->singleton(
            EmailStudioService::class,
            static function (ContainerInterface $c): EmailStudioService {
                return new EmailStudioService(
                    $c->make(DeliveryPolicy::class),
                    $c->make(CapturedEmailRepository::class),
                    $c->make(EmailAttemptRepository::class),
                    $c->make(MailDriver::class),
                    $c->make(EmailTemplateService::class),
                    'wp-mail',
                );
            },
        );
        $this->container->singleton(
            EmailRouteService::class,
            static fn (ContainerInterface $c): EmailRouteService => new EmailRouteService(
                $c->make(EmailRouteRepository::class),
                new EmailRouteDispatcher(
                    new EmailRouteMessageFactory(
                        $c->make(EmailTemplateRepository::class),
                        $c->make(EmailTemplateService::class),
                        $c->make(EmailLayoutRepository::class),
                    ),
                    $c->make(EmailStudioService::class),
                    $c->make(ConfigInterface::class),
                ),
            ),
        );
        $this->container->singleton(
            RoutedMailer::class,
            static fn (ContainerInterface $c): RoutedMailer => $c->make(EmailRouteService::class),
        );
        $this->container->singleton(EmailStudioRepositories::class);
        $this->container->singleton(EmailStudioSubmissionGateway::class);
        $this->container->singleton(
            SubmissionEmailGateway::class,
            static fn (ContainerInterface $c): EmailStudioSubmissionGateway => $c->make(EmailStudioSubmissionGateway::class),
        );
        $this->container->singleton(EmailStudioController::class);
    }

    public function boot(): void
    {
        add_action('init', [$this, 'registerEmailLogPostType']);
        add_action('init', [$this, 'registerEmailStudio']);
        add_action('rest_api_init', [$this, 'registerEmailStudioRoutes']);

        $this->container->make(TemplateRegistry::class)->register(
            $this->container->make(ContactNotificationTemplate::class),
        );

        // Hook the queue worker LAZILY. Resolving the dispatcher here (plugins_loaded)
        // would eagerly build the mail stack (RequestMailer → TemplateRenderer → Layout
        // → wp_get_global_settings) and load the `corex` textdomain before `init` — the
        // "translation triggered too early" notice. The handler resolves the dispatcher
        // only when a queued send actually fires (during queue processing, after init).
        add_action(ActionSchedulerDispatcher::HOOK, [$this, 'runQueuedSend'], 10, 1);
    }

    /**
     * Process one queued mail send. Resolves the dispatcher lazily so boot never builds
     * the mail stack; a no-op when Action Scheduler is not the bound dispatcher.
     *
     * @param array<string,mixed> $payload
     */
    public function runQueuedSend(array $payload): void
    {
        $dispatcher = $this->container->make(MailQueueDispatcher::class);

        if ($dispatcher instanceof ActionSchedulerDispatcher) {
            $dispatcher->handle($payload);
        }
    }

    /**
     * The non-public audit store for email sends.
     */
    public function registerEmailLogPostType(): void
    {
        register_post_type('corex_email_log', [
            'label'           => __('Email Log', 'corex'),
            'public'          => false,
            'show_ui'         => false,
            'supports'        => ['title'],
            'capability_type' => 'post',
        ]);
    }

    /**
     * Register private Studio persistence and idempotently install available layouts.
     */
    public function registerEmailStudio(): void
    {
        $this->container->make(WpEmailStudioStore::class)->registerPostType();
        if (get_option('corex_email_studio_defaults_version') === self::STUDIO_DEFAULTS_VERSION) {
            return;
        }

        $this->container->make(EmailLayoutRepository::class)->installDefaults(
            class_exists('WooCommerce'),
            get_current_user_id(),
            new \DateTimeImmutable('now'),
        );
        $this->container->make(EmailPartialRepository::class)->installDefaults(
            get_current_user_id(),
            new \DateTimeImmutable('now'),
        );
        update_option('corex_email_studio_defaults_version', self::STUDIO_DEFAULTS_VERSION, false);
    }

    public function registerEmailStudioRoutes(): void
    {
        $this->container->make(EmailStudioController::class)->register();
    }

    /**
     * The brand values for the email layout, read at runtime from the resolved
     * theme.json (which already includes any brand.json override via spec 006) — so
     * a rebrand flows into mail with no code change (FR-005).
     *
     * @return array{name:string,color:string,dir:string}
     */
    private static function brand(): array
    {
        $palette = (array) wp_get_global_settings(['color', 'palette']);
        $entries = $palette['theme'] ?? $palette['default'] ?? [];
        $color   = '';

        foreach ((array) $entries as $entry) {
            if (is_array($entry) && ($entry['slug'] ?? '') === 'primary') {
                $color = (string) ($entry['color'] ?? '');
                break;
            }
        }

        return [
            'name'  => (string) get_bloginfo('name'),
            'color' => $color,
            'dir'   => is_rtl() ? 'rtl' : 'ltr',
        ];
    }

    /** @return array<string,mixed> */
    private static function defaultMailConfig(): array
    {
        $defaults = require dirname(__DIR__) . '/config/mail.php';

        return is_array($defaults) ? $defaults : [];
    }
}
