<?php

/**
 * Unit tests for Email Studio routing, test sends, resend lineage, and health (spec 068: FR-120–FR-125).
 *
 * @package Corex\Tests\Unit\Email
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Email\Capture\CapturedEmailRepository;
use Corex\Email\Delivery\DeliveryPolicy;
use Corex\Email\Delivery\EmailAttempt;
use Corex\Email\Delivery\EmailAttemptRepository;
use Corex\Email\Driver\MailDriver;
use Corex\Email\Message\EmailMessage;
use Corex\Email\Routing\EmailRouteDispatcher;
use Corex\Email\Routing\EmailRouteMessageFactory;
use Corex\Email\Routing\EmailRouteRepository;
use Corex\Email\Routing\EmailRouteService;
use Corex\Email\Routing\EmailRoute;
use Corex\Email\Studio\EmailLayoutRepository;
use Corex\Email\Studio\EmailPartialRepository;
use Corex\Email\Studio\EmailStudioRepositories;
use Corex\Email\Studio\EmailStudioService;
use Corex\Email\Studio\EmailStudioStore;
use Corex\Email\Studio\EmailStudioSubmissionGateway;
use Corex\Email\Studio\EmailTemplateRepository;
use Corex\Email\Template\Layout;
use Corex\Mail\UnavailableSubmissionEmailGateway;
use Corex\Email\Studio\EmailDeliveryContext;
use Corex\Email\Studio\EmailHealthContext;
use Corex\Email\Studio\EmailTemplateService;
use Corex\Email\Studio\EmailTemplateCatalog;
use Corex\Email\Studio\EmailTemplateVersion;
use Corex\Email\Studio\EmailTemplateReference;
use Corex\Mail\MailResult;
use Corex\Support\Config\ConfigInterface;

beforeEach(function () {
    Functions\when('__')->returnArg();
    // See DeliveryPolicyTest: function_exists('wp_salt') is unreliable across a Brain Monkey suite
    // once any test has stubbed it, so this path must stub it explicitly rather than rely on order.
    Functions\when('wp_salt')->justReturn('unit-test-salt');
});

function emailStudioServiceStore(): EmailStudioStore
{
    return new class implements EmailStudioStore {
        /** @var array<int,array{id:int,type:string,slug:string,name:string,parentId:int,payload:array<string,mixed>}> */
        private array $records = [];

        public function create(string $type, string $slug, string $name, int $parentId, array $payload): int
        {
            $id = count($this->records) + 1;
            $this->records[$id] = compact('id', 'type', 'slug', 'name', 'parentId', 'payload');

            return $id;
        }

        public function update(int $id, string $name, array $payload): bool
        {
            if (! isset($this->records[$id])) {
                return false;
            }

            $this->records[$id]['name']    = $name;
            $this->records[$id]['payload'] = $payload;

            return true;
        }

        public function find(int $id): ?array
        {
            return $this->records[$id] ?? null;
        }

        public function findBySlug(string $type, string $slug): ?array
        {
            foreach ($this->records as $record) {
                if ($record['type'] === $type && $record['slug'] === $slug) {
                    return $record;
                }
            }

            return null;
        }

        public function all(string $type, ?int $parentId = null): array
        {
            return array_values(array_filter(
                $this->records,
                static fn (array $record): bool => $record['type'] === $type
                    && ($parentId === null || $record['parentId'] === $parentId),
            ));
        }
    };
}

function studioMailDriver(bool $accepted = true): MailDriver
{
    return new class($accepted) implements MailDriver {
        public int $calls = 0;

        public function __construct(private readonly bool $accepted)
        {
        }

        public function send(EmailMessage $message): bool
        {
            ++$this->calls;

            return $this->accepted;
        }
    };
}

/** @return array{0:EmailStudioService,1:CapturedEmailRepository,2:EmailAttemptRepository,3:MailDriver} */
function emailStudioService(bool $providerAccepts = true): array
{
    $store    = emailStudioServiceStore();
    $captures = new CapturedEmailRepository($store);
    $attempts = new EmailAttemptRepository($store);
    $driver   = studioMailDriver($providerAccepts);
    $editor   = new EmailTemplateService([
        'user.name' => ['type' => 'text', 'label' => 'User name'],
    ]);

    return [
        new EmailStudioService(new DeliveryPolicy(), $captures, $attempts, $driver, $editor, 'postmark'),
        $captures,
        $attempts,
        $driver,
    ];
}

function studioMessage(): EmailMessage
{
    return new EmailMessage(['sam@example.com'], [], [], 'reply@example.com', 'Welcome', '<p>Hello</p>');
}

