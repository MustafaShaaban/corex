<?php

/**
 * What a form's notification email says about a file that was sent with it (spec 081, US3;
 * Phase G).
 *
 * The email printed the number the file is stored under: "Cv: 224". The owner's decision of
 * 2026-10-11, asked whether the file should be attached: no. The email names the file and links
 * to the submission in the inbox, where whoever may read the submission opens it. A file sent to
 * a mailbox cannot be taken back.
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Forms\Form;
use Corex\Forms\FormRegistry;
use Corex\Forms\Listeners\SendEmailListener;
use Corex\Forms\Listeners\StoreSubmissionListener;
use Corex\Forms\Submission\FormSubmittedEvent;
use Corex\Forms\Submission\NotificationDispatcher;
use Corex\Forms\Submission\SubmissionStore;
use Corex\Forms\Submission\UploadedFileNames;
use Corex\Mail\SubmissionAddress;

const INBOX_OF_41 = 'https://example.test/wp-admin/admin.php?page=corex-submissions&corex_submission=41';

beforeEach(function () {
    Functions\stubTranslationFunctions();
    Functions\stubEscapeFunctions();
    Functions\when('is_email')->alias(static fn (string $v): string|false => str_contains($v, '@') ? $v : false);
    Functions\when('sanitize_email')->returnArg();
    Functions\when('get_bloginfo')->justReturn('CoreX Test');
    Functions\when('get_option')->justReturn('admin@example.com');
    Functions\when('wp_json_encode')->alias(static fn (mixed $d): string => (string) json_encode($d));
    // The site holds one file, numbered 224.
    Functions\when('get_attached_file')->alias(
        static fn (int $id): string|false => $id === 224 ? '/srv/uploads/corex-private/salma-cv.pdf' : false,
    );
});

function applicationForms(): FormRegistry
{
    $forms = new FormRegistry();
    $forms->register(new class extends Form {
        public string $slug = 'apply';

        protected array $fields = [
            'name' => ['type' => 'text'],
            'years' => ['type' => 'number'],
            'cv' => ['type' => 'file'],
        ];
    });

    return $forms;
}

function inboxAddresses(): SubmissionAddress
{
    return new class implements SubmissionAddress {
        public function of(int $submissionId): string
        {
            return 'https://example.test/wp-admin/admin.php?page=corex-submissions&corex_submission=' . $submissionId;
        }
    };
}

/**
 * The request a notification makes of the mailer, for one application.
 *
 * @param array<string,mixed> $values
 */
function notificationFor(array $values, ?int $storedAs = 41, ?SubmissionAddress $addresses = null): Corex\Mail\MailRequest
{
    $event = new FormSubmittedEvent('apply', $values);
    if ($storedAs !== null) {
        $event->storedAs($storedAs);
    }
    $mailer = requestKeepingMailer();

    (new SendEmailListener(
        new NotificationDispatcher(null, $mailer),
        emailListenerConfig(),
        new UploadedFileNames(applicationForms()),
        $addresses ?? inboxAddresses(),
    ))->dispatch($event);

    return $mailer->request;
}

it('names the file a form was sent with, in place of the number it is stored under', function () {
    $request = notificationFor(['name' => 'Salma', 'years' => '224', 'cv' => 224]);

    expect($request->body)->toContain('salma-cv.pdf')
        // The years of experience are an answer, and stay one.
        ->and($request->context['submission'])->toBe(['name' => 'Salma', 'years' => '224', 'cv' => 'salma-cv.pdf'])
        ->and(substr_count($request->body, '224'))->toBe(1);
});

it('links to the submission in the inbox, where the file is opened', function () {
    $request = notificationFor(['name' => 'Salma', 'cv' => 224]);

    expect($request->body)->toContain('href="' . INBOX_OF_41 . '"')
        ->and($request->context['submission_url'])->toBe(INBOX_OF_41)
        // Nothing is attached: a file sent to a mailbox cannot be taken back.
        ->and($request->attachments)->toBe([]);
});

it('says nothing of a file in the email of a submission that has none', function () {
    $request = notificationFor(['name' => 'Salma']);

    expect($request->body)->not->toContain('href=')
        ->and($request->context['submission'])->toBe(['name' => 'Salma']);
});

it('names the file and offers no link when the submission was not stored', function () {
    $request = notificationFor(['name' => 'Salma', 'cv' => 224], storedAs: null);

    expect($request->body)->toContain('salma-cv.pdf')
        ->and($request->body)->not->toContain('href=')
        ->and($request->context['submission_url'])->toBe('');
});

it('leaves the answer as it is stored when the file cannot be found', function () {
    $request = notificationFor(['name' => 'Salma', 'cv' => 999]);

    expect($request->context['submission']['cv'])->toBe(999);
});

it('is told by whatever stores a submission which submission it became', function () {
    $store = new class implements SubmissionStore {
        public function save(string $slug, array $values): int
        {
            return 41;
        }
    };
    $event = new FormSubmittedEvent('apply', ['name' => 'Salma']);

    expect($event->submissionId())->toBeNull();

    (new StoreSubmissionListener($store))($event);

    expect($event->submissionId())->toBe(41);
});
