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
use Corex\Forms\Submission\FlowEmailAddressResolver;
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
use Corex\Mail\RoutedMailer;
use Corex\Mail\MailTemplateCatalog;
use Corex\Security\ChallengeVerifier;

/**
 * Boots the forms engine: binds the headless cores (schema resolver, validator,
 * rule registry) and the submission lifecycle (registry, service, repository,
 * listeners, controller), then on the boot pass wires the WordPress boundary —
 * the registered forms, their listeners, the submission CPT, and the REST route.
 */
final class FormsServiceProvider extends ServiceProvider
{
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
        $this->container->singleton(SubmissionRepository::class);
        $this->container->singleton(FlowSchemaFactory::class);
        $this->container->singleton(FlowEmailAddressResolver::class);
        $this->container->singleton(ValidationStage::class);
        $this->container->singleton(
            ProtectionStage::class,
            static fn (ContainerInterface $c): ProtectionStage => new ProtectionStage(
                $c->has(ChallengeVerifier::class) ? $c->make(ChallengeVerifier::class) : null,
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
            static fn (ContainerInterface $c): SendEmailListener => new SendEmailListener($c, $c->make(ConfigInterface::class)),
        );
        $this->container->singleton(FormSubmissionService::class);
        $this->container->singleton(SubmitController::class);
        $this->container->singleton(FormsListController::class);
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
     * Register each form's listeners on the shared provider once (deduplicated),
     * so a submission's FormSubmittedEvent reaches the store + email listeners.
     */
    private function registerListeners(): void
    {
        $provider = $this->container->make(ListenerProvider::class);
        $registered = [];

        foreach ($this->container->make(FormRegistry::class)->all() as $form) {
            foreach ($form->listeners() as $listenerId) {
                if (isset($registered[$listenerId])) {
                    continue;
                }

                $registered[$listenerId] = true;
                $provider->listen(FormSubmittedEvent::class, $this->container->make($listenerId));
            }
        }
    }
}
