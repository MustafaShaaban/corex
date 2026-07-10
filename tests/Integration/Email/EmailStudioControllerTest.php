<?php

/**
 * Integration coverage for Email Studio WordPress persistence and REST contracts (spec 068: FR-111–FR-125).
 *
 * @package Corex\Tests\Integration\Email
 */

declare(strict_types=1);

use Corex\Email\Capture\CapturedEmailRepository;
use Corex\Email\Delivery\DeliveryPolicy;
use Corex\Email\Delivery\EmailAttemptRepository;
use Corex\Email\Driver\MailDriver;
use Corex\Email\Message\EmailMessage;
use Corex\Email\Routing\EmailRouteRepository;
use Corex\Email\Studio\EmailLayoutRepository;
use Corex\Email\Studio\EmailPartialRepository;
use Corex\Email\Studio\EmailStudioController;
use Corex\Email\Studio\EmailStudioRepositories;
use Corex\Email\Studio\EmailStudioService;
use Corex\Email\Studio\EmailTemplateRepository;
use Corex\Email\Studio\EmailTemplateService;
use Corex\Email\Studio\WpEmailStudioStore;
use Corex\Support\Config\ConfigInterface;

beforeEach(function () {
    $this->store = new WpEmailStudioStore();
    $this->store->registerPostType();
    $this->baselineAssetIds = get_posts([
        'post_type'      => WpEmailStudioStore::POST_TYPE,
        'post_status'    => 'any',
        'posts_per_page' => 500,
        'fields'         => 'ids',
    ]);

    $this->templates = new EmailTemplateRepository($this->store);
    $this->layouts   = new EmailLayoutRepository($this->store);
    $this->partials  = new EmailPartialRepository($this->store);
    $this->routes    = new EmailRouteRepository($this->store);
    $this->captures  = new CapturedEmailRepository($this->store);
    $this->attempts  = new EmailAttemptRepository($this->store);
    $this->editor    = new EmailTemplateService([
        'user.name' => ['type' => 'text', 'label' => 'User name'],
    ]);
    $this->driver = new class implements MailDriver {
        public int $calls = 0;

        public function send(EmailMessage $message): bool
        {
            ++$this->calls;

            return true;
        }
    };
    $this->config = new class implements ConfigInterface {
        public function get(string $key, mixed $default = null): mixed
        {
            return match ($key) {
                'app.env'            => 'development',
                'mail.provider'      => 'postmark',
                'mail.live_delivery' => true,
                default              => $default,
            };
        }

        public function has(string $key): bool
        {
            return in_array($key, ['app.env', 'mail.provider', 'mail.live_delivery'], true);
        }
    };
    $this->studio = new EmailStudioService(
        new DeliveryPolicy(),
        $this->captures,
        $this->attempts,
        $this->driver,
        $this->editor,
        'postmark',
    );
    $this->controller = new EmailStudioController(
        new EmailStudioRepositories(
            $this->templates,
            $this->layouts,
            $this->partials,
            $this->routes,
            $this->captures,
            $this->attempts,
        ),
        $this->studio,
        $this->editor,
        $this->config,
    );

    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));
});

afterEach(function () {
    $ids = get_posts([
        'post_type'      => WpEmailStudioStore::POST_TYPE,
        'post_status'    => 'any',
        'posts_per_page' => 500,
        'fields'         => 'ids',
    ]);
    foreach (array_diff($ids, $this->baselineAssetIds) as $id) {
        wp_delete_post((int) $id, true);
    }
});

it('registers the Email Studio collection mutation test health and resend routes', function () {
    add_action('rest_api_init', [$this->controller, 'register']);
    do_action('rest_api_init', rest_get_server());
    $routes = rest_get_server()->get_routes();

    expect($routes)->toHaveKey('/corex/v1/email-studio')
        ->and($routes)->toHaveKey('/corex/v1/email-studio/templates')
        ->and($routes)->toHaveKey('/corex/v1/email-studio/templates/(?P<id>\d+)')
        ->and($routes)->toHaveKey('/corex/v1/email-studio/templates/(?P<id>\d+)/draft')
        ->and($routes)->toHaveKey('/corex/v1/email-studio/templates/(?P<id>\d+)/activate')
        ->and($routes)->toHaveKey('/corex/v1/email-studio/templates/(?P<id>\d+)/health')
        ->and($routes)->toHaveKey('/corex/v1/email-studio/layouts')
        ->and($routes)->toHaveKey('/corex/v1/email-studio/partials')
        ->and($routes)->toHaveKey('/corex/v1/email-studio/routes')
        ->and($routes)->toHaveKey('/corex/v1/email-studio/test')
        ->and($routes)->toHaveKey('/corex/v1/email-studio/attempts/(?P<attempt>[0-9a-f-]+)/resend');
});

