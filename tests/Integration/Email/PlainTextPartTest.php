<?php

/**
 * An email leaves with a plain-text version beside the HTML one, when it has one (spec 106,
 * FR-013).
 *
 * Against real WordPress and its own mailer, because what is under test is the message that
 * mailer builds: two parts under `multipart/alternative`.
 *
 * **Nothing is sent.** The message is read at the last moment before WordPress would send it,
 * and the sending is stopped there. An install whose `wp_mail()` is not WordPress's own (an SMTP
 * plugin replaces it, and a development install has one with a real mailbox behind it) is not
 * the code under test and may not stop where WordPress's does, so these are skipped on it.
 *
 * @package Corex\Tests\Integration\Email
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Email\Capture\CapturedEmailRepository;
use Corex\Email\Driver\WpMailDriver;
use Corex\Email\Message\EmailMessage;
use Corex\Email\Studio\WpEmailStudioStore;

function wordPressSendsItsOwnMail(): bool
{
    $defined = str_replace('\\', '/', (string) (new ReflectionFunction('wp_mail'))->getFileName());

    // Its own function, and nothing that answers for it before the mailer is reached.
    return str_ends_with($defined, '/wp-includes/pluggable.php') && has_filter('pre_wp_mail') === false;
}

/**
 * What WordPress's mailer had built for one message when it was stopped before sending it.
 *
 * The sender is answered for the test. WordPress makes one up from the site's host, and its
 * mailer refuses `wordpress@localhost`, which is what a CI site is: the message was then never
 * built, and the first run of this file in CI read an empty string.
 *
 * @throws RuntimeException When WordPress gave up before the message was built, with its reason.
 */
function builtAndNotSent(WpMailDriver $driver, EmailMessage $message): string
{
    $built = '';
    $refused = '';
    $stop = static function (object $mailer) use (&$built): void {
        $mailer->preSend();
        $built = $mailer->getSentMIMEMessage();

        throw new LogicException('Stopped before sending: this test delivers nothing.');
    };
    $from = static fn (): string => 'site@example.com';
    $failed = static function (WP_Error $error) use (&$refused): void {
        $refused = $error->get_error_message();
    };
    add_action('phpmailer_init', $stop, PHP_INT_MAX);
    add_filter('wp_mail_from', $from, PHP_INT_MAX);
    add_action('wp_mail_failed', $failed);

    try {
        $driver->send($message);
    } catch (LogicException) {
        // The stop above.
    } finally {
        remove_action('phpmailer_init', $stop, PHP_INT_MAX);
        remove_filter('wp_mail_from', $from, PHP_INT_MAX);
        remove_action('wp_mail_failed', $failed);
    }
    if ($built === '') {
        throw new RuntimeException('WordPress did not build the message: ' . ($refused !== '' ? $refused : 'no reason given'));
    }

    return $built;
}

it('sends both versions as alternatives of one message', function () {
    $built = builtAndNotSent(Boot::app()->container()->make(WpMailDriver::class), new EmailMessage(
        ['salma@example.com'],
        [],
        [],
        null,
        'Re: your message',
        '<html><body><p>Hello <strong>Salma</strong></p></body></html>',
        plainText: "Hello Salma\n\n-- \nAcme",
    ));

    expect($built)->toContain('Content-Type: multipart/alternative')
        // The mailer names the plain part's character set itself: plain ASCII for these words.
        ->and($built)->toContain('Content-Type: text/plain;')
        ->and($built)->toContain('Content-Type: text/html;')
        ->and($built)->toContain('Hello Salma')
        ->and($built)->toContain('<strong>Salma</strong>');
})->skip(fn (): bool => ! wordPressSendsItsOwnMail(), 'This install replaces wp_mail().');

