<?php

/**
 * A finished message on the shared mailer may carry its own plain-text version (spec 120,
 * story 7): optional, last, kept through the queue, and none when it is empty.
 *
 * @package Corex\Tests\Unit\Mail
 */

declare(strict_types=1);

use Corex\Email\Queue\MailRequestPayload;
use Corex\Mail\MailRequest;

it('is built the old way, unchanged, with no plain text', function () {
    $request = new MailRequest(['a@example.com'], null, [], 'Subject', '<p>Hi</p>', 'reply@example.com');

    expect($request->plainText)->toBeNull()
        ->and($request->body)->toBe('<p>Hi</p>')
        ->and($request->replyTo)->toBe('reply@example.com');
});

it('keeps a plain text as given, and takes one with nothing in it as none', function (?string $given, ?string $kept) {
    expect((new MailRequest(to: ['a@example.com'], body: '<p>Hi</p>', plainText: $given))->plainText)->toBe($kept);
})->with([
    'words' => ["Hi,\n\nsee you", "Hi,\n\nsee you"],
    'empty' => ['', null],
    'spaces and lines' => [" \n\t ", null],
    'none' => [null, null],
]);

it('carries the plain text through the queue and back', function () {
    $request = new MailRequest(to: ['a@example.com'], subject: 'S', body: '<p>Hi</p>', from: 'info@example.com', plainText: 'Hi');

    $back = MailRequestPayload::fromArray(MailRequestPayload::toArray($request));

    expect($back->plainText)->toBe('Hi')
        ->and($back->from)->toBe('info@example.com')
        ->and(MailRequestPayload::fromArray(['to' => ['a@example.com'], 'body' => '<p>Hi</p>'])->plainText)->toBeNull();
});
