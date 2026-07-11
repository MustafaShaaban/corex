<?php

/**
 * Integration coverage for routed Email Studio capture and provider delivery (spec 068: SC-010).
 *
 * @package Corex\Tests\Integration\Mail
 */

declare(strict_types=1);

use Corex\Email\Capture\CapturedEmailRepository;
use Corex\Email\Delivery\DeliveryPolicy;
use Corex\Email\Delivery\EmailAttemptRepository;
use Corex\Email\Driver\MailDriver;
use Corex\Email\Message\EmailMessage;
use Corex\Email\Routing\EmailRouteDispatcher;
use Corex\Email\Routing\EmailRouteMessageFactory;
use Corex\Email\Routing\EmailRouteRepository;
use Corex\Email\Routing\EmailRouteService;
use Corex\Email\Routing\EmailRoute;
use Corex\Email\Studio\EmailStudioService;
use Corex\Email\Studio\EmailTemplateRepository;
use Corex\Email\Studio\EmailTemplateService;
use Corex\Email\Studio\EmailTemplateVersion;
use Corex\Email\Studio\WpEmailStudioStore;
use Corex\Mail\MailResult;
use Corex\Support\Config\ConfigInterface;

function emailLifecycleConfig(string $environment, string $provider = '', bool $live = false): ConfigInterface
{
    return new class($environment, $provider, $live) implements ConfigInterface {
        public function __construct(
            private readonly string $environment,
            private readonly string $provider,
            private readonly bool $live,
        ) {
        }

        public function get(string $key, mixed $default = null): mixed
        {
            return match ($key) {
                'app.env'            => $this->environment,
                'mail.provider'      => $this->provider,
                'mail.live_delivery' => $this->live,
                default              => $default,
            };
        }

        public function has(string $key): bool
        {
            return in_array($key, ['app.env', 'mail.provider', 'mail.live_delivery'], true);
        }
    };
}

function emailLifecycleRouter(
    EmailRouteRepository $routes,
    EmailTemplateRepository $templates,
    EmailTemplateService $editor,
    EmailStudioService $studio,
    ConfigInterface $config,
): EmailRouteService {
    return new EmailRouteService(
        $routes,
        new EmailRouteDispatcher(
            new EmailRouteMessageFactory($templates, $editor),
            $studio,
            $config,
        ),
    );
}

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
    $this->routes    = new EmailRouteRepository($this->store);
    $this->captures  = new CapturedEmailRepository($this->store);
    $this->attempts  = new EmailAttemptRepository($this->store);
    $this->editor    = new EmailTemplateService([
        'user.name'  => ['type' => 'text', 'label' => 'User name'],
        'user.email' => ['type' => 'email', 'label' => 'User email'],
    ]);
    $this->driver = new class implements MailDriver {
        public int $calls = 0;

        public function send(EmailMessage $message): bool
        {
            ++$this->calls;

            return true;
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

    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    $actorId = (int) ($administrators[0] ?? 1);
    $now = new DateTimeImmutable('2026-07-03T13:00:00+00:00');
    $template = $this->templates->create('access-approved-integration', 'Access approved integration', $actorId, $now);
    $version = $this->templates->saveDraft(new EmailTemplateVersion(
        id: 1,
        templateId: $template->id,
        versionNumber: 1,
        subject: 'Access approved for {{ user.name }}',
        fromName: 'CoreX',
        fromAddress: 'hello@example.com',
        htmlBody: '<p>Hello {{ user.name }}</p>',
        plainText: 'Hello {{ user.name }}',
        plainTextMode: 'manual',
        layoutId: 1,
        layoutVersion: 1,
        variableKeys: ['user.name'],
        createdBy: $actorId,
        createdAt: $now,
        checksum: hash('sha256', 'access-approved-integration'),
    ));
    $this->templates->activate($template->id, $version->versionNumber, $actorId, $now);
    $this->routes->save(new EmailRoute(
        id: 0,
        trigger: 'access.request.approved.integration',
        templateId: $template->id,
        recipientRules: [['source' => 'context', 'path' => 'user.email']],
        replyToRule: null,
        enabled: true,
        updatedBy: $actorId,
        updatedAt: $now,
    ));
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

it('captures a routed Development notification and persists its exact active template result', function () {
    $router = emailLifecycleRouter(
        $this->routes,
        $this->templates,
        $this->editor,
        $this->studio,
        emailLifecycleConfig('development', 'postmark', true),
    );

    $result = $router->dispatch('access.request.approved.integration', [
        'user' => ['name' => 'Sam', 'email' => 'sam@example.com'],
    ]);
    $attempt = $this->attempts->latest()[0];
    $route = $this->routes->findByTrigger('access.request.approved.integration');

    expect($result?->state)->toBe(MailResult::STATE_CAPTURED)
        ->and($this->driver->calls)->toBe(0)
        ->and($this->captures->latest())->toHaveCount(1)
        ->and($this->captures->latest()[0]->subject)->toBe('Access approved for Sam')
        ->and($this->captures->latest()[0]->attemptId)->toBe($attempt->attemptId)
        ->and($attempt->templateSlug)->toBe('access-approved-integration')
        ->and($attempt->templateId)->not->toBeNull()
        ->and($attempt->templateVersion)->toBe(1)
        ->and($attempt->routeId)->toBe($route?->id);
});

it('blocks unconfigured Production and delivers after both provider gates pass', function () {
    $context = ['user' => ['name' => 'Sam', 'email' => 'sam@example.com']];
    $blockedRouter = emailLifecycleRouter(
        $this->routes,
        $this->templates,
        $this->editor,
        $this->studio,
        emailLifecycleConfig('production'),
    );
    $readyRouter = emailLifecycleRouter(
        $this->routes,
        $this->templates,
        $this->editor,
        $this->studio,
        emailLifecycleConfig('production', 'postmark', true),
    );

    $blocked = $blockedRouter->dispatch('access.request.approved.integration', $context);
    $sent    = $readyRouter->dispatch('access.request.approved.integration', $context);
    $newAttempts = array_values(array_filter(
        $this->attempts->latest(100),
        fn ($attempt): bool => ! in_array($attempt->id, $this->baselineAssetIds, true),
    ));

    expect($blocked?->state)->toBe(MailResult::STATE_REJECTED)
        ->and($sent?->state)->toBe(MailResult::STATE_SENT)
        ->and($this->driver->calls)->toBe(1)
        ->and(array_column($newAttempts, 'state'))->toBe([
            'sent',
            'rejected',
        ]);
});
