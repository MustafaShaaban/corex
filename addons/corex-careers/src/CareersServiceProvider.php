<?php

/**
 * @package Corex\Careers
 */

declare(strict_types=1);

namespace Corex\Careers;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockMap;
use Corex\Blocks\DynamicBlockRegistrar;
use Corex\Captcha\Captcha;
use Corex\Careers\Application\ApplicationRepository;
use Corex\Careers\Application\ApplicationService;
use Corex\Careers\Application\ApplicationStore;
use Corex\Careers\Application\WpApplicationStore;
use Corex\Careers\Block\JobProvider;
use Corex\Careers\Block\WpJobProvider;
use Corex\Careers\Templates\ApplicationReceivedTemplate;
use Corex\Careers\Templates\NewApplicationTemplate;
use Corex\Container\ContainerInterface;
use Corex\Database\Schema\Migrator;
use Corex\Database\Schema\Table;
use Corex\Email\Template\TemplateRegistry;
use Corex\Foundation\ServiceProvider;
use Corex\Mail\Mailer;
use Corex\Security\Upload\UploadValidator;
use Corex\Support\Config\ConfigInterface;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Wires careers: the job CPT + taxonomies, the applications table, the corex/jobs
 * block, the application service, the apply REST route, and the email templates.
 */
final class CareersServiceProvider extends ServiceProvider
{
    private const CV_TYPES = [
        'application/pdf' => ['pdf'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
    ];

    public function register(): void
    {
        $this->container->singleton(ApplicationRepository::class);
        $this->container->singleton(
            ApplicationStore::class,
            static fn (ContainerInterface $c): ApplicationStore => new WpApplicationStore($c->make(ApplicationRepository::class)),
        );
        $this->container->singleton(JobProvider::class, WpJobProvider::class);

        $this->container->singleton(
            ApplicationService::class,
            static function (ContainerInterface $c): ApplicationService {
                $config  = $c->make(ConfigInterface::class);
                $hrEmail = (string) ($config->get('careers.hr_email') ?: get_option('admin_email'));

                return new ApplicationService(
                    $c->make(ApplicationStore::class),
                    new UploadValidator(self::CV_TYPES, 5 * 1024 * 1024),
                    $c->make(Mailer::class),
                    $hrEmail,
                );
            },
        );
    }

    public function boot(): void
    {
        add_action('init', [$this, 'install']);
        add_action('rest_api_init', [$this, 'registerRoute']);
    }

    public function install(): void
    {
        register_post_type('corex_job', [
            'label'        => __('Jobs', 'corex'),
            'public'       => true,
            'has_archive'  => true,
            'show_in_rest' => true,
            'supports'     => ['title', 'editor'],
            'rewrite'      => ['slug' => 'jobs'],
            'menu_icon'    => 'dashicons-businessperson',
        ]);

        foreach (['job_department' => __('Departments', 'corex'), 'job_location' => __('Locations', 'corex'), 'job_type' => __('Types', 'corex')] as $taxonomy => $label) {
            register_taxonomy($taxonomy, 'corex_job', [
                'label'        => $label,
                'public'       => true,
                'hierarchical' => false,
                'show_in_rest' => true,
            ]);
        }

        $this->container->make(Migrator::class)->create(
            (new Table('applications'))
                ->id()->integer('job_id')->string('name')->string('email')->text('cover_letter')
                ->integer('cv_attachment')->string('status', 20)->timestamps()
        );

        $registrar = $this->container->make(DynamicBlockRegistrar::class);
        foreach ($this->container->make(BlockMap::class)->discover(dirname(__DIR__) . '/blocks') as $block) {
            $registrar->register($block);
        }

        if ($this->container->has(TemplateRegistry::class)) {
            $registry = $this->container->make(TemplateRegistry::class);
            $registry->register(new NewApplicationTemplate());
            $registry->register(new ApplicationReceivedTemplate());
        }
    }

    public function registerRoute(): void
    {
        register_rest_route('corex/v1', '/careers/apply', [
            'methods'             => 'POST',
            'permission_callback' => '__return_true', // public; honeypot + captcha gate it
            'callback'            => [$this, 'apply'],
        ]);
    }

    public function apply(WP_REST_Request $request): WP_REST_Response
    {
        if (trim((string) $request['corex_hp']) !== '') {
            return new WP_REST_Response(['ok' => false], 422);
        }

        if ($this->container->has(Captcha::class)
            && ! $this->container->make(Captcha::class)->verify((string) $request['captcha_token'])) {
            return new WP_REST_Response(['ok' => false, 'error' => 'captcha'], 422);
        }

        $fields = [
            'name'         => sanitize_text_field((string) $request['name']),
            'email'        => sanitize_email((string) $request['email']),
            'cover_letter' => sanitize_textarea_field((string) $request['cover_letter']),
        ];

        // The CV descriptor is validated by the service; a production handler also moves
        // the validated file via wp_handle_upload to a protected location.
        $cv = isset($_FILES['cv']) ? array_map('sanitize_text_field', wp_unslash($_FILES['cv'])) : [];

        $result = $this->container->make(ApplicationService::class)->apply((int) $request['job_id'], $fields, $cv);

        return new WP_REST_Response(['ok' => $result->stored, 'error' => $result->reason], $result->stored ? 200 : 422);
    }
}
