<?php

/**
 * Removing what Email Studio keeps of one attempt, in WordPress (spec 105, FR-011).
 *
 * A record's payload is one serialized value, so an attempt is found by looking for its id
 * inside it. Against real WordPress because that search is the database's: it has to find the
 * attempt, the attempt made again from it and the captured copy, and nothing that merely
 * resembles them.
 *
 * @package Corex\Tests\Integration\Email
 */

declare(strict_types=1);

use Corex\Email\Studio\WpEmailStudioStore;

beforeEach(function () {
    if (! class_exists(WpEmailStudioStore::class)) {
        $this->markTestSkipped('The email add-on is not installed.');
    }
    $this->store = new WpEmailStudioStore();
    if (! post_type_exists(WpEmailStudioStore::POST_TYPE)) {
        $this->store->registerPostType();
    }
    $this->made = [];
});

afterEach(function () {
    foreach ($this->made ?? [] as $id) {
        wp_delete_post($id, true);
    }
});

it('removes an attempt, the attempt made again from it and their captured copies, and nothing else', function () {
    $attempt = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    $again = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
    $other = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
    $record = fn (string $type, string $slug, array $payload): int => $this->made[] = $this->store->create($type, $slug, 'Re: your message', 0, $payload);

    $first = $record('email_attempt', $attempt, ['attempt_id' => $attempt, 'uuid' => $attempt, 'subject' => 'Re: your message']);
    $resent = $record('email_attempt', $again, ['attempt_id' => $again, 'uuid' => $again, 'parent_attempt_id' => $attempt]);
    $captured = $record('captured_email', 'dddddddd-dddd-4ddd-8ddd-dddddddddddd', ['attempt_id' => $attempt, 'to' => ['salma@example.com'], 'body' => 'Thank you, Salma.']);
    // Another submission's attempt, whose subject happens to quote the first one's id.
    $unrelated = $record('email_attempt', $other, ['attempt_id' => $other, 'uuid' => $other, 'subject' => 'About ' . $attempt]);
    // And a template, which is not about an attempt at all.
    $template = $record('template', 'welcome', ['html_body' => 'See ' . $attempt]);

    $removed = $this->store->forgetAttempt($attempt);

    expect($removed)->toBe(3)
        ->and(get_post($first))->toBeNull()
        ->and(get_post($resent))->toBeNull()
        ->and(get_post($captured))->toBeNull()
        ->and(get_post($unrelated))->not->toBeNull()
        ->and(get_post($template))->not->toBeNull()
        ->and($this->store->forgetAttempt($attempt))->toBe(0);
});