it('sends a message with no plain-text version as it always was, and leaves none behind for the next', function () {
    $driver = Boot::app()->container()->make(WpMailDriver::class);
    builtAndNotSent($driver, new EmailMessage(['a@example.com'], [], [], null, 'First', '<p>One</p>', plainText: 'One, in plain text'));
    $built = builtAndNotSent($driver, new EmailMessage(['b@example.com'], [], [], null, 'Second', '<p>Two</p>'));

    expect($built)->toContain('Content-Type: text/html; charset=UTF-8')
        ->and($built)->toContain('<p>Two</p>')
        ->and($built)->not->toContain('multipart/alternative')
        ->and($built)->not->toContain('text/plain')
        ->and($built)->not->toContain('in plain text');
})->skip(fn (): bool => ! wordPressSendsItsOwnMail(), 'This install replaces wp_mail().');

// The database does not take a payload that is not text whole, and what it kept could not be
// read: such a record took Email Studio's list down, and nothing would ever have removed it.
it('refuses to keep a record it could not read back, and leaves none behind', function () {
    $store = Boot::app()->container()->make(WpEmailStudioStore::class);
    $before = count($store->all('captured_email'));

    expect(fn () => $store->create('captured_email', '0f8fad5b-d9cb-469f-a165-70867728950e', 'Not text', 0, [
        'capture_id' => '0f8fad5b-d9cb-469f-a165-70867728950e',
        'plain_text' => "Caf\xE9",
    ]))->toThrow(DomainException::class, 'could not be stored')
        ->and(count($store->all('captured_email')))->toBe($before);
});

it('keeps the plain-text version a message was sent with in its capture', function () {
    $captures = new CapturedEmailRepository(Boot::app()->container()->make(WpEmailStudioStore::class));

    $captured = $captures->capture(new EmailMessage(
        ['salma@example.com'],
        [],
        [],
        null,
        'Re: your message',
        '<p>Hello</p><p>Salma</p>',
        plainText: "Hello\n\nSalma",
    ));
    $derived = $captures->capture(new EmailMessage(['salma@example.com'], [], [], null, 'Re: your message', '<p>Hello</p><p>Salma</p>'));
    wp_delete_post($captured->id, true);
    wp_delete_post($derived->id, true);

    // A message that brings none still gets the capture's own reading of its HTML, as before.
    expect($captured->plainText)->toBe("Hello\n\nSalma")
        ->and($derived->plainText)->toContain('Salma')->not->toContain('<p>');
});

/**
 * A reply to the second of two earlier emails, each under an id as CoreX makes them.
 */
function replyToTheSecondOfTwo(): EmailMessage
{
    return new EmailMessage(
        ['salma@example.com'],
        [],
        [],
        null,
        'Re: We received your enquiry',
        '<p>Hello Salma</p>',
        [
            'Message-ID' => '<33333333333343338333@acme.test>',
            'In-Reply-To' => '<22222222222242228222@acme.test>',
            'References' => '<11111111111141118111@acme.test> <22222222222242228222@acme.test>',
        ],
        plainText: 'Hello Salma',
    );
}

// Spec 106, FR-037 and FR-040: an email leaves under the id CoreX chose for it, and says which
// email it answers. Read from the message WordPress's own mailer built.
it('leaves under the id it was given, naming the email it answers', function () {
    $built = builtAndNotSent(Boot::app()->container()->make(WpMailDriver::class), replyToTheSecondOfTwo());

    expect($built)->toContain('Message-ID: <33333333333343338333@acme.test>')
        // One id: the mailer's own was not written beside it.
        ->and(preg_match_all('/^Message-ID:/mi', $built))->toBe(1)
        ->and($built)->toContain('In-Reply-To: <22222222222242228222@acme.test>')
        // Written as it is. The mailer rewrites a header it finds too long as an encoded
        // word, which is not an id to any mail program: the first run of this test read
        // `=?us-ascii?Q?<2222...` here, for an id of 48 characters.
        ->and($built)->not->toContain('=?us-ascii?')
        ->and($built)->toContain('Content-Type: multipart/alternative');
})->skip(fn (): bool => ! wordPressSendsItsOwnMail(), 'This install replaces wp_mail().');