function studioVersion(
    string $plainText = 'Hello {{ user.name }}',
    string $html = '<p>Hello {{ user.name }}</p>',
    int $templateId = 1,
): EmailTemplateVersion
{
    return new EmailTemplateVersion(
        id: 2,
        templateId: $templateId,
        versionNumber: 1,
        subject: 'Welcome {{ user.name }}',
        fromName: 'CoreX',
        fromAddress: 'hello@example.com',
        htmlBody: $html,
        plainText: $plainText,
        plainTextMode: 'manual',
        layoutId: 3,
        layoutVersion: 1,
        variableKeys: ['user.name'],
        createdBy: 7,
        createdAt: new DateTimeImmutable('2026-07-03T12:00:00+00:00'),
        checksum: hash('sha256', $plainText . $html),
    );
}

it('persists and resolves enabled trigger routes from whitelisted context paths', function () {
    $repository = new EmailRouteRepository(emailStudioServiceStore());
    $route      = $repository->save(new EmailRoute(
        id: 0,
        trigger: 'forms.contact.submitted',
        templateId: 4,
        recipientRules: [['source' => 'context', 'path' => 'submission.email']],
        replyToRule: ['source' => 'context', 'path' => 'submission.email'],
        enabled: true,
        updatedBy: 7,
        updatedAt: new DateTimeImmutable('2026-07-03T12:00:00+00:00'),
    ));

    $resolved = (new EmailRouteService($repository))->resolve($route->trigger, [
        'submission' => ['email' => 'sam@example.com'],
    ]);

    expect($repository->findByTrigger('forms.contact.submitted')?->templateId)->toBe(4)
        ->and($resolved?->recipients)->toBe(['sam@example.com'])
        ->and($resolved?->replyTo)->toBe('sam@example.com');
});

it('exposes only active Email Studio templates to cross-module binding controls', function () {
    $repository = new \Corex\Email\Studio\EmailTemplateRepository(emailStudioServiceStore());
    $now = new DateTimeImmutable('2026-07-03T12:00:00+00:00');
    $active = $repository->create('active-template', 'Active template', 7, $now);
    $draft = $repository->saveDraft(studioVersion(templateId: $active->id));
    $repository->activate($active->id, $draft->versionNumber, 7, $now);
    $repository->create('draft-template', 'Draft template', 7, $now);

    expect((new EmailTemplateCatalog($repository))->templates())->toBe([[
        'id' => $active->id,
        'slug' => 'active-template',
        'name' => 'Active template',
    ]]);
});

it('renders and dispatches the active editable template selected by a route', function () {
    $store      = emailStudioServiceStore();
    $templates  = new \Corex\Email\Studio\EmailTemplateRepository($store);
    $routes     = new EmailRouteRepository($store);
    $captures   = new CapturedEmailRepository($store);
    $attempts   = new EmailAttemptRepository($store);
    $editor     = new EmailTemplateService(['user.name' => ['type' => 'text', 'label' => 'User name']]);
    $driver     = studioMailDriver();
    $now        = new DateTimeImmutable('2026-07-03T12:00:00+00:00');
    $template   = $templates->create('welcome-route', 'Welcome route', 7, $now);
    $version    = $templates->saveDraft(studioVersion(templateId: $template->id));
    $templates->activate($template->id, $version->versionNumber, 7, $now);
    $routes->save(new EmailRoute(
        id: 0,
        trigger: 'access.request.approved',
        templateId: $template->id,
        recipientRules: [['source' => 'context', 'path' => 'requester.email']],
        replyToRule: null,
        enabled: true,
        updatedBy: 7,
        updatedAt: $now,
    ));
    $studio = new EmailStudioService(new DeliveryPolicy(), $captures, $attempts, $driver, $editor, 'postmark');
    $config = new class implements ConfigInterface {
        public function get(string $key, mixed $default = null): mixed
        {
            return $key === 'app.env' ? 'development' : $default;
        }

        public function has(string $key): bool
        {
            return $key === 'app.env';
        }
    };
    $router = new EmailRouteService(
        $routes,
        new EmailRouteDispatcher(
            new EmailRouteMessageFactory($templates, $editor),
            $studio,
            $config,
        ),
    );

    $result = $router->dispatch('access.request.approved', [
        'user' => ['name' => 'Sam'],
        'requester' => ['email' => 'sam@example.com'],
    ]);

    expect($result?->state)->toBe(MailResult::STATE_CAPTURED)
        ->and($captures->latest()[0]->subject)->toBe('Welcome Sam')
        ->and($captures->latest()[0]->body)->toBe('<p>Hello Sam</p>');
});

