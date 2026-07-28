<?php

/**
 * A message can name the mailbox it leaves from (#150).
 *
 * Not a preference: SMTP relays route on the From address, and in FluentSMTP — and most relay
 * plugins — each configured mailbox is its own authenticated connection. Without a per-message
 * seam, a site with `info@`, `noreply@` and `contact@` all properly configured still sends
 * everything through whichever one is the global default.
 *
 * @package Corex\Tests\Unit\Mail
 */

declare(strict_types=1);

use Corex\Email\Queue\ActionSchedulerDispatcher;
use Corex\Mail\MailRequest;

it('carries no sender by default, so every existing caller is unchanged', function () {
    expect((new MailRequest(['someone@example.test']))->from)->toBeNull();
});

it('carries the mailbox a caller named', function () {
    $request = new MailRequest(
        ['someone@example.test'],
        subject: 'Hello',
        body: 'Body',
        from: 'info@example.test',
    );

    expect($request->from)->toBe('info@example.test');
});

/**
 * The arm that matters most, and the one an implementation forgets.
 *
 * Without it, a message sent immediately and the same message sent through Action Scheduler leave
 * from different addresses — an inconsistency nobody notices until a client asks why some of their
 * confirmations come from the wrong mailbox.
 */
it('keeps its sender across the queue', function () {
    $original = new MailRequest(
        ['someone@example.test'],
        templateName: 'welcome',
        context: ['name' => 'Sam'],
        replyTo: 'contact@example.test',
        from: 'info@example.test',
    );

    $restored = ActionSchedulerDispatcher::fromArray(
        ActionSchedulerDispatcher::toArray($original),
    );

    expect($restored->from)->toBe('info@example.test')
        ->and($restored->replyTo)->toBe('contact@example.test')
        ->and($restored->templateName)->toBe('welcome');
});

it('restores a queued message that carries no sender as carrying none', function () {
    $restored = ActionSchedulerDispatcher::fromArray(
        ActionSchedulerDispatcher::toArray(new MailRequest(['someone@example.test'])),
    );

    // Not '' — an empty string would reach the driver as a request for a mailbox named nothing.
    expect($restored->from)->toBeNull();
});