it('persists creates drafts and activation through the REST boundary', function () {
    $this->layouts->installDefaults(false, get_current_user_id(), new DateTimeImmutable());
    $layout = $this->layouts->find('transactional');

    $create = new WP_REST_Request('POST', '/corex/v1/email-studio/templates');
    $create->set_body_params(['slug' => 'integration-welcome', 'name' => 'Integration Welcome']);
    $created = $this->controller->createTemplate($create);
    $templateId = (int) $created->get_data()['data']['template']['id'];

    $draft = new WP_REST_Request('POST', '/corex/v1/email-studio/templates/' . $templateId . '/draft');
    $draft->set_url_params(['id' => $templateId]);
    $draft->set_body_params([
        'subject'        => 'Welcome {{ user.name }}',
        'from_name'      => 'CoreX',
        'from_address'   => 'hello@example.com',
        'html_body'      => '<p>Hello {{ user.name }}</p>',
        'plain_text'     => 'Hello {{ user.name }}',
        'plain_text_mode'=> 'manual',
        'layout_id'      => $layout?->id,
        'layout_version' => $layout?->version,
        'variable_keys'  => ['user.name'],
    ]);
    $saved = $this->controller->saveDraft($draft);

    $activate = new WP_REST_Request('POST', '/corex/v1/email-studio/templates/' . $templateId . '/activate');
    $activate->set_url_params(['id' => $templateId]);
    $activate->set_body_params(['version' => 1]);
    $active = $this->controller->activateTemplate($activate);

    expect($created->get_status())->toBe(201)
        ->and($saved->get_status())->toBe(201)
        ->and($active->get_data()['data']['template']['status'])->toBe('active')
        ->and((new EmailTemplateRepository(new WpEmailStudioStore()))->find($templateId)?->activeVersion)->toBe(1);

    $detail = new WP_REST_Request('GET', '/corex/v1/email-studio/templates/' . $templateId);
    $detail->set_url_params(['id' => $templateId]);
    $detailResponse = $this->controller->showTemplate($detail);
    $overviewResponse = $this->controller->index(new WP_REST_Request('GET', '/corex/v1/email-studio'));
    $summary = current(array_filter(
        $overviewResponse->get_data()['data']['templates'],
        static fn (array $candidate): bool => (int) $candidate['id'] === $templateId,
    ));
    expect($detailResponse->get_data()['data']['versions'])->toHaveCount(1)
        ->and($detailResponse->get_data()['data']['versions'][0]['subject'])->toBe('Welcome {{ user.name }}')
        ->and($summary['subject'])->toBe('Welcome {{ user.name }}')
        ->and($summary['updated_at'])->not->toBe('')
        ->and($overviewResponse->get_data()['data']['variables'])->toHaveKeys(['Recipient']);
});

it('rejects a draft that points to an unavailable layout revision', function () {
    $template = $this->templates->create('invalid-layout', 'Invalid layout', get_current_user_id(), new DateTimeImmutable());
    $draft = new WP_REST_Request('POST', '/corex/v1/email-studio/templates/' . $template->id . '/draft');
    $draft->set_url_params(['id' => $template->id]);
    $draft->set_body_params([
        'subject'         => 'Welcome',
        'from_name'       => 'CoreX',
        'from_address'    => 'hello@example.com',
        'html_body'       => '<p>Hello</p>',
        'plain_text'      => 'Hello',
        'plain_text_mode' => 'manual',
        'layout_id'       => 999999,
        'layout_version'  => 1,
        'variable_keys'   => [],
    ]);

    $response = $this->controller->saveDraft($draft);

    expect($response->get_status())->toBe(422)
        ->and($response->get_data()['details']['fields']['layout_id'])
        ->toBe('Choose an available email layout revision.');
});

