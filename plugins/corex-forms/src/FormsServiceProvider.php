<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockMap;
use Corex\Blocks\DynamicBlockRegistrar;
use Corex\Container\ContainerInterface;
use Corex\Events\ListenerProvider;
use Corex\Foundation\ServiceProvider;
use Corex\Forms\Block\FormBlockRenderer;
use Corex\Forms\Block\FlowBlockRenderer;
use Corex\Forms\Block\ProtectedFormRegistry;
use Corex\Forms\Catalog\FormCatalog;
use Corex\Forms\Catalog\SubmissionCounts;
use Corex\Forms\Forms\ContactForm;
use Corex\Forms\Flow\FlowRepository;
use Corex\Forms\Flow\FlowService;
use Corex\Forms\Flow\FlowStore;
use Corex\Forms\Flow\FlowActionRegistry;
use Corex\Forms\Flow\FlowBehaviorRegistries;
use Corex\Forms\Flow\FlowController;
use Corex\Forms\Flow\FlowControllerServices;
use Corex\Forms\Flow\FlowConfigurationValidator;
use Corex\Forms\Flow\FlowExtensionCatalog;
use Corex\Forms\Flow\FlowRestGateway;
use Corex\Forms\Flow\FlowRestMapper;
use Corex\Forms\Flow\FlowRestInputMapper;
use Corex\Forms\Flow\FlowRestPresenter;
use Corex\Forms\Flow\EmailVariableRegistry;
use Corex\Forms\Flow\WpFlowStore;
use Corex\Forms\Listeners\SendEmailListener;
use Corex\Forms\Listeners\StoreSubmissionListener;
use Corex\Forms\Schema\SchemaResolver;
use Corex\Forms\Schema\FieldTypeRegistry;
use Corex\Forms\Routing\RoutingService;
use Corex\Forms\Submission\FormSubmissionService;
use Corex\Forms\Submission\FormSubmissionPipeline;
use Corex\Forms\Submission\FlowEmailSender;
use Corex\Forms\Submission\NotificationDispatcher;
use Corex\Forms\Submission\FlowEmailAddressResolver;
use Corex\Forms\Submission\FormChallengeContextFactory;
use Corex\Forms\Submission\FlowSchemaFactory;
use Corex\Forms\Submission\FlowTestService;
use Corex\Forms\Submission\FlowSubmissionController;
use Corex\Forms\Submission\FlowVisitorSubmissionService;
use Corex\Forms\Submission\FormSubmittedEvent;
use Corex\Forms\Submission\FormsListController;
use Corex\Forms\Submission\SubmissionRepository;
use Corex\Forms\Submission\SubmitController;
use Corex\Forms\Submission\Stages\EmailStage;
use Corex\Forms\Submission\Stages\InboxStage;
use Corex\Forms\Submission\Stages\ProtectionStage;
use Corex\Forms\Submission\Stages\RoutingStage;
use Corex\Forms\Submission\Stages\StorageStage;
use Corex\Forms\Submission\Stages\TimelineStage;
use Corex\Forms\Submission\Stages\ValidationStage;
use Corex\Forms\Validation\RuleRegistry;
use Corex\Forms\Validation\Validator;
use Corex\Forms\Success\SuccessStateRegistry;
use Corex\Support\Config\ConfigInterface;
use Corex\Mail\Mailer;
use Corex\Mail\RoutedMailer;
use Corex\Mail\MailTemplateCatalog;
use Corex\Security\ChallengeVerifier;
use Corex\Security\Upload\AttachmentStorage;
use Corex\Security\Upload\AttachmentStore;
use Corex\Security\Upload\UploadValidator;

/**
 * Boots the forms engine: binds the headless cores (schema resolver, validator,
 * rule registry) and the submission lifecycle (registry, service, repository,
 * listeners, controller), then on the boot pass wires the WordPress boundary —
 * the registered forms, their listeners, the submission CPT, and the REST route.
 */