it('captures a Development test and records a truthful captured attempt', function () {
    [$service, $captures, $attempts, $driver] = emailStudioService();

    $result = $service->testSend(
        studioMessage(),
        new EmailDeliveryContext(
            'development',
            providerConfigured: true,
            liveDeliveryEnabled: true,
            requestId: '64d15a02-8cf2-4e47-9ea3-fbbbc22ce22c',
        ),
        template: new EmailTemplateReference('welcome', 3, 2),
    );

    expect($result->state)->toBe(MailResult::STATE_CAPTURED)
        ->and($result->provider)->toBe('corex-capture')
        ->and($captures->latest())->toHaveCount(1)
        ->and($attempts->latest())->toHaveCount(1)
        ->and($captures->latest()[0]->attemptId)->toBe($attempts->latest()[0]->attemptId)
        ->and($attempts->latest()[0]->templateId)->toBe(3)
        ->and($attempts->latest()[0]->templateVersion)->toBe(2)
        ->and($attempts->latest()[0]->providerEvent)->toBe('captured')
        ->and($driver->calls)->toBe(0);
});

it('records one delivery attempt per valid recipient under one request', function () {
    [$service, $captures, $attempts] = emailStudioService();
    $message = new EmailMessage(
        ['sam@example.com', 'lee@example.com', 'not-an-email'],
        [],
        [],
        null,
        'Team update',
        '<p>Hello team</p>',
    );

    $result = $service->testSend(
        $message,
        new EmailDeliveryContext('development', true, true, 'a4cfdc36-e3fe-45f5-a783-7657b11d19d6'),
    );

    expect($result->attemptId)->toBe($attempts->latest()[1]->attemptId)
        ->and($attempts->latest())->toHaveCount(2)
        ->and(array_column($attempts->latest(), 'recipient'))->toBe(['l***@example.com', 's***@example.com'])
        ->and($captures->latest())->toHaveCount(1);
});

it('blocks unsafe Production tests and delivers only after the provider gate passes', function () {
    [$service, , $attempts, $driver] = emailStudioService();

    $blocked = $service->testSend(
        studioMessage(),
        new EmailDeliveryContext(
            'production',
            providerConfigured: false,
            liveDeliveryEnabled: true,
            requestId: '64d15a02-8cf2-4e47-9ea3-fbbbc22ce22c',
        ),
    );
    $sent = $service->testSend(
        studioMessage(),
        new EmailDeliveryContext(
            'production',
            providerConfigured: true,
            liveDeliveryEnabled: true,
            requestId: '7bb7b752-5ae8-47b8-b09a-b5cc26c3bfd9',
        ),
    );

    expect($blocked->state)->toBe(MailResult::STATE_REJECTED)
        ->and($sent->state)->toBe(MailResult::STATE_SENT)
        ->and($attempts->latest())->toHaveCount(2)
        ->and($driver->calls)->toBe(1);
});

it('creates a new attempt linked to the failed attempt when resending', function () {
    [$service, , $attempts] = emailStudioService();
    $failed = $attempts->record(new EmailAttempt(
        id: 0,
        attemptId: 'f6773ddc-2d63-40cc-b408-35c0a81c084b',
        requestId: '64d15a02-8cf2-4e47-9ea3-fbbbc22ce22c',
        parentAttemptId: null,
        recipient: 'sam@example.com',
        subject: 'Welcome',
        templateSlug: 'welcome',
        state: EmailAttempt::STATE_FAILED,
        provider: 'postmark',
        providerEvent: 'timeout',
        retryable: true,
        occurredAt: new DateTimeImmutable('2026-07-03T12:00:00+00:00'),
    ));

    $resent = $service->resend(
        $failed->attemptId,
        studioMessage(),
        new EmailDeliveryContext(
            'production',
            providerConfigured: true,
            liveDeliveryEnabled: true,
            requestId: '7bb7b752-5ae8-47b8-b09a-b5cc26c3bfd9',
        ),
    );

    expect($resent->state)->toBe(MailResult::STATE_SENT)
        ->and($resent->parentAttemptId)->toBe($failed->attemptId)
        ->and($attempts->latest())->toHaveCount(2);
});

it('reports actionable template provider reply-to and subscription health failures', function () {
    [$service] = emailStudioService();

    $health = $service->health(
        studioVersion(plainText: '', html: '<p>Hello {{ user.name }}</p>'),
        new EmailHealthContext(
            providerConfigured: false,
            requiresSubscriptionLinks: true,
            replyTo: 'not-an-email',
        ),
    );

    expect($health)->toHaveKeys(['plainText', 'unsubscribe', 'preferences', 'replyTo', 'provider']);
});

