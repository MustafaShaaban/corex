<?php

/**
 * Integration test: a send is deferred on a site that has no Action Scheduler (#271).
 *
 * From the seam a form holds, through the container's own wiring, to `wp_mail()`: with the
 * `mail_queue` flag on, the request does not send, a cron event is asked for, and the hook that
 * event fires delivers the message.
 *
 * The event is caught as WordPress is asked to schedule it and is never written to the cron
 * array: on a development install a real event could be run by another request, outside this
 * test and without its `pre_wp_mail` stand-in.
 *
 * @package Corex\Tests\Integration\Mail
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Email\Queue\MailQueueDispatcher;
use Corex\Mail\Mailer;
use Corex\Mail\MailRequest;
use Corex\Tests\Support\CreatedPosts;

beforeEach(function () {
    $this->emailLogs    = CreatedPosts::watch('corex_email_log');
    $this->flagBefore   = get_option('corex_features_mail_queue', null);
    $this->handedToMail = 0;
    $this->asked        = [];

    update_option('corex_features_mail_queue', '1');
    add_filter('pre_wp_mail', function () {
        $this->handedToMail++;

        return true;
    });
    add_filter('pre_schedule_event', function ($scheduled, object $event) {
        if ($event->hook !== MailQueueDispatcher::HOOK) {
            return $scheduled;
        }
        $this->asked[] = $event->args;

        return true;
    }, 10, 2);
});

afterEach(function () {
    remove_all_filters('pre_wp_mail');
    remove_all_filters('pre_schedule_event');
    remove_action('shutdown', 'wp_cron');
    $this->flagBefore === null
        ? delete_option('corex_features_mail_queue')
        : update_option('corex_features_mail_queue', $this->flagBefore);
    $this->emailLogs->delete();
});

it('does not send inside the request, and the queue hook delivers the message', function () {
    Boot::app()->container()->make(Mailer::class)->send(new MailRequest(
        to: ['admin@example.com'],
        templateName: 'contact-notification',
        context: ['submission' => ['name' => 'B', 'email' => 'b@example.com', 'message' => 'Hi'], 'form' => ['slug' => 'contact']],
    ));

    expect($this->handedToMail)->toBe(0)
        ->and($this->asked)->toHaveCount(1)
        ->and(has_action('shutdown', 'wp_cron'))->not->toBeFalse();

    $stored = 'corex_mail_queued_' . $this->asked[0][0]['queued'];
    expect(get_option($stored))->toBeArray();

    do_action_ref_array(MailQueueDispatcher::HOOK, $this->asked[0]);

    expect($this->handedToMail)->toBe(1)
        ->and($this->emailLogs->ids())->toHaveCount(1)
        ->and(get_option($stored))->toBeFalse();
})->skip(
    fn (): bool => function_exists('as_enqueue_async_action'),
    'Action Scheduler is installed here, so CoreX Mail queues through it.',
);
