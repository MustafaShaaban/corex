<?php

/**
 * A form's notification email names the file it was sent with and links to the submission, on
 * real WordPress (spec 081, US3; Phase G).
 *
 * What only WordPress can show: that the submission the email points at is the one that was
 * stored, that the address is the one the inbox opens a submission from, and that the name in
 * the email is the name of the file on disk.
 *
 * The form is given to the listener directly and not registered with the site: in a run of the
 * whole suite the registry in the container is not the one the forms service was built with.
 *
 * @package Corex\Tests\Integration\Forms
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Forms\Form;
use Corex\Forms\FormRegistry;
use Corex\Forms\Listeners\SendEmailListener;
use Corex\Forms\Listeners\StoreSubmissionListener;
use Corex\Forms\Submission\FormSubmittedEvent;
use Corex\Forms\Submission\NotificationDispatcher;
use Corex\Forms\Submission\UploadedFileNames;
use Corex\Mail\AttemptingMailer;
use Corex\Mail\MailRequest;
use Corex\Mail\MailResult;
use Corex\Mail\SubmissionAddress;
use Corex\Security\Upload\ProtectedUploads;
use Corex\Support\Config\ConfigInterface;
use Corex\Tests\Support\CreatedPosts;

const NOTIFIED_FORM = 'corex-notified-file-probe';

beforeEach(function () {
    $this->stored = CreatedPosts::watch('corex_submission', 'attachment');
    wp_set_current_user((int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0]);

    // Keeps the request and sends nothing: a development install may deliver for real.
    $this->mailer = new class implements AttemptingMailer {
        public ?MailRequest $request = null;

        public function send(MailRequest $request): void
        {
            $this->request = $request;
        }

        public function attempt(MailRequest $request): MailResult
        {
            $this->request = $request;

            return new MailResult(
                attemptId: 'f6773ddc-2d63-40cc-b408-35c0a81c084b',
                requestId: $request->requestId,
                state: MailResult::STATE_CAPTURED,
                provider: 'test',
                message: 'Kept by the test.',
                occurredAt: new DateTimeImmutable('now'),
                retryable: false,
            );
        }
    };
});

afterEach(function () {
    foreach ($this->stored->ids() as $id) {
        if (get_post_type($id) === 'attachment') {
            wp_delete_attachment($id, true);
        }
    }
    $this->stored->delete();
    wp_set_current_user(0);
});

it('names the file and links to the submission that was stored, at the address the inbox opens it from', function () {
    $container = Boot::app()->container();
    $forms     = new FormRegistry();
    $forms->register(new class extends Form {
        public string $slug = NOTIFIED_FORM;

        protected array $fields = ['name' => ['type' => 'text'], 'cv' => ['type' => 'file']];
    });

    $path = ProtectedUploads::ensure() . '/corex-notified-' . wp_generate_password(8, false) . '.txt';
    file_put_contents($path, 'A curriculum vitae.');
    $cv = (int) wp_insert_attachment(['post_title' => 'CV', 'post_status' => 'private', 'post_mime_type' => 'text/plain'], $path);

    // As a form's listeners run: the one that stores, then the one that writes the email.
    $event = new FormSubmittedEvent(NOTIFIED_FORM, ['name' => 'Salma', 'cv' => $cv]);
    ($container->make(StoreSubmissionListener::class))($event);
    (new SendEmailListener(
        new NotificationDispatcher(null, $this->mailer),
        $container->make(ConfigInterface::class),
        new UploadedFileNames($forms),
        $container->make(SubmissionAddress::class),
    ))($event);

    $submissionId = $event->submissionId();
    parse_str((string) wp_parse_url($this->mailer->request->context['submission_url'], PHP_URL_QUERY), $address);

    expect(get_post_type($submissionId))->toBe('corex_submission')
        ->and((int) get_post_meta($submissionId, 'corex_field_cv', true))->toBe($cv)
        ->and($address)->toBe(['page' => 'corex-submissions', 'corex_submission' => (string) $submissionId])
        ->and($this->mailer->request->body)->toContain(basename($path))
        ->and($this->mailer->request->body)->toContain('corex_submission=' . $submissionId)
        ->and($this->mailer->request->attachments)->toBe([]);
});
