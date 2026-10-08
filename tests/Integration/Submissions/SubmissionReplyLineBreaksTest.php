<?php

/**
 * A reply keeps the paragraphs it was typed with (spec 106, slice 0).
 *
 * The reply is typed in a plain text box and sent as HTML. Nothing turned a line break into
 * markup, so three paragraphs reached the recipient as one block: read from the code on
 * 2026-10-08, while specifying the reply editor. Against real WordPress because the conversion is
 * WordPress's own, and a stub of it would prove nothing.
 *
 * @package Corex\Tests\Integration\Submissions
 */

declare(strict_types=1);

use Corex\Config\Submissions\SubmissionReply;

it('sends what was typed on separate lines as separate paragraphs and lines', function () {
    $reply = new SubmissionReply('Re: your message', "Hello Salma,\n\nThank you for writing.\nWe will call you tomorrow.\n\nRegards");

    expect($reply->htmlBody)
        ->toContain('<p>Hello Salma,</p>')
        ->toContain("<p>Thank you for writing.<br />\nWe will call you tomorrow.</p>")
        ->toContain('<p>Regards</p>');
});

it('leaves a reply that is already marked up as it is', function () {
    $reply = new SubmissionReply('Re: your message', '<p>Thanks, Sam.</p><ul><li>One</li><li>Two</li></ul>');

    expect(preg_replace('/\s+/', '', $reply->htmlBody))->toBe('<p>Thanks,Sam.</p><ul><li>One</li><li>Two</li></ul>');
});

it('still refuses a reply with no words in it', function () {
    expect(fn () => new SubmissionReply('Re: your message', " \n\n "))->toThrow(InvalidArgumentException::class);
});