// Through PHP's own mail() a header's value has 47 characters before the mailer rewrites it.
// Two ids do not fit: the one being answered is named, which is the one a mail program needs.
//
// Which way that is, is asked after whoever sets the mail server up has done so. A plugin does
// that on the same hook, at the usual priority or a little after it: asked at the usual
// priority, a site about to send through a mail server was read as sending by mail().
it('names as many of the earlier emails as the way it is sent leaves room for, the latest first', function (string $transport, int $setUpAt, string $named) {
    $by = static function (object $mailer) use ($transport): void {
        $mailer->Mailer = $transport;
    };
    add_action('phpmailer_init', $by, $setUpAt);

    try {
        $built = builtAndNotSent(Boot::app()->container()->make(WpMailDriver::class), replyToTheSecondOfTwo());
    } finally {
        remove_action('phpmailer_init', $by, $setUpAt);
    }

    expect($built)->toMatch('/^References: ' . preg_quote($named, '/') . '\r?$/m')
        ->and($built)->not->toContain('=?us-ascii?');
})->with([
    'by mail(): the one it answers' => ['mail', 1, '<22222222222242228222@acme.test>'],
    'by a mail server: both' => ['smtp', 1, '<11111111111141118111@acme.test> <22222222222242228222@acme.test>'],
    'by a mail server set up at the usual priority' => ['smtp', 10, '<11111111111141118111@acme.test> <22222222222242228222@acme.test>'],
    'by a mail server set up after it' => ['smtp', 20, '<11111111111141118111@acme.test> <22222222222242228222@acme.test>'],
])->skip(fn (): bool => ! wordPressSendsItsOwnMail(), 'This install replaces wp_mail().');

// A site on a long host, sending by mail(): the id of the email being answered is itself more
// than the mailer leaves as written. The reply still goes, under its own id, as an email of
// its own; what must not happen is an id rewritten into something no mail program reads.
it('goes without naming an email whose id the way it is sent has no room for', function () {
    $onALongHost = '<1111111122@staging.a-client-of-ours-indeed.example.com>';
    $built = builtAndNotSent(Boot::app()->container()->make(WpMailDriver::class), new EmailMessage(
        ['salma@example.com'],
        [],
        [],
        null,
        'Re: We received your enquiry',
        '<p>Hello Salma</p>',
        ['Message-ID' => '<2222222233@staging.a-client-of-ours-indeed.example.com>', 'In-Reply-To' => $onALongHost, 'References' => $onALongHost],
    ));

    expect(strlen($onALongHost))->toBeGreaterThan(\Corex\Mail\MessageId::FITS)
        ->and($built)->toContain('Message-ID: <2222222233@staging.a-client-of-ours-indeed.example.com>')
        ->and($built)->not->toContain('In-Reply-To')->not->toContain('References')
        ->and($built)->not->toContain('=?us-ascii?')
        ->and($built)->toContain('<p>Hello Salma</p>');
})->skip(fn (): bool => ! wordPressSendsItsOwnMail(), 'This install replaces wp_mail().');

/**
 * Every message WordPress's mailer built while one email was sent through the driver, in the
 * order it built them, and what the driver answered. None of them is delivered: each is read
 * and then left with nobody to go to, which the mailer refuses.
 *
 * @param array<string,array{0:callable,1:int}> $listening Hook name to its listener and priority,
 *                                                         on for the length of the send.
 *
 * @return array{built:list<string>,went:bool}
 */
function builtWhileSending(EmailMessage $message, array $listening): array
{
    $built = [];
    $read = static function (object $mailer) use (&$built): void {
        $mailer->preSend();
        $built[] = $mailer->getSentMIMEMessage();
        $mailer->clearAllRecipients();
    };
    add_action('phpmailer_init', $read, PHP_INT_MAX);
    foreach ($listening as $hook => [$listener, $priority]) {
        add_filter($hook, $listener, $priority);
    }

    try {
        $went = Boot::app()->container()->make(WpMailDriver::class)->send($message);
    } finally {
        remove_action('phpmailer_init', $read, PHP_INT_MAX);
        foreach ($listening as $hook => [$listener, $priority]) {
            remove_filter($hook, $listener, $priority);
        }
    }

    return ['built' => $built, 'went' => $went];
}

