<?php

/**
 * Spec 113, US4 (FR-018, FR-019): what Email Studio says happens to mail matches what the site
 * does, for each kind of site, and names the paths that do not follow Email Studio.
 *
 * @package Corex\Tests\Unit\Email
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Container\Container;
use Corex\Email\Capture\CapturedEmailRepository;
use Corex\Email\Delivery\DeliveryPolicy;
use Corex\Email\Delivery\EmailAttemptRepository;
use Corex\Email\Delivery\MailOutcome;
use Corex\Email\Driver\MailDriver;
use Corex\Email\Message\EmailMessage;
use Corex\Email\Studio\EmailDeliveryContextFactory;
use Corex\Email\Studio\EmailStudioService;
use Corex\Email\Studio\EmailTemplateService;
use Corex\Environment\SiteEnvironment;
use Corex\Mail\Capture\CapturedMessage;
use Corex\Mail\Capture\MailCaptureStore;
use Corex\Mail\Capture\MailGate;
use Corex\Mail\Capture\MailHold;
use Corex\Support\Config\ConfigInterface;
use Corex\Support\Config\Source;
use Corex\Tests\Support\InMemoryEmailStudioStore;

/** @param array<string,mixed> $settings */
function mailOutcomeFor(array $settings): array
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
    $store = new InMemoryEmailStudioStore();
    $studio = new EmailStudioService(
        new DeliveryPolicy(),
        new CapturedEmailRepository($store),
        new EmailAttemptRepository($store),
        new class implements MailDriver {
            public function send(EmailMessage $message): bool
            {
                return true;
            }
        },
        new EmailTemplateService([]),
        'wp-mail',
    );
    $environment = new SiteEnvironment([[SiteEnvironment::BY_MODE, $config]]);
    $container = new Container();
    $container->instance(MailCaptureStore::class, new class implements MailCaptureStore {
        public function keep(CapturedMessage $message): string
        {
            return 'kept';
        }
    });

    return (new MailOutcome(
        $environment,
        new EmailDeliveryContextFactory($environment, $config, $studio),
        $studio,
        new MailGate($environment, $config, new MailHold(), $container),
    ))->describe();
}

beforeEach(function () {
    Functions\when('__')->returnArg();
});

it('says a production site with a provider and live delivery delivers for real', function () {
    $outcome = mailOutcomeFor(['app.env' => 'production', 'mail.provider' => 'wp-mail', 'mail.live_delivery' => true]);

    expect($outcome['state'])->toBe(MailOutcome::DELIVERED)
        ->and($outcome['title'])->toBe('Delivered for real')
        ->and($outcome['exceptions'])->toBe([])
        ->and($outcome['facts'])->toContain('Live delivery: on')
        ->and($outcome['facts'])->toContain('Provider: wp-mail')
        ->and($outcome['facts'])->not->toContain('Captures: all mail from this site');
});

it('says a development site keeps everything, and which mail', function () {
    $all = mailOutcomeFor(['app.env' => 'development']);
    $corex = mailOutcomeFor(['app.env' => 'development', 'mail.capture_scope' => 'corex']);

    expect($all['state'])->toBe(MailOutcome::CAPTURED)
        ->and($all['title'])->toBe('Captured: nothing leaves this site')
        ->and($all['facts'])->toContain('Captures: all mail from this site')
        ->and($all['exceptions'])->toBe([])
        ->and($corex['title'])->toBe('Captured: CoreX mail stays on this site')
        ->and($corex['facts'])->toContain('Captures: only CoreX mail')
        ->and($corex['exceptions'])->toBe(['WordPress\'s own emails and other plugins\' mail']);
});

it('names what still goes out on a production site with no provider, never only "blocked"', function () {
    $outcome = mailOutcomeFor(['app.env' => 'production']);

    expect($outcome['state'])->toBe(MailOutcome::BLOCKED)
        ->and($outcome['title'])->toBe('Blocked: Email Studio sends nothing')
        ->and($outcome['exceptions'])->toHaveCount(2)
        ->and($outcome['detail'])->toContain('Still sent for real:')
        ->and($outcome['facts'])->toContain('Provider: none set')
        ->and($outcome['facts'])->toContain('Live delivery: off');
});

it('covers staging, with and without live delivery', function () {
    $live = mailOutcomeFor(['app.env' => 'staging', 'mail.provider' => 'wp-mail', 'mail.live_delivery' => '1']);
    $off = mailOutcomeFor(['app.env' => 'staging', 'mail.provider' => 'wp-mail']);

    expect($live['state'])->toBe(MailOutcome::DELIVERED)
        ->and($live['exceptions'])->toBe([])
        ->and($off['state'])->toBe(MailOutcome::CAPTURED)
        ->and($off['exceptions'])->toBe([])
        ->and($off['environment'])->toBe('staging');
});

it('says what decided, and whether it is locked', function () {
    $outcome = mailOutcomeFor(['app.env' => 'staging']);

    expect($outcome['decided_by'])->toBe(SiteEnvironment::BY_MODE)
        ->and($outcome['locked'])->toBeFalse()
        ->and($outcome['facts'][0])->toBe('Environment: Staging (set by the operations mode)')
        ->and($outcome['scope'])->toBe(MailGate::SCOPE_ALL);
});