/**
 * The gateway a reply from the Submissions inbox goes through, over the service above.
 *
 * @param array<string,mixed> $settings
 */
function replyGateway(array $settings): EmailStudioSubmissionGateway
{
    [$service] = emailStudioService();
    $store = emailStudioServiceStore();
    $config = new class($settings) implements ConfigInterface {
        /** @param array<string,mixed> $settings */
        public function __construct(private readonly array $settings)
        {
        }

        public function get(string $key, mixed $default = null): mixed
        {
            return $this->settings[$key] ?? $default;
        }

        public function has(string $key): bool
        {
            return array_key_exists($key, $this->settings);
        }
    };

    return new EmailStudioSubmissionGateway(
        new EmailStudioRepositories(
            new EmailTemplateRepository($store),
            new EmailLayoutRepository($store),
            new EmailPartialRepository($store),
            new EmailRouteRepository($store),
            new CapturedEmailRepository($store),
            new EmailAttemptRepository($store),
        ),
        $service,
        new EmailTemplateService([]),
        $config,
        new Layout(['name' => 'Acme']),
    );
}

dataset('reply delivery settings', [
    'production, no provider' => [
        ['app.env' => 'production', 'mail.live_delivery' => true], 'refuse', 'no_provider', MailResult::STATE_REJECTED,
    ],
    'production, a provider that is not the bound one' => [
        ['app.env' => 'production', 'mail.provider' => 'wp-mail', 'mail.live_delivery' => true], 'refuse', 'no_provider', MailResult::STATE_REJECTED,
    ],
    'production, live delivery off' => [
        ['app.env' => 'production', 'mail.provider' => 'postmark'], 'refuse', 'live_off', MailResult::STATE_REJECTED,
    ],
    'production, both set' => [
        ['app.env' => 'production', 'mail.provider' => 'postmark', 'mail.live_delivery' => '1'], 'deliver', 'live', MailResult::STATE_SENT,
    ],
    'development' => [
        ['app.env' => 'development', 'mail.provider' => 'postmark', 'mail.live_delivery' => true], 'capture', 'development', MailResult::STATE_CAPTURED,
    ],
    'staging, live delivery off' => [
        ['app.env' => 'staging', 'mail.provider' => 'postmark'], 'capture', 'staging_capture', MailResult::STATE_CAPTURED,
    ],
    'an environment CoreX does not know' => [
        ['app.env' => 'mystery', 'mail.provider' => 'postmark', 'mail.live_delivery' => true], 'refuse', 'unknown_environment', MailResult::STATE_REJECTED,
    ],
]);

// Spec 106, FR-028 and FR-029: what the reply box is told, and what the sending then does.
it('says before a reply is written what will happen to it, and then does that', function (array $settings, string $mode, string $code, string $state) {
    Functions\when('sanitize_key')->returnArg();
    Functions\when('is_email')->justReturn(false);
    $gateway = replyGateway($settings);

    $readiness = $gateway->readiness();
    $result = $gateway->reply('sam@example.com', 'Re: Contact', '<p>Thanks, Sam.</p>');

    expect($readiness->mode)->toBe($mode)
        ->and($readiness->code)->toBe($code)
        ->and($result->state)->toBe($state);
})->with('reply delivery settings');

it('names the Mail settings when a reply will not be delivered, and says nothing when it will', function () {
    Functions\when('sanitize_key')->returnArg();
    $off = replyGateway(['app.env' => 'production', 'mail.provider' => 'postmark'])->readiness();
    $none = replyGateway(['app.env' => 'production'])->readiness();
    $live = replyGateway(['app.env' => 'production', 'mail.provider' => 'postmark', 'mail.live_delivery' => true])->readiness();

    expect($off->reason)->toContain('live delivery is switched off')->toContain('Settings')
        ->and($none->reason)->toContain('no delivery provider')->toContain('Settings')
        ->and($off->sendable())->toBeFalse()
        ->and($live->reason)->toBe('')
        ->and($live->sendable())->toBeTrue();
});

it('says replies need the email add-on where it is absent', function () {
    $readiness = (new UnavailableSubmissionEmailGateway())->readiness();

    expect($readiness->mode)->toBe('unavailable')
        ->and($readiness->sendable())->toBeFalse()
        ->and($readiness->reason)->toContain('Email add-on');
});