/** A sender WordPress's mailer takes, for an install whose own would be `wordpress@localhost`. */
function aSenderTheMailerTakes(): array
{
    return [static fn (): string => 'site@example.com', PHP_INT_MAX];
}

// WordPress says how an email went before `wp_mail()` returns, and something may send an email
// of its own on hearing it: a notice to the team that a reply failed. WordPress has not
// emptied its mailer of the reply's id by then.
it('leaves its id on no email that something sends on hearing how it went', function (Closure $notice, string $itsId) {
    $told = false;
    $sent = builtWhileSending(replyToTheSecondOfTwo(), [
        'wp_mail_from' => aSenderTheMailerTakes(),
        'wp_mail_failed' => [static function () use (&$told, $notice): void {
            if (! $told) {
                $told = true;
                $notice();
            }
        }, 10],
    ]);
    $second = $sent['built'][1] ?? '';

    expect($sent['went'])->toBeFalse()
        ->and($sent['built'])->toHaveCount(2)
        ->and($second)->toContain('A reply was not sent')
        ->and($second)->not->toContain('33333333333343338333')
        ->and($second)->not->toContain('In-Reply-To')->not->toContain('References')
        // Nor the reply's plain-text version: it is handed to the reply and to nothing else.
        ->and($second)->not->toContain('multipart/alternative')->not->toContain('Hello Salma')
        ->and(preg_match_all('/^Message-ID: <[^>\s]+>/mi', $second))->toBe(1)
        ->and($itsId === '' || str_contains($second, 'Message-ID: ' . $itsId))->toBeTrue();
})->with([
    'by wp_mail()' => [static fn () => wp_mail('team@example.com', 'A reply was not sent', 'The mail server refused it.'), ''],
    // An email of CoreX's own, sent the same way from inside the first: it leaves under its own id.
    'through this driver, under an id of its own' => [
        static fn () => Boot::app()->container()->make(WpMailDriver::class)->send(new EmailMessage(
            ['team@example.com'],
            [],
            [],
            null,
            'A reply was not sent',
            '<p>The mail server refused it.</p>',
            ['Message-ID' => '<44444444444444448444@acme.test>'],
        )),
        '<44444444444444448444@acme.test>',
    ],
])->skip(fn (): bool => ! wordPressSendsItsOwnMail(), 'This install replaces wp_mail().');

// An id something else gave that second email is not CoreX's to take off.
it('leaves an email sent meanwhile the id something else gave it', function () {
    $told = false;
    $sent = builtWhileSending(replyToTheSecondOfTwo(), [
        'wp_mail_from' => aSenderTheMailerTakes(),
        'wp_mail_failed' => [static function () use (&$told): void {
            if (! $told) {
                $told = true;
                wp_mail('team@example.com', 'A reply was not sent', 'The mail server refused it.');
            }
        }, 10],
        // A helpdesk plugin, giving its notices ids of its own.
        'phpmailer_init' => [static function (object $mailer): void {
            if ($mailer->Subject === 'A reply was not sent') {
                $mailer->MessageID = '<ticket-81@helpdesk.example>';
            }
        }, 10],
    ]);

    expect($sent['built'][1] ?? '')->toContain('Message-ID: <ticket-81@helpdesk.example>');
})->skip(fn (): bool => ! wordPressSendsItsOwnMail(), 'This install replaces wp_mail().');

