<?php

/**
 * The subject a reply starts with (spec 106, FR-035).
 *
 * @package Corex\Tests\Unit\Submissions
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Submissions\ReplySubject;

beforeEach(function () {
    // Answered in another language, to see what of a subject is translated and what is not.
    Functions\when('__')->alias(static fn (string $text): string => $text === 'your message' ? 'Ihre Nachricht' : $text);
});

it('starts a reply\'s subject from the form the submission came from', function (array $record, string $knownAs, string $subject) {
    expect(ReplySubject::startingFor($record, $knownAs))->toBe($subject);
})->with([
    'a form built in the admin, by the name it had when this was sent' => [
        ['form' => 'request-a-quote', 'flow' => 'Request a quote'],
        'Quotes, renamed since',
        'Re: Request a quote',
    ],
    'a form defined in code, by the name it gives itself' => [
        ['form' => 'quote_v2', 'flow' => 'quote_v2'],
        'Request a quote',
        'Re: Request a quote',
    ],
    'a form nothing names, by its slug as a person would read it' => [
        ['form' => 'book_a-visit', 'flow' => ''],
        '',
        'Re: Book A Visit',
    ],
    'a form the site knows only by its slug' => [
        ['form' => 'contact', 'flow' => 'contact'],
        'contact',
        'Re: Contact',
    ],
    'a name with spaces around it' => [
        ['form' => 'lead', 'flow' => '  Kitchen enquiry  '],
        '',
        'Re: Kitchen enquiry',
    ],
]);

it('does not answer twice a form whose own name answers already', function (string $name) {
    expect(ReplySubject::startingFor(['form' => 'lead', 'flow' => $name]))->toBe($name);
})->with(['RE: your enquiry', 'Re : votre demande', 're:Offer']);

// WordPress keeps a title with its `&` and `<` as entities. A subject is not markup.
it('writes a form\'s name as the text it is, not as WordPress stores it', function () {
    expect(ReplySubject::startingFor(['form' => 'sales', 'flow' => 'Sales &amp; Support &lt;5 people &#8211; Cairo']))
        ->toBe('Re: Sales & Support <5 people – Cairo');
});

// "Re:" is what a mail program keeps a conversation together by, in every language.
it('writes "Re:" as it is in every language, and translates only what stands for a name', function () {
    expect(ReplySubject::startingFor([]))->toBe('Re: Ihre Nachricht')
        ->and(ReplySubject::startingFor(['form' => '', 'flow' => ' ']))->toBe('Re: Ihre Nachricht')
        ->and(ReplySubject::startingFor(['form' => 'contact', 'flow' => 'Kontakt']))->toBe('Re: Kontakt');
});

it('is one line of a subject\'s length, whatever a form was called', function () {
    $injected = ReplySubject::startingFor(['form' => 'lead', 'flow' => "Enquiry\r\nBcc: x@example.com"]);
    $long = ReplySubject::startingFor(['form' => 'lead', 'flow' => str_repeat('طلب عرض سعر ', 60)]);

    expect($injected)->toBe('Re: Enquiry Bcc: x@example.com')
        ->and(mb_strlen($long))->toBe(204)
        ->and(mb_check_encoding($long, 'UTF-8'))->toBeTrue();
});