it('persists editable layouts partials and routing through REST', function () {
    $layout = new WP_REST_Request('POST', '/corex/v1/email-studio/layouts');
    $layout->set_body_params([
        'slug'    => 'campaign',
        'name'    => 'Campaign',
        'regions' => [
            'header' => '<header>Brand</header>',
            'accent' => '#2271b1',
            'body'   => '<main>{{ content }}</main>',
            'button' => '<a href="https://example.com">Continue</a>',
            'footer' => '<footer>Unsubscribe</footer>',
        ],
    ]);
    $layoutResponse = $this->controller->saveLayout($layout);

    $partial = new WP_REST_Request('POST', '/corex/v1/email-studio/partials');
    $partial->set_body_params([
        'slug'       => 'signature',
        'name'       => 'Signature',
        'kind'       => 'custom',
        'html_body'  => '<p>CoreX Team</p>',
        'plain_text' => 'CoreX Team',
        'status'     => 'active',
    ]);
    $partialResponse = $this->controller->savePartial($partial);

    $template = $this->templates->create('route-template', 'Route template', get_current_user_id(), new DateTimeImmutable());
    $route = new WP_REST_Request('POST', '/corex/v1/email-studio/routes');
    $route->set_body_params([
        'trigger'         => 'forms.contact.submitted',
        'flow_id'        => 12,
        'template_id'     => $template->id,
        'template_version_policy' => 'active',
        'recipient_rules' => [['source' => 'literal', 'value' => 'owner@example.com']],
        'priority'        => 20,
        'enabled'         => true,
    ]);
    $routeResponse = $this->controller->saveRoute($route);

    expect($layoutResponse->get_status())->toBe(201)
        ->and($layoutResponse->get_data()['data']['layout']['version'])->toBe(1)
        ->and($partialResponse->get_status())->toBe(201)
        ->and($partialResponse->get_data()['data']['partial']['version'])->toBe(1)
        ->and($routeResponse->get_status())->toBe(200)
        ->and($this->routes->findByTrigger('forms.contact.submitted')?->templateId)->toBe($template->id)
        ->and($this->routes->findByTrigger('forms.contact.submitted')?->flowId)->toBe(12)
        ->and($this->routes->findByTrigger('forms.contact.submitted')?->priority)->toBe(20)
        ->and($this->routes->findByTrigger('forms.contact.submitted')?->templateVersionPolicy)->toBe('active');
});

it('rejects a route that points to an unavailable template', function () {
    $route = new WP_REST_Request('POST', '/corex/v1/email-studio/routes');
    $route->set_body_params([
        'trigger'         => 'forms.missing.submitted',
        'template_id'     => 999999,
        'recipient_rules' => [['source' => 'literal', 'value' => 'owner@example.com']],
        'enabled'         => true,
    ]);

    $response = $this->controller->saveRoute($route);

    expect($response->get_status())->toBe(422)
        ->and($response->get_data()['message'])->toBe('Choose an available email template.');
});

it('captures Development test sends and exposes truthful persisted overview counts', function () {
    $request = new WP_REST_Request('POST', '/corex/v1/email-studio/test');
    $request->set_body_params([
        'to'      => ['sam@example.com'],
        'subject' => 'Integration test',
        'body'    => '<p>Hello</p>',
    ]);

    $sent     = $this->controller->testSend($request);
    $overview = $this->controller->index(new WP_REST_Request('GET', '/corex/v1/email-studio'));
    $data     = $overview->get_data()['data'];
    $attemptId = $sent->get_data()['data']['result']['attempt_id'];
    $attempt = current(array_filter(
        $data['attempts'],
        static fn (array $candidate): bool => $candidate['attempt_id'] === $attemptId,
    ));
    $capture = current(array_filter(
        $data['captures'],
        static fn (array $candidate): bool => $candidate['attempt_id'] === $attemptId,
    ));

    expect($sent->get_data()['data']['result']['state'])->toBe('captured')
        ->and($this->driver->calls)->toBe(0)
        ->and($data['delivery']['environment'])->toBe('development')
        ->and($data['counts']['captures'])->toBeGreaterThanOrEqual(1)
        ->and($data['counts']['attempts'])->toBeGreaterThanOrEqual(1)
        ->and($attempt['provider_event'])->toBe('captured')
        ->and($attempt['error_code'])->toBeNull()
        ->and($capture['plain_text'])->toBe('Hello')
        ->and(array_column($data['recent_test_sends'], 'attempt_id'))->toContain($attemptId)
        ->and($data)->toHaveKeys(['health', 'variables']);
});

it('requires administrator capability and a REST nonce for mutations', function () {
    $request = new WP_REST_Request('POST', '/corex/v1/email-studio/templates');
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));

    expect($this->controller->canManage())->toBeTrue()
        ->and($this->controller->canMutate($request))->toBeTrue();

    $request->set_header('X-WP-Nonce', 'invalid');
    expect($this->controller->canMutate($request))->toBeFalse();

    wp_set_current_user(0);
    expect($this->controller->canManage())->toBeFalse();
});
