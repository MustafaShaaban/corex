<?php

/**
 * Integration coverage for routed Email Studio capture and provider delivery (spec 068: SC-010).
 *
 * @package Corex\Tests\Integration\Mail
 */

declare(strict_types=1);

use Corex\Email\Capture\CapturedEmailRepository;
use Corex\Email\Delivery\DeliveryPolicy;
use Corex\Email\Delivery\EmailAttempt;
use Corex\Email\Delivery\EmailAttemptRepository;
use Corex\Email\Design\ReplyComposer;
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
use Corex\Email\Studio\EmailStudioSubmissionGateway;
use Corex\Email\Studio\EmailTemplateRepository;
use Corex\Email\Studio\EmailTemplateService;
use Corex\Email\Studio\EmailTemplateVersion;
use Corex\Email\Studio\WpEmailStudioStore;
use Corex\Mail\MailResult;
use Corex\Support\Config\ConfigInterface;
use Corex\Support\Uuid;

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

/**
 * An active template with this subject, and the route that sends it to `user.email`.
 *
 * @param array{slug:string,trigger:string} $named
 */
function emailLifecycleRoutedTemplate(
    EmailTemplateRepository $templates,
    EmailRouteRepository $routes,
    array $named,
    string $subject,
): EmailTemplateVersion {
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    $actorId = (int) ($administrators[0] ?? 1);
    $now = new DateTimeImmutable('2026-07-03T13:00:00+00:00');
    ['slug' => $slug, 'trigger' => $trigger] = $named;
    $template = $templates->create($slug, 'Lifecycle ' . $slug, $actorId, $now);
    $version = $templates->saveDraft(new EmailTemplateVersion(
        id: 1,
        templateId: $template->id,
        versionNumber: 1,
        subject: $subject,
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
        checksum: hash('sha256', $slug),
    ));
    $templates->activate($template->id, $version->versionNumber, $actorId, $now);
    $routes->save(new EmailRoute(
        id: 0,
        trigger: $trigger,
        templateId: $template->id,
        recipientRules: [['source' => 'context', 'path' => 'user.email']],
        replyToRule: null,
        enabled: true,
        updatedBy: $actorId,
        updatedAt: $now,
    ));

    return $version;
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

        public ?EmailMessage $last = null;

        public function send(EmailMessage $message): bool
        {
            ++$this->calls;
            $this->last = $message;

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

    emailLifecycleRoutedTemplate(
        $this->templates,
        $this->routes,
        ['slug' => 'access-approved-integration', 'trigger' => 'access.request.approved.integration'],
        'Access approved for {{ user.name }}',
    );
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

// A subject is a header, plain text and not markup (DECISIONS #330). Read from where a
// development site keeps an email: nothing is sent.
it('keeps a routed email under its template\'s subject as written, with a value merged as it is', function (
    string $written,
    string $name,
    string $keptAs,
) {
    emailLifecycleRoutedTemplate(
        $this->templates,
        $this->routes,
        ['slug' => 'subject-as-written-integration', 'trigger' => 'subject.as.written.integration'],
        $written,
    );
    $router = emailLifecycleRouter(
        $this->routes,
        $this->templates,
        $this->editor,
        $this->studio,
        emailLifecycleConfig('development', 'postmark', true),
    );

    $result = $router->dispatch('subject.as.written.integration', [
        'user' => ['name' => $name, 'email' => 'sam@example.com'],
    ]);

    expect($result?->state)->toBe(MailResult::STATE_CAPTURED)
        ->and($this->captures->latest(1)[0]->subject)->toBe($keptAs)
        ->and($this->attempts->findByAttemptId((string) $result?->attemptId)?->subject)->toBe($keptAs)
        ->and($this->driver->calls)->toBe(0);
})->with([
    'a less-than sign' => ['Price <500 EGP', '', 'Price <500 EGP'],
    'an address in angle brackets' => ['Sam <sam@example.com> asked', '', 'Sam <sam@example.com> asked'],
    'an ampersand and both quotes' => ['Tom & Jerry\'s "offer"', '', 'Tom & Jerry\'s "offer"'],
    'Arabic' => ['السعر أقل من <500> جنيه & "خصم"', '', 'السعر أقل من <500> جنيه & "خصم"'],
    'a merged value with markup' => ['Hello {{ user.name }}', 'Sam <b>x</b> & co', 'Hello Sam <b>x</b> & co'],
    'a merged value with markup and a break' => [
        'Hello {{ user.name }}',
        "Sam <b>x</b>\r\nBcc: victim@example.com",
        'Hello Sam <b>x</b> Bcc: victim@example.com',
    ],
]);

it('hands the mail driver one line when a merged value holds a break', function () {
    emailLifecycleRoutedTemplate(
        $this->templates,
        $this->routes,
        ['slug' => 'subject-one-line-integration', 'trigger' => 'subject.one.line.integration'],
        'Hello {{ user.name }}',
    );
    $router = emailLifecycleRouter(
        $this->routes,
        $this->templates,
        $this->editor,
        $this->studio,
        emailLifecycleConfig('production', 'postmark', true),
    );

    $result = $router->dispatch('subject.one.line.integration', [
        'user' => ['name' => "Sam <sam@example.com>\r\nBcc: victim@example.com", 'email' => 'sam@example.com'],
    ]);

    expect($result?->state)->toBe(MailResult::STATE_SENT)
        ->and($this->driver->last?->subject)->toBe('Hello Sam <sam@example.com> Bcc: victim@example.com');
});

it('sends a template\'s email again under the subject as written', function () {
    $version = emailLifecycleRoutedTemplate(
        $this->templates,
        $this->routes,
        ['slug' => 'subject-again-integration', 'trigger' => 'subject.again.integration'],
        'Price <500 EGP for {{ user.name }}',
    );
    $failed = $this->attempts->record(new EmailAttempt(
        id: 0,
        attemptId: Uuid::v4(),
        requestId: Uuid::v4(),
        parentAttemptId: null,
        recipient: 'sam@example.com',
        subject: 'Price',
        templateSlug: 'subject-again-integration',
        state: EmailAttempt::STATE_FAILED,
        provider: 'postmark',
        providerEvent: 'failed',
        retryable: true,
        occurredAt: new DateTimeImmutable('now'),
        templateId: $version->templateId,
        templateVersion: $version->versionNumber,
    ));
    $gateway = new EmailStudioSubmissionGateway(
        new EmailStudioRepositories(
            $this->templates,
            new EmailLayoutRepository($this->store),
            new EmailPartialRepository($this->store),
            $this->routes,
            $this->captures,
            $this->attempts,
        ),
        $this->studio,
        $this->editor,
        emailLifecycleConfig('development', 'postmark', true),
        Corex\Boot::app()->container()->make(ReplyComposer::class),
    );

    $again = $gateway->resend($failed->attemptId, 'sam@example.com', ['user' => ['name' => 'Tom & "Jerry"']]);
    $resent = $this->attempts->findByAttemptId($again->attemptId);

    expect($again->state)->toBe(MailResult::STATE_CAPTURED)
        ->and($resent?->subject)->toBe('Price <500 EGP for Tom & "Jerry"')
        ->and($resent?->parentAttemptId)->toBe($failed->attemptId)
        ->and($this->driver->calls)->toBe(0);
});
