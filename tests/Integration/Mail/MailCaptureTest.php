<?php

/**
 * Spec 113, US2 (FR-005 to FR-011): on a development or staging site every email stays on the
 * site, named by what sent it; production sends as before.
 *
 * The last `pre_wp_mail` answer is a spy: it records an email only if nothing before it kept the
 * email, which is an email that would have reached the transport, and then stops it. Nothing in
 * this file can leave the site.
 *
 * @package Corex\Tests\Integration\Mail
 */

declare(strict_types=1);

use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;
use Corex\Container\Container;
use Corex\Email\Capture\CapturedEmailRepository;
use Corex\Environment\SiteEnvironment;
use Corex\Mail\Capture\MailGate;
use Corex\Mail\Capture\MailHold;
use Corex\Mail\Capture\MailPath;
use Corex\Mail\Capture\WpMailInterceptor;
use Corex\Mail\Mailer;
use Corex\Mail\MailRequest;
use Corex\Support\Config\ConfigInterface;
use Corex\Tests\Support\CreatedPosts;
use Corex\Tests\Support\MailDelivery;
use Corex\Tests\Support\RecordingActivityRepository;

beforeEach(function () {
    $this->container = Corex\Boot::app()->container();
    // Another file may have taken every pre_wp_mail answer away; the gate is put back.
    $this->container->make(WpMailInterceptor::class)->register();
    $this->assets = CreatedPosts::watch('corex_email_asset', 'corex_email_log');
    $this->transport = [];
    $this->spy = function (mixed $return, array $atts): mixed {
        if ($return === null) {
            $this->transport[] = (string) ($atts['subject'] ?? '');
        }

        return true;
    };
    add_filter('pre_wp_mail', $this->spy, PHP_INT_MAX, 2);
    $this->scope = null;
    // WordPress writes the requester's address into its password reset email.
    $this->remoteAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
});

afterEach(function () {
    remove_filter('pre_wp_mail', $this->spy, PHP_INT_MAX);
    if ($this->scope !== null) {
        remove_filter('pre_option_corex_mail_capture_scope', $this->scope);
    }
    MailDelivery::stop($this->settings ?? []);
    $this->assets->delete();
    if ($this->remoteAddress === null) {
        unset($_SERVER['REMOTE_ADDR']);
    } else {
        $_SERVER['REMOTE_ADDR'] = $this->remoteAddress;
    }
});

/** The captures kept since this file's test began, newest first, by subject. */
function capturedSince(object $test): array
{
    $kept = [];
    foreach ($test->container->make(CapturedEmailRepository::class)->latest(20) as $capture) {
        $kept[$capture->subject] = [$capture->path, $capture->state];
    }

    return $kept;
}

function sendTheSiteMail(object $test, string $tag): void
{
    $test->container->make(Mailer::class)->send(new MailRequest(
        to: ['team@example.com'],
        subject: 'Site code mail ' . $tag,
        body: '<p>Hello</p>',
    ));
    retrieve_password(get_userdata((int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0])->user_login);
    wp_mail('reader@example.com', 'A plugin mail ' . $tag, 'From another plugin');
}

it('keeps every email on a development site, named by what sent it', function () {
    $this->settings = MailDelivery::answerAs(MailDelivery::CAPTURE);
    $tag = wp_generate_password(6, false);

    sendTheSiteMail($this, $tag);
    $kept = capturedSince($this);
    $reset = array_values(array_filter(array_keys($kept), static fn (string $subject): bool => str_contains($subject, 'Password Reset')));

    expect($this->transport)->toBe([])
        ->and($kept['Site code mail ' . $tag] ?? null)->toBe(['CoreX mail', 'captured'])
        ->and($kept['A plugin mail ' . $tag] ?? null)->toBe(['Other: wp_mail', 'captured'])
        ->and($reset)->not->toBe([])
        ->and($kept[$reset[0]][0])->toBe('WordPress: password reset');
});

it('keeps only CoreX mail when the owner switches capture to CoreX mail', function () {
    $this->settings = MailDelivery::answerAs(MailDelivery::CAPTURE);
    $this->scope = static fn (): string => MailGate::SCOPE_COREX;
    add_filter('pre_option_corex_mail_capture_scope', $this->scope);
    $tag = wp_generate_password(6, false);

    sendTheSiteMail($this, $tag);
    $kept = capturedSince($this);

    expect($kept)->toHaveKey('Site code mail ' . $tag)
        ->and($kept)->not->toHaveKey('A plugin mail ' . $tag)
        ->and($this->transport)->toContain('A plugin mail ' . $tag)
        ->and(count($this->transport))->toBe(2);
});

it('sends as before on a production site with no provider and live delivery off', function () {
    $this->settings = MailDelivery::answerAs(MailDelivery::REFUSE);
    $tag = wp_generate_password(6, false);

    sendTheSiteMail($this, $tag);

    expect(capturedSince($this))->not->toHaveKey('Site code mail ' . $tag)
        ->and($this->transport)->toContain('Site code mail ' . $tag)
        ->and($this->transport)->toContain('A plugin mail ' . $tag)
        ->and(count($this->transport))->toBe(3);
});

it('refuses and records an email it has nowhere to keep', function () {
    $config = $this->container->make(ConfigInterface::class);
    $activity = new RecordingActivityRepository();
    $withoutMail = new Container();
    $withoutMail->instance(\Corex\Activity\ActivityRepository::class, $activity);
    $withoutMail->instance(ActivityService::class, new ActivityService($activity));
    $environment = new SiteEnvironment([[SiteEnvironment::BY_OPTION, new class implements Corex\Support\Config\Source {
        public function has(string $key): bool
        {
            return true;
        }

        public function get(string $key): mixed
        {
            return 'development';
        }
    }]]);
    $interceptor = new WpMailInterceptor(new MailGate($environment, $config, new MailHold(), $withoutMail), new MailPath());

    $answer = $interceptor->intercept(null, ['to' => 'team@example.com', 'subject' => 'Nowhere to keep', 'message' => 'Body']);

    expect($answer)->toBeFalse()
        ->and($activity->events)->toHaveCount(1)
        ->and($activity->events[0]->kind)->toBe('mail.refused')
        ->and($activity->events[0]->area)->toBe(ActivityEvent::AREA_EMAIL)
        ->and($activity->events[0]->context)->toBe(['to' => ['team@example.com'], 'subject' => 'Nowhere to keep']);
});
