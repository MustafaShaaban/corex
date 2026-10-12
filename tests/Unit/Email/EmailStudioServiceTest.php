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
use Corex\Email\Design\EmailBrand;
use Corex\Email\Design\EmailBrandSource;
use Corex\Email\Design\ReplyComposer;
use Corex\Email\Design\StandardEmailDesign;
use Corex\Mail\MessageId;
use Corex\Mail\ReplyConversation;
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

        /** @var list<EmailMessage> */
        public array $sent = [];

        public function send(EmailMessage $message): bool
        {
            ++$this->calls;
            $this->sent[] = $message;

            return $this->accepted;
        }
    };
}

/** @return array{0:EmailStudioService,1:CapturedEmailRepository,2:EmailAttemptRepository,3:MailDriver} */
function emailStudioService(bool $providerAccepts = true, string $messageHost = ''): array
{
    $store    = emailStudioServiceStore();
    $captures = new CapturedEmailRepository($store);
    $attempts = new EmailAttemptRepository($store);
    $driver   = studioMailDriver($providerAccepts);
    $editor   = new EmailTemplateService([
        'user.name' => ['type' => 'text', 'label' => 'User name'],
    ]);

    return [
        new EmailStudioService(new DeliveryPolicy(), $captures, $attempts, $driver, $editor, 'postmark', null, $messageHost),
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
 * @param array<string,mixed>                                                                $settings
 * @param array{0:EmailStudioService,1:CapturedEmailRepository,2:EmailAttemptRepository,3:MailDriver}|null $studio The service to send through, for a test that reads what it kept.
 */
function replyGateway(array $settings, ?array $studio = null): EmailStudioSubmissionGateway
{
    [$service, $captures, $attempts] = $studio ?? emailStudioService();
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
            $captures,
            $attempts,
        ),
        $service,
        new EmailTemplateService([]),
        $config,
        new ReplyComposer(new StandardEmailDesign(), new class() implements EmailBrandSource {
            public function brand(): EmailBrand
            {
                return EmailBrand::standard('Acme', 'https://acme.test/');
            }
        }),
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

// Spec 106, FR-008 and FR-013: what the reply leaves as, read from where a development site keeps it.
it('sends a reply in the site design with the same words as plain text beside it', function () {
    Functions\when('sanitize_key')->returnArg();
    Functions\when('is_email')->justReturn(false);
    $studio = emailStudioService();

    replyGateway(['app.env' => 'development'], $studio)
        ->reply('sam@example.com', 'Re: Contact', '<p>Thanks, Sam.</p><ul><li>One</li></ul>');
    $captured = $studio[1]->latest(1)[0];

    expect($captured->body)->toStartWith('<!DOCTYPE html>')
        ->and($captured->body)->toContain('Thanks, Sam.')
        ->and($captured->body)->toContain('Acme')
        ->and($captured->body)->toContain('border-top:4px solid')
        ->and($captured->plainText)->toStartWith("Thanks, Sam.\n\n- One\n\n-- \n")
        ->and($captured->plainText)->toContain('Acme');
});

it('says replies need the email add-on where it is absent', function () {
    $readiness = (new UnavailableSubmissionEmailGateway())->readiness();
    // And there is no email to show before sending where nothing would send one.
    expect(fn () => (new UnavailableSubmissionEmailGateway())->preview('sam@example.com', 'Re: Contact', '<p>Hello</p>'))
        ->toThrow(DomainException::class, 'Email Studio is required');

    expect($readiness->mode)->toBe('unavailable')
        ->and($readiness->sendable())->toBeFalse()
        ->and($readiness->reason)->toContain('Email add-on');
});

// Spec 106, FR-037: an email is sent under an id CoreX chose, so that a later one can name it.
it('sends each email under an id of its own, made of the site\'s host, and keeps it with the attempt', function () {
    [$studio, , $attempts, $driver] = emailStudioService(messageHost: 'mu-va.com');
    $live = new EmailDeliveryContext('production', true, true, '44444444-4444-4444-8444-444444444444');

    $first = $studio->send(studioMessage(), $live);
    $second = $studio->send(studioMessage(), $live);
    $kept = $attempts->findByAttemptId($first->attemptId);

    expect($driver->sent[0]->headers['Message-ID'])->toBe(MessageId::of($first->attemptId, 'mu-va.com'))
        ->and($kept->providerMessageId)->toBe(MessageId::of($first->attemptId, 'mu-va.com'))
        ->and($driver->sent[1]->headers['Message-ID'])->toBe(MessageId::of($second->attemptId, 'mu-va.com'))
        // The rest of the message is as it was given.
        ->and($driver->sent[0]->subject)->toBe('Welcome')
        ->and($driver->sent[0]->replyTo)->toBe('reply@example.com');
});

it('keeps the id with an email that was kept on the site and not delivered', function () {
    [$studio, $captures, $attempts] = emailStudioService(messageHost: 'acme.test');

    $kept = $studio->send(studioMessage(), new EmailDeliveryContext('development', true, true, '44444444-4444-4444-8444-444444444444'));

    expect($attempts->findByAttemptId($kept->attemptId)->providerMessageId)->toBe(MessageId::of($kept->attemptId, 'acme.test'))
        ->and($captures->latest(1)[0]->headers['Message-ID'])->toBe(MessageId::of($kept->attemptId, 'acme.test'));
});

it('sends as before where it was told no host to make an id of', function () {
    [$studio, , $attempts, $driver] = emailStudioService();

    $sent = $studio->send(studioMessage(), new EmailDeliveryContext('production', true, true, '44444444-4444-4444-8444-444444444444'));

    expect($driver->sent[0]->headers)->toBe([])
        ->and($attempts->findByAttemptId($sent->attemptId)->providerMessageId)->toBeNull();
});

it('makes an id only of a host a mail server would take for a name', function (string $address, string $host) {
    expect(MessageId::hostOf($address))->toBe($host)
        // Twenty characters of the attempt's id, where the host leaves room for them.
        ->and(MessageId::of('11111111-2222-4333-8444-555555555555', $host))
        ->toBe($host === '' ? '' : '<11111111222243338444@' . $host . '>');
})->with([
    'a site' => ['https://Mu-Va.com/shop/', 'mu-va.com'],
    'a site on a port' => ['http://127.0.0.1:8906/', '127.0.0.1'],
    'no address' => ['', ''],
    'a path' => ['/wp-admin/', ''],
]);

// WordPress's mailer rewrites a header's value past 47 characters when it sends through PHP's
// `mail()`, and no mail program reads what it writes as an id. So an id is shorter on a longer
// host, down to ten characters of the attempt's.
it('makes an id that fits in a header as it is written, on a host of up to 34 characters', function (string $host, string $id) {
    $made = MessageId::of('11111111-2222-4333-8444-555555555555', $host);

    expect($made)->toBe($id)
        ->and(strlen($made) <= MessageId::FITS)->toBe(strlen($host) <= 34);
})->with([
    'a short host' => ['mu-va.com', '<11111111222243338444@mu-va.com>'],
    'the longest that leaves room for twenty' => ['a-site-of-twenty.example', '<11111111222243338444@a-site-of-twenty.example>'],
    'one longer' => ['a-site-of-twenty4.example', '<1111111122224333844@a-site-of-twenty4.example>'],
    'the longest an id fits on' => ['staging.a-client-of-us.example.com', '<1111111122@staging.a-client-of-us.example.com>'],
    'one longer, where it does not' => ['staging.a-client-of-us1.example.com', '<1111111122@staging.a-client-of-us1.example.com>'],
]);

it('knows an id from what is not one', function (mixed $value, bool $isOne) {
    expect(MessageId::isOne($value))->toBe($isOne);
})->with([
    'an id' => ['<11111111-1111-4111-8111-111111111111@mu-va.com>', true],
    'without its brackets' => ['11111111-1111-4111-8111-111111111111@mu-va.com', false],
    'with a header after it' => ["<a@b.com>\r\nBcc: x@example.com", false],
    'two of them' => ['<a@b.com> <c@d.com>', false],
    'with a space' => ['<a b@c.com>', false],
    // WordPress's mailer takes none of these for an id, and sends under one of its own.
    'two dots together' => ['<a..b@c.com>', false],
    'a dot first' => ['<.a@c.com>', false],
    'a dot before the host' => ['<a.@c.com>', false],
    'a host that ends in a dot' => ['<a@c.com.>', false],
    'longer than a header\'s line' => ['<' . str_repeat('a', 250) . '@c.com>', false],
    'nothing' => ['', false],
    'not text' => [['<a@b.com>'], false],
]);

// FR-038 and FR-040: the emails this person was sent, in order, are what a reply continues.
it('answers a reply\'s conversation from the emails this person was sent, oldest first', function () {
    Functions\when('sanitize_key')->returnArg();
    Functions\when('is_email')->justReturn(false);
    $studio = emailStudioService(messageHost: 'acme.test');
    $gateway = replyGateway(['app.env' => 'development', 'mail.provider' => 'postmark', 'mail.live_delivery' => true], $studio);
    $development = new EmailDeliveryContext('development', true, true, '44444444-4444-4444-8444-444444444444');
    $confirmation = $studio[0]->send(new EmailMessage(['sam@example.com'], [], [], null, 'We received your enquiry', '<p>Thanks</p>'), $development);
    $toTheTeam = $studio[0]->send(new EmailMessage(['sales@acme.test'], [], [], null, 'New enquiry', '<p>Sam wrote</p>'), $development);
    $first = $gateway->reply('sam@example.com', 'Re: We received your enquiry', '<p>Hello Sam</p>');

    $conversation = $gateway->conversation('Sam@Example.com ', [$first->attemptId, $toTheTeam->attemptId, 'not-an-id', $confirmation->attemptId]);

    expect($conversation->messageIds)->toBe([
        MessageId::of($confirmation->attemptId, 'acme.test'),
        MessageId::of($first->attemptId, 'acme.test'),
    ])
        ->and($conversation->subject)->toBe('Re: We received your enquiry')
        ->and($conversation->latest())->toBe(MessageId::of($first->attemptId, 'acme.test'));
});

it('has no conversation where nothing this person was sent has an id', function () {
    Functions\when('sanitize_key')->returnArg();
    Functions\when('is_email')->justReturn(false);
    // Sent before CoreX chose ids: the email is on record, and nothing can name it.
    $studio = emailStudioService();
    $gateway = replyGateway(['app.env' => 'development', 'mail.provider' => 'postmark', 'mail.live_delivery' => true], $studio);
    $old = $studio[0]->send(studioMessage(), new EmailDeliveryContext('development', true, true, '44444444-4444-4444-8444-444444444444'));

    expect($gateway->conversation('sam@example.com', [$old->attemptId])->isEmpty())->toBeTrue()
        ->and($gateway->conversation('sam@example.com', [])->isEmpty())->toBeTrue();
});

it('sends a reply under the email it answers, naming the ones before it; or as a new email, naming none', function () {
    Functions\when('sanitize_key')->returnArg();
    Functions\when('is_email')->justReturn(false);
    $studio = emailStudioService(messageHost: 'acme.test');
    $gateway = replyGateway(['app.env' => 'production', 'mail.provider' => 'postmark', 'mail.live_delivery' => true], $studio);
    $within = new ReplyConversation(['<one@acme.test>', '<two@acme.test>'], 'We received your enquiry');

    $gateway->reply('sam@example.com', 'Re: We received your enquiry', '<p>Hello</p>', $within);
    $gateway->reply('sam@example.com', 'A new subject', '<p>Hello</p>');
    $gateway->reply('sam@example.com', 'A new subject', '<p>Hello</p>', new ReplyConversation());
    [$continued, $alone, $withNothing] = $studio[3]->sent;

    expect($continued->headers['In-Reply-To'])->toBe('<two@acme.test>')
        ->and($continued->headers['References'])->toBe('<one@acme.test> <two@acme.test>')
        ->and($alone->headers)->not->toHaveKey('In-Reply-To')->not->toHaveKey('References')
        ->and($withNothing->headers)->not->toHaveKey('In-Reply-To')
        // Each is still sent under an id of its own, for the next reply to name.
        ->and(MessageId::isOne($alone->headers['Message-ID']))->toBeTrue();
});

it('lets nothing but an id into a conversation', function () {
    $conversation = new ReplyConversation(['<one@acme.test>', "<two@acme.test>\r\nBcc: x@example.com", 'plain', ['x'], '<three@acme.test>'], "Subject\r\nBcc: x");

    expect($conversation->messageIds)->toBe(['<one@acme.test>', '<three@acme.test>'])
        ->and($conversation->subject)->toBe('Subject Bcc: x')
        ->and((new ReplyConversation([], 'A subject'))->subject)->toBe('');
});

