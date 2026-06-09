<?php

/**
 * Unit tests for the header-injection guard (spec US2: FR-006, SC-002).
 *
 * A CR, LF, or control character in any header field rejects the message; a clean
 * field set passes.
 *
 * @package Corex\Tests\Unit\Mail
 */

declare(strict_types=1);

use Corex\Email\Security\HeaderGuard;

it('rejects a carriage return or line feed in any field', function (string $field) {
    $reason = (new HeaderGuard())->inspect([$field => "value\r\nBcc: victim@example.com"]);

    expect($reason)->toBeString()->and($reason)->not->toBe('');
})->with(['subject', 'reply-to', 'from', 'display-name']);

it('rejects other control characters', function () {
    expect((new HeaderGuard())->inspect(['subject' => "ok\x00nul"]))->toBeString();
});

it('passes a clean field set', function () {
    expect((new HeaderGuard())->inspect([
        'subject'  => 'Your receipt #1024',
        'reply-to' => 'support@example.com',
    ]))->toBeNull();
});
