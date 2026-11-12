<?php

/**
 * A code-defined form's notification on an upgraded site and on a new one (spec 120, FR-023 and
 * FR-018): as before where the site had submissions when this release first ran, designed where
 * it had none.
 *
 * Every email here is kept in Email Studio: the site answers as a development site.
 *
 * @package Corex\Tests\Integration\Forms
 */

declare(strict_types=1);

use Corex\Email\Capture\CapturedEmailRepository;
use Corex\Forms\Listeners\NotificationStyle;
use Corex\Forms\Listeners\SendEmailListener;
use Corex\Forms\Submission\FormSubmittedEvent;
use Corex\Forms\Submission\NotificationDispatcher;
use Corex\Forms\Submission\SubmissionRepository;
use Corex\Mail\Capture\WpMailInterceptor;
use Corex\Tests\Support\CreatedPosts;
use Corex\Tests\Support\MailDelivery;

beforeEach(function () {
    if (! class_exists(CapturedEmailRepository::class)) {
        $this->markTestSkipped('The email add-on is not active on this install.');
    }
    $this->container = Corex\Boot::app()->container();
    $this->container->make(WpMailInterceptor::class)->register();
    $this->posts = CreatedPosts::watch('corex_email_asset', 'corex_email_log', 'corex_submission');
    $this->settings = MailDelivery::answerAs(MailDelivery::CAPTURE);
    $this->style = null;
});

afterEach(function () {
    MailDelivery::stop($this->settings);
    if ($this->style !== null) {
        remove_filter('pre_option_' . NotificationStyle::OPTION, $this->style);
    }
    $this->posts->delete();
});

/** The kept notification of a code-defined form sent on a site whose style is this. */
function styledNotification(object $test, string $style): Corex\Email\Capture\CapturedEmail
{
    $test->style = static fn (): string => $style;
    add_filter('pre_option_' . NotificationStyle::OPTION, $test->style);
    $slug = 'callback-' . strtolower(wp_generate_password(6, false, false));
    $test->container->make(SendEmailListener::class)->dispatch(new FormSubmittedEvent($slug, ['phone' => '01016999700']));

    return $test->container->make(CapturedEmailRepository::class)->latest(1)[0];
}

it('FR-023: sends an upgraded site\'s code-defined form notification as before, its HTML as it was', function () {
    $kept = styledNotification($this, NotificationStyle::AS_BEFORE);

    expect($kept->body)->toBe(NotificationDispatcher::htmlBody(['phone' => '01016999700']))
        ->and($kept->body)->not->toContain('This message was sent by');
});

it('FR-018: sends a new site\'s code-defined form notification in the one frame', function () {
    $kept = styledNotification($this, NotificationStyle::DESIGNED);

    expect($kept->body)->toContain('This message was sent by')
        ->and($kept->body)->toContain('01016999700');
});

it('FR-023: knows whether the site has stored a form submission, one in the trash too', function () {
    $submissions = $this->container->make(SubmissionRepository::class);
    $id = $submissions->save('style-probe', ['phone' => '1']);
    wp_trash_post($id);

    expect($submissions->any())->toBeTrue();
});