// The first email to reach the mailer after the driver sent one is not always the driver's.
// WordPress refuses a sender before it reaches the mailer's hook, and says so at once: an
// email sent on hearing that reaches the hook first, and the reply never does.
it('hands nothing of a reply that never reached the mailer to the email that did', function () {
    $asked = 0;
    $told = false;
    $sent = builtWhileSending(replyToTheSecondOfTwo(), [
        // The reply's sender is one the mailer refuses, as `wordpress@localhost` is; the notice's is not.
        'wp_mail_from' => [static function () use (&$asked): string {
            return ++$asked === 1 ? 'not an address' : 'site@example.com';
        }, PHP_INT_MAX],
        'wp_mail_failed' => [static function () use (&$told): void {
            if (! $told) {
                $told = true;
                wp_mail('team@example.com', 'A reply was not sent', 'Its sender was refused.');
            }
        }, 10],
    ]);
    $notice = $sent['built'][0] ?? '';

    expect($sent['went'])->toBeFalse()
        ->and($sent['built'])->toHaveCount(1)
        ->and($notice)->toContain('A reply was not sent')
        ->and($notice)->not->toContain('33333333333343338333')
        ->and($notice)->not->toContain('In-Reply-To')->not->toContain('References')
        ->and($notice)->not->toContain('multipart/alternative')->not->toContain('Hello Salma');
})->skip(fn (): bool => ! wordPressSendsItsOwnMail(), 'This install replaces wp_mail().');

// A plugin that copies every email to somebody sends its copy from the `wp_mail` filter, which
// is before the email it copies has reached the mailer.
it('hands a reply what is the reply\'s when another email is sent from inside its sending first', function () {
    $copied = false;
    $sent = builtWhileSending(replyToTheSecondOfTwo(), [
        'wp_mail_from' => aSenderTheMailerTakes(),
        'wp_mail' => [static function (array $email) use (&$copied): array {
            if (! $copied) {
                $copied = true;
                wp_mail('archive@example.com', 'Copy: ' . $email['subject'], 'A copy for the archive.');
            }

            return $email;
        }, 10],
    ]);
    [$copy, $reply] = $sent['built'] + ['', ''];

    expect($sent['built'])->toHaveCount(2)
        ->and($copy)->toContain('A copy for the archive.')
        ->and($copy)->not->toContain('33333333333343338333')->not->toContain('In-Reply-To')
        ->and($copy)->not->toContain('multipart/alternative')
        // And the reply is still the reply.
        ->and($reply)->toContain('Message-ID: <33333333333343338333@acme.test>')
        ->and($reply)->toContain('In-Reply-To: <22222222222242228222@acme.test>')
        ->and($reply)->toContain('multipart/alternative')->toContain('Hello Salma');
})->skip(fn (): bool => ! wordPressSendsItsOwnMail(), 'This install replaces wp_mail().');

// What the driver knows its own email by at the mailer is taken off there.
it('sends nothing of its own making with an email but what it was asked to', function () {
    $built = builtAndNotSent(Boot::app()->container()->make(WpMailDriver::class), replyToTheSecondOfTwo());

    expect($built)->not->toContain('X-Corex')
        ->and(preg_match_all('/^X-[A-Za-z-]+:/m', $built))->toBe(preg_match_all('/^X-(Mailer|Priority):/m', $built));
})->skip(fn (): bool => ! wordPressSendsItsOwnMail(), 'This install replaces wp_mail().');

// WordPress empties its mailer's body and headers before each email, and not its id. Left
// there, the id of one email was the id of the next one anything on the site sent.
it('leaves nothing of one email on the next: not its id, and not what it answered', function () {
    $driver = Boot::app()->container()->make(WpMailDriver::class);
    builtAndNotSent($driver, replyToTheSecondOfTwo());

    global $phpmailer;
    // What the mailer still holds when CoreX has finished with it, for whatever sends next.
    $left = $phpmailer->MessageID;
    $next = builtAndNotSent($driver, new EmailMessage(['salma@example.com'], [], [], null, 'The next one', '<p>Hello</p>'));

    expect($next)->not->toContain('acme.test')
        ->and($next)->not->toContain('In-Reply-To')->not->toContain('References')
        ->and(preg_match_all('/^Message-ID: <[^>\s]+>/mi', $next))->toBe(1)
        ->and($left)->toBe('');
})->skip(fn (): bool => ! wordPressSendsItsOwnMail(), 'This install replaces wp_mail().');

