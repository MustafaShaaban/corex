<?php

/**
 * Spec 113, US2 and US3 (FR-005 to FR-013): what the mail gate does with an email, for each
 * environment, the "Capture only CoreX mail" switch, a flow test's hold, and a site with
 * nowhere to keep mail; and the `pre_wp_mail` interceptor that asks it.
 *
 * @package Corex\Tests\Unit\Mail
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Container\Container;
use Corex\Environment\SiteEnvironment;
use Corex\Mail\Capture\CapturedMessage;
use Corex\Mail\Capture\MailCaptureStore;
use Corex\Mail\Capture\MailDecision;
use Corex\Mail\Capture\MailGate;
use Corex\Mail\Capture\MailHold;
use Corex\Mail\Capture\MailPath;
use Corex\Mail\Capture\WpMailInterceptor;
use Corex\Support\Config\ConfigInterface;
use Corex\Support\Config\Source;

final class RecordingCaptureStore implements MailCaptureStore
{
    /** @var list<CapturedMessage> */
    public array $kept = [];

    public function keep(CapturedMessage $message): string
    {
        $this->kept[] = $message;

        return 'capture-' . count($this->kept);
    }
}

/** @param array<string,mixed> $settings `app.env`, `mail.provider`, `mail.live_delivery`, `mail.capture_scope` */
function mailGate(array $settings, ?RecordingCaptureStore $store, ?MailHold $hold = null): MailGate
{
    $config = new class ($settings) implements ConfigInterface, Source {
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
    $container = new Container();
    if ($store !== null) {
        $container->instance(MailCaptureStore::class, $store);
    }

    return new MailGate(
        new SiteEnvironment([[SiteEnvironment::BY_DOTENV, $config]]),
        $config,
        $hold ?? new MailHold(),
        $container,
    );
}

beforeEach(function () {
    Functions\when('__')->returnArg();
});

it('decides by the environment, live delivery and the switch', function (array $settings, bool $corexMail, MailDecision $expected) {
    expect(mailGate($settings, new RecordingCaptureStore())->decide($corexMail))->toBe($expected);
})->with([
    'production, nothing set (a client site)' => [['app.env' => 'production'], true, MailDecision::Deliver],
    'production, WordPress mail' => [['app.env' => 'production'], false, MailDecision::Deliver],
    'development, CoreX mail' => [['app.env' => 'development', 'mail.provider' => 'wp-mail', 'mail.live_delivery' => true], true, MailDecision::Capture],
    'development, WordPress mail' => [['app.env' => 'local'], false, MailDecision::Capture],
    'development, capture only CoreX, WordPress mail' => [['app.env' => 'development', 'mail.capture_scope' => 'corex'], false, MailDecision::Deliver],
    'development, capture only CoreX, CoreX mail' => [['app.env' => 'development', 'mail.capture_scope' => 'corex'], true, MailDecision::Capture],
    'staging, live delivery off' => [['app.env' => 'staging', 'mail.provider' => 'wp-mail'], true, MailDecision::Capture],
    'staging, provider and live delivery' => [['app.env' => 'staging', 'mail.provider' => 'wp-mail', 'mail.live_delivery' => '1'], false, MailDecision::Deliver],
]);

it('refuses on a development site where there is nowhere to keep mail, and delivers in production', function () {
    expect(mailGate(['app.env' => 'development'], null)->decide(true))->toBe(MailDecision::Refuse)
        ->and(mailGate(['app.env' => 'production'], null)->decide(true))->toBe(MailDecision::Deliver);
});

it('holds every email of a flow test, even in production, and lists what it held', function () {
    $store = new RecordingCaptureStore();
    $hold = new MailHold();
    $gate = mailGate(['app.env' => 'production', 'mail.provider' => 'wp-mail', 'mail.live_delivery' => true], $store, $hold);

    [$decision, $held] = $hold->during(function () use ($gate): MailDecision {
        $decision = $gate->decide(true);
        $gate->keep(new CapturedMessage(['team@example.com'], 'New lead', 'Body', 'Form notification'), $decision);

        return $decision;
    });

    expect($decision)->toBe(MailDecision::Hold)
        ->and($store->kept[0]->state)->toBe(CapturedMessage::STATE_HELD_FOR_TEST)
        ->and($held)->toBe([[
            'to' => ['team@example.com'],
            'subject' => 'New lead',
            'capture_id' => 'capture-1',
            'path' => 'Form notification',
        ]])
        ->and($hold->active())->toBeFalse()
        ->and($gate->decide(true))->toBe(MailDecision::Deliver);
});

it('keeps WordPress mail on a development site under the name WordPress gave it', function () {
    $store = new RecordingCaptureStore();
    $paths = new MailPath();
    $interceptor = new WpMailInterceptor(mailGate(['app.env' => 'development'], $store), $paths);

    $paths->next('WordPress: password reset');
    $answer = $interceptor->intercept(null, [
        'to' => 'reader@example.com, second@example.com',
        'subject' => 'Password reset',
        'message' => 'Follow the link.',
        'headers' => "Cc: copy@example.com\r\nReply-To: help@example.com",
        'attachments' => [],
    ]);
    $plugin = $interceptor->intercept(null, ['to' => ['x@example.com'], 'subject' => 'Plugin', 'message' => '']);

    expect($answer)->toBeTrue()
        ->and($store->kept[0]->to)->toBe(['reader@example.com', 'second@example.com'])
        ->and($store->kept[0]->cc)->toBe(['copy@example.com'])
        ->and($store->kept[0]->replyTo)->toBe('help@example.com')
        ->and($store->kept[0]->path)->toBe('WordPress: password reset')
        ->and($plugin)->toBeTrue()
        ->and($store->kept[1]->path)->toBe('Other: wp_mail');
});

it('lets mail through in production and leaves an earlier filter\'s answer alone', function () {
    $store = new RecordingCaptureStore();
    $interceptor = new WpMailInterceptor(mailGate(['app.env' => 'production'], $store), new MailPath());

    expect($interceptor->intercept(null, ['to' => 'a@example.com', 'subject' => 'S', 'message' => 'M']))->toBeNull()
        ->and($interceptor->intercept(false, ['to' => 'a@example.com']))->toBeFalse()
        ->and($store->kept)->toBe([]);
});

it('makes wp_mail() fail when there is nowhere to keep the email', function () {
    $interceptor = new WpMailInterceptor(mailGate(['app.env' => 'staging'], null), new MailPath());

    expect($interceptor->intercept(null, ['to' => 'a@example.com', 'subject' => 'S', 'message' => 'M']))->toBeFalse();
});

it('names CoreX mail by the innermost name, and only while it sends', function () {
    $paths = new MailPath();

    $inside = $paths->during('Form notification', static fn () => $paths->during('Inner', static fn () => $paths->current()));

    expect($inside)->toBe('Inner')
        ->and($paths->current())->toBeNull();
});