final class FormsServiceProvider extends ServiceProvider
{
    /**
     * What a form may accept when its own field does not narrow it (spec 081).
     *
     * Documents and images, nothing executable. A framework whose default upload policy accepted
     * whatever the server's mime map allowed would be handing every site a file-drop endpoint it
     * did not ask for, and the first person to notice would be whoever found the webshell.
     *
     * @var array<string,list<string>>
     */
    private const UPLOAD_TYPES = [
        'application/pdf' => ['pdf'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
    ];

    /** 10 MB. A form that needs more says so with `max_size:`. */
    private const UPLOAD_MAX_BYTES = 10 * 1024 * 1024;

    public function register(): void
    {
        $this->container->singleton(RuleRegistry::class);
        $this->container->singleton(FieldTypeRegistry::class);
        $this->container->singleton(RoutingService::class);
        $this->container->singleton(FlowActionRegistry::class);
        $this->container->singleton(EmailVariableRegistry::class);
        $this->container->singleton(SuccessStateRegistry::class);
        $this->container->singleton(FlowBehaviorRegistries::class);
        $this->container->singleton(
            FlowExtensionCatalog::class,
            static fn (ContainerInterface $c): FlowExtensionCatalog => new FlowExtensionCatalog(
                $c->make(FieldTypeRegistry::class),
                $c->make(RuleRegistry::class),
                $c->make(FlowBehaviorRegistries::class),
                $c->has(MailTemplateCatalog::class) ? $c->make(MailTemplateCatalog::class) : null,
            ),
        );
        $this->container->singleton(FlowRestGateway::class);
        $this->container->singleton(FlowRestInputMapper::class);
        $this->container->singleton(FlowRestPresenter::class);
        $this->container->singleton(FlowRestMapper::class);
        $this->container->singleton(WpFlowStore::class);
        $this->container->singleton(
            FlowStore::class,
            static fn (ContainerInterface $c): FlowStore => $c->make(WpFlowStore::class),
        );
        $this->container->singleton(FlowRepository::class);
        $this->container->singleton(FlowConfigurationValidator::class);
        $this->container->singleton(FlowService::class);
        $this->container->singleton(FlowControllerServices::class);
        $this->container->singleton(FlowController::class);

        $this->container->singleton(
            SchemaResolver::class,
            static fn (ContainerInterface $c): SchemaResolver => new SchemaResolver($c->make(RuleRegistry::class)),
        );

        $this->container->singleton(
            Validator::class,
            static fn (ContainerInterface $c): Validator => new Validator($c->make(RuleRegistry::class)),
        );

        // Submission lifecycle — autowired from the bindings above plus the core
        // event seam, data layer, and middleware pipeline.
        $this->container->singleton(FormRegistry::class);

        // The one list of every form CoreX knows about, whatever its source (spec 074, FR-1).
        // Bound here rather than in corex-config because both of its inputs live in this plugin;
        // the submission counter is optional, so a consumer that has none still gets a catalog —
        // it just reports each count as unavailable rather than inventing a zero.
        $this->container->singleton(
            FormCatalog::class,
            static fn (ContainerInterface $c): FormCatalog => new FormCatalog(
                $c->make(FlowRepository::class),
                $c->make(FormRegistry::class),
                $c->has(SubmissionCounts::class) ? $c->make(SubmissionCounts::class) : null,
            ),
        );

        $this->container->singleton(SubmissionRepository::class);
        $this->container->singleton(FlowSchemaFactory::class);
        $this->container->singleton(FlowEmailAddressResolver::class);
        $this->container->singleton(ValidationStage::class);
        $this->container->singleton(
            FormChallengeContextFactory::class,
            static fn (ContainerInterface $c): FormChallengeContextFactory => new FormChallengeContextFactory(
                $c->make(ConfigInterface::class),
            ),
        );
        $this->container->singleton(
            ProtectionStage::class,
            static fn (ContainerInterface $c): ProtectionStage => new ProtectionStage(
                $c->has(ChallengeVerifier::class) ? $c->make(ChallengeVerifier::class) : null,
                $c->make(FormChallengeContextFactory::class),
            ),
        );
        $this->container->singleton(StorageStage::class);
        $this->container->singleton(RoutingStage::class);
        $this->container->singleton(
            FlowEmailSender::class,
            static fn (ContainerInterface $c): FlowEmailSender => new FlowEmailSender(
                $c->has(RoutedMailer::class) ? $c->make(RoutedMailer::class) : null,
                $c->make(FlowEmailAddressResolver::class),
            ),
        );
        // The shared detect-and-defer ladder: its optional mailers are resolved conditionally so a
        // site without CoreX Mail still reaches the wp_mail() floor (FR-017).
        $this->container->singleton(
            NotificationDispatcher::class,
            static fn (ContainerInterface $c): NotificationDispatcher => new NotificationDispatcher(
                $c->has(RoutedMailer::class) ? $c->make(RoutedMailer::class) : null,
                $c->has(Mailer::class) ? $c->make(Mailer::class) : null,
            ),
        );
        $this->container->singleton(EmailStage::class);
        $this->container->singleton(InboxStage::class);
        $this->container->singleton(TimelineStage::class);
        $this->container->singleton(
            FormSubmissionPipeline::class,
            static fn (ContainerInterface $c): FormSubmissionPipeline => new FormSubmissionPipeline([
                $c->make(ValidationStage::class),
                $c->make(ProtectionStage::class),
                $c->make(StorageStage::class),
                $c->make(RoutingStage::class),
                $c->make(EmailStage::class),
                $c->make(InboxStage::class),
                $c->make(TimelineStage::class),
            ]),
        );
        $this->container->singleton(FlowTestService::class);
        $this->container->singleton(FlowVisitorSubmissionService::class);
        $this->container->singleton(FlowSubmissionController::class);
        // The submission persistence seam (spec 045): the post-meta repository is the default driver.
        $this->container->singleton(
            \Corex\Forms\Submission\SubmissionStore::class,
            static fn (ContainerInterface $c): SubmissionRepository => $c->make(SubmissionRepository::class),
        );
        $this->container->singleton(StoreSubmissionListener::class);
        $this->container->singleton(
            SendEmailListener::class,
            static fn (ContainerInterface $c): SendEmailListener => new SendEmailListener(
                $c->make(NotificationDispatcher::class),
                $c->make(ConfigInterface::class),
            ),
        );
        // The attachment store is built here rather than autowired: it needs an UploadValidator,
        // and that needs an allow-list and a size cap, which are policy rather than dependencies.
        // The defaults are deliberately narrow — a form declaring `mime:` widens them per field,
        // and a framework that accepted anything by default would be handing every site an upload
        // endpoint they did not ask for (spec 081).
        $this->container->singleton(
            AttachmentStorage::class,
            static fn (): AttachmentStorage => new AttachmentStore(
                new UploadValidator(self::UPLOAD_TYPES, self::UPLOAD_MAX_BYTES),
            ),
        );
        $this->container->singleton(
            FormSubmissionService::class,
            static fn (ContainerInterface $c): FormSubmissionService => new FormSubmissionService(
                $c->make(\Corex\Forms\FormRegistry::class),
                $c->make(\Corex\Forms\Schema\SchemaResolver::class),
                $c->make(\Corex\Forms\Validation\Validator::class),
                $c->make(\Corex\Events\EventDispatcher::class),
                $c->make(AttachmentStorage::class),
            ),
        );
        $this->container->singleton(SubmitController::class);
        $this->container->singleton(FormsListController::class);
        // Request-scoped: one registry per page render, shared between the renderer that declares
        // protected forms and the captcha asset controller that reads them at footer time.
        $this->container->singleton(ProtectedFormRegistry::class);
        $this->container->singleton(FlowBlockRenderer::class);
        $this->container->singleton(FormBlockRenderer::class);
    }

    public function boot(): void
    {
        $this->registerForms();
        $this->registerListeners();

        add_action('init', [$this, 'registerSubmissionPostType']);
        add_action('init', [$this, 'registerFlowPostType']);
        add_action('init', [$this, 'registerFormBlock']);

        add_action('rest_api_init', function (): void {
            $this->container->make(SubmitController::class)->register();
            $this->container->make(FormsListController::class)->register();
            $this->container->make(FlowController::class)->register();
            $this->container->make(FlowSubmissionController::class)->register();
        });
    }

    /**
     * Discover and register the form block. Its view script + style are declared in
     * block.json, so WordPress loads them only on pages where the block renders (FR-014).
     */
    public function registerFormBlock(): void
    {
        $registrar = $this->container->make(DynamicBlockRegistrar::class);
        $built = dirname(__DIR__) . '/build/blocks';
        $blocksDir = is_dir($built) ? $built : __DIR__ . '/Block/blocks';

        foreach ($this->container->make(BlockMap::class)->discover($blocksDir) as $block) {
            $registrar->register($block);
        }
    }

    /**
     * The non-public store for submissions. Querying/admin viewing is out of scope.
     */
    public function registerSubmissionPostType(): void
    {
        register_post_type('corex_submission', [
            'label'           => __('Form Submissions', 'corex'),
            'public'          => false,
            'show_ui'         => false,
            'supports'        => ['title'],
            'capability_type' => 'post',
        ]);
    }

    public function registerFlowPostType(): void
    {
        $this->container->make(WpFlowStore::class)->registerPostType();
    }

    private function registerForms(): void
    {
        $this->container->make(FormRegistry::class)->register($this->container->make(ContactForm::class));
    }

    /**
     * One listener on the shared event, which runs the listeners of the form that was actually
     * submitted.
     *
     * This used to walk every registered form at boot and register each distinct listener id once,
     * **deduplicated across all forms**, onto the shared `FormSubmittedEvent`. The resulting list
     * was global: as soon as any one form declared a listener, that listener ran for every
     * submission on the site. `Form::listeners()` documents itself as overridable, and overriding
     * it to *remove* a listener did nothing at all — a site replacing the built-in notification
     * with its own sent two emails per submission. Reported from a real build (issue #138, item 1).
     *
     * Resolution stays lazy for the reason the original was: the listener graph reaches the mail
     * stack, including the optional Email Studio router, and building it at boot loads translations
     * before `init`. Listeners are singletons, so a listener shared by several forms is still
     * constructed once.
     *
     * A slug with no registered `Form` — a database-defined flow rather than a code-defined form —
     * matches nothing and is skipped, which is the correct answer rather than a fallback to
     * "everything".
     */
    private function registerListeners(): void
    {
        $container = $this->container;

        $this->container->make(ListenerProvider::class)->listen(
            FormSubmittedEvent::class,
            static function (FormSubmittedEvent $event) use ($container): void {
                $form = $container->make(FormRegistry::class)->find($event->formSlug);

                if ($form === null) {
                    return;
                }

                foreach ($form->listeners() as $listenerId) {
                    ($container->make($listenerId))($event);
                }
            },
        );
    }
}
