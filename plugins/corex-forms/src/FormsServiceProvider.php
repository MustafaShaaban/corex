<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms;

defined('ABSPATH') || exit;

use Corex\Container\ContainerInterface;
use Corex\Events\ListenerProvider;
use Corex\Foundation\ServiceProvider;
use Corex\Forms\Forms\ContactForm;
use Corex\Forms\Listeners\SendEmailListener;
use Corex\Forms\Listeners\StoreSubmissionListener;
use Corex\Forms\Schema\SchemaResolver;
use Corex\Forms\Submission\FormSubmissionService;
use Corex\Forms\Submission\FormSubmittedEvent;
use Corex\Forms\Submission\SubmissionRepository;
use Corex\Forms\Submission\SubmitController;
use Corex\Forms\Validation\RuleRegistry;
use Corex\Forms\Validation\Validator;

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
        $this->container->singleton(StoreSubmissionListener::class);
        $this->container->singleton(SendEmailListener::class);
        $this->container->singleton(FormSubmissionService::class);
        $this->container->singleton(SubmitController::class);
    }

    public function boot(): void
    {
        $this->registerForms();
        $this->registerListeners();

        add_action('init', [$this, 'registerSubmissionPostType']);

        add_action('rest_api_init', function (): void {
            $this->container->make(SubmitController::class)->register();
        });
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
