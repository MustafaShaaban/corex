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
        builtAndNotSent(Boot::app()->container()->make(WpMailDriver::class), new EmailMessage(
            ['salma@example.com'],
            [],
            [],
            null,
            'Re: your message',
            '<p>Hello Salma</p>',
            plainText: 'Hello Salma',
        ));
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

// And the words of one email are still not the next one's, sent while this one is on its way:
// `wp_mail()` fires this hook again for an email sent from inside the first one's sending.
it('gives its plain-text version to one email only, though it stays on the hook until that one has gone', function () {
    $second = 'not asked';
    // What listens at CoreX's own priority is asked again, as a second email would ask it.
    $again = static function (object $mailer) use (&$second): void {
        global $wp_filter;
        $other = clone $mailer;
        $other->AltBody = '';
        foreach ($wp_filter['phpmailer_init']->callbacks[10] ?? [] as $listener) {
            $listener['function']($other);
        }
        $second = (string) $other->AltBody;
    };
    add_action('phpmailer_init', $again, 11);

    try {
        $built = builtAndNotSent(
            Boot::app()->container()->make(WpMailDriver::class),
            new EmailMessage(['a@example.com'], [], [], null, 'First', '<p>One</p>', plainText: 'One, in plain text'),
        );
    } finally {
        remove_action('phpmailer_init', $again, 11);
    }

    expect($built)->toContain('One, in plain text')
        ->and($second)->toBe('');
})->skip(fn (): bool => ! wordPressSendsItsOwnMail(), 'This install replaces wp_mail().');
