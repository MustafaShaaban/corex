<?php

/**
 * Unit tests for the WP-Cron mail dispatcher (#271): what it stores and schedules for a queued
 * request, what one firing of the hook sends, and what happens when nothing could be scheduled.
 *
 * WordPress's option and cron functions are stood in for by a store and a list in memory.
 *
 * @package Corex\Tests\Unit\Email
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Email\Queue\CronMailDispatcher;
use Corex\Email\Queue\MailQueueDispatcher;
use Corex\Mail\Mailer;
use Corex\Mail\MailRequest;

beforeEach(function () {
    $this->stored   = [];
    $this->events    = [];
    $this->schedules = true;

    Functions\when('add_option')->alias(function (string $name, mixed $value, string $deprecated, bool $autoload): bool {
        $this->stored[$name] = ['value' => $value, 'autoload' => $autoload];

        return true;
    });
    Functions\when('get_option')->alias(fn (string $name): mixed => $this->stored[$name]['value'] ?? false);
    Functions\when('delete_option')->alias(function (string $name): bool {
        unset($this->stored[$name]);

        return true;
    });
    Functions\when('wp_schedule_single_event')->alias(function (int $at, string $hook, array $args): bool {
        if ($this->schedules) {
            $this->events[] = ['hook' => $hook, 'args' => $args];
        }

        return $this->schedules;
    });
});

function cronQueueMailer(): Mailer
{
    return new class implements Mailer {
        /** @var list<MailRequest> */
        public array $sent = [];

        public function send(MailRequest $request): void
        {
            $this->sent[] = $request;
        }
    };
}

function leadNotification(): MailRequest
{
    return new MailRequest(
        to: ['owner@example.test'],
        templateName: 'contact-notification',
        context: ['name' => 'Sam'],
        from: 'leads@example.test',
    );
}

it('keeps a queued request out of the cron array and sends nothing yet', function () {
    $mailer = cronQueueMailer();

    (new CronMailDispatcher($mailer))->enqueue(leadNotification());

    $option = array_key_first($this->stored);
    $event  = $this->events[0];

    expect($mailer->sent)->toBe([])
        ->and($this->events)->toHaveCount(1)
        ->and($event['hook'])->toBe(MailQueueDispatcher::HOOK)
        // The event carries an id and nothing of the message.
        ->and(array_keys($event['args'][0]))->toBe(['queued'])
        ->and($option)->toBe('corex_mail_queued_' . $event['args'][0]['queued'])
        ->and($this->stored[$option]['autoload'])->toBeFalse()
        ->and($this->stored[$option]['value']['to'])->toBe(['owner@example.test']);
});

it('asks WordPress to look for due events when the request ends', function () {
    (new CronMailDispatcher(cronQueueMailer()))->enqueue(leadNotification());

    expect(has_action('shutdown', 'wp_cron'))->not->toBeFalse();
});

it('sends the request it queued when the hook fires, as it was queued, and only once', function () {
    $mailer     = cronQueueMailer();
    $dispatcher = new CronMailDispatcher($mailer);
    $dispatcher->enqueue(leadNotification());
    $fired = $this->events[0]['args'][0];

    $dispatcher->handle($fired);
    // WP-Cron can fire an event twice when two requests spawn it at once.
    $dispatcher->handle($fired);

    expect($mailer->sent)->toHaveCount(1)
        ->and($mailer->sent[0]->to)->toBe(['owner@example.test'])
        ->and($mailer->sent[0]->templateName)->toBe('contact-notification')
        ->and($mailer->sent[0]->context)->toBe(['name' => 'Sam'])
        ->and($mailer->sent[0]->from)->toBe('leads@example.test')
        ->and($this->stored)->toBe([]);
});

it('sends at once, and leaves nothing stored, when WordPress will not schedule the event', function () {
    $this->schedules = false;
    $mailer          = cronQueueMailer();

    (new CronMailDispatcher($mailer))->enqueue(leadNotification());

    expect($mailer->sent)->toHaveCount(1)
        ->and($this->stored)->toBe([]);
});