// A header is written only as what it has to be. One taken as it was given could carry a
// second header behind it, and a recipient nobody sees.
it('writes none of a message\'s headers that is not an id, and no header it does not know', function () {
    $built = builtAndNotSent(Boot::app()->container()->make(WpMailDriver::class), new EmailMessage(['salma@example.com'], [], [], null, 'Subject', '<p>Hello</p>', [
        'Message-ID' => "<a@acme.test>\r\nBcc: hidden@example.com",
        'In-Reply-To' => 'not an id',
        'References' => "<kept@acme.test> junk\r\nBcc: other@example.com",
        'Bcc' => 'third@example.com',
        'X-Anything' => 'at all',
    ]));

    expect($built)->not->toContain('hidden@example.com')->not->toContain('other@example.com')
        ->and($built)->not->toContain('third@example.com')->not->toContain('X-Anything')
        ->and($built)->not->toContain('In-Reply-To')
        ->and($built)->toMatch('/^References: <kept@acme\.test>\r?$/m')
        // It leaves under an id the mailer made, since the one given was not one.
        ->and(preg_match_all('/^Message-ID: <[^>\s]+>/mi', $built))->toBe(1)
        ->and($built)->not->toContain('<a@acme.test>');
})->skip(fn (): bool => ! wordPressSendsItsOwnMail(), 'This install replaces wp_mail().');

// WordPress skips the listeners at the next priority when one before them takes itself off
// the hook as it runs, and something listens earlier still. CoreX's own did: where another
// plugin set up the mail server at a later priority, a reply left without its settings.
it('keeps nothing that listens to the mailer after it from running', function (array $others) {
    $ran = [];
    $listeners = [];
    foreach ($others as $priority) {
        $listeners[$priority] = static function () use (&$ran, $priority): void {
            $ran[] = $priority;
        };
        add_action('phpmailer_init', $listeners[$priority], $priority);
    }

    try {
        builtAndNotSent(Boot::app()->container()->make(WpMailDriver::class), replyToTheSecondOfTwo());
    } finally {
        foreach ($listeners as $priority => $listener) {
            remove_action('phpmailer_init', $listener, $priority);
        }
    }

    expect($ran)->toBe($others);
})->with([
    'one before it and one after' => [[5, 20]],
    'one before it and two after' => [[1, 11, 99]],
    'only after it' => [[20, 99]],
])->skip(fn (): bool => ! wordPressSendsItsOwnMail(), 'This install replaces wp_mail().');

// FR-042: a site's own email, sent through the mailer a site is given, leaves under an id made
// of its request's. That is what the site names when it says which email a reply answers.
it('sends an email a site asks for under an id made of its request\'s', function () {
    $request = new \Corex\Mail\MailRequest(to: ['salma@example.com'], subject: 'We have your request', body: '<p>Thank you</p>');
    $built = '';
    $read = static function (object $mailer) use (&$built): void {
        $mailer->preSend();
        $built = $mailer->getSentMIMEMessage();
        // With nobody to go to, the mailer refuses it: this test delivers nothing.
        $mailer->clearAllRecipients();
    };
    $from = static fn (): string => 'site@example.com';
    add_action('phpmailer_init', $read, PHP_INT_MAX);
    add_filter('wp_mail_from', $from, PHP_INT_MAX);

    try {
        $result = Boot::app()->container()->make(\Corex\Email\RequestMailer::class)->attempt($request);
    } finally {
        remove_action('phpmailer_init', $read, PHP_INT_MAX);
        remove_filter('wp_mail_from', $from, PHP_INT_MAX);
    }
    // The mail log's row of the email that was refused.
    if ($result->logId !== null) {
        wp_delete_post((int) $result->logId, true);
    }
    $expected = \Corex\Mail\MessageId::of($request->requestId, (string) wp_parse_url(home_url('/'), PHP_URL_HOST));

    expect($expected)->not->toBe('')
        ->and($result->state)->toBe(\Corex\Mail\MailResult::STATE_FAILED)
        ->and($built)->toContain('Message-ID: ' . $expected)
        ->and(preg_match_all('/^Message-ID:/mi', $built))->toBe(1);
})->skip(fn (): bool => ! wordPressSendsItsOwnMail(), 'This install replaces wp_mail().');
