<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Listeners;

defined('ABSPATH') || exit;

use Corex\Forms\Submission\NotificationDelivery;
use Corex\Forms\Submission\NotificationDispatcher;
use Corex\Forms\Submission\FormSubmittedEvent;
use Corex\Forms\Submission\UploadedFileNames;
use Corex\Mail\MailRequest;
use Corex\Mail\SubmissionAddress;
use Corex\Support\Config\ConfigInterface;

/**
 * Emails a legacy (registered, non-flow) submission to the configured recipient
 * (`forms.email.recipient`, falling back to the site admin).
 *
 * The transport ladder — CoreX Mail's router, a bound mailer, then the wp_mail() floor — lives in
 * the shared {@see NotificationDispatcher}, so this listener no longer carries its own copy (and no
 * longer mislabels wp_mail acceptance as "sent"). No hard dependency on CoreX Mail either way
 * (Principle IX).
 *
 * A file sent with the form is named, and is not attached: the email links to the submission,
 * where whoever may read it opens the file (spec 081, US3; the owner's decision of 2026-10-11).
 * A file sent to a mailbox cannot be taken back.
 */
final class SendEmailListener
{
    /**
     * The one form the `contact-notification` template was written for. It prints a name, an email
     * and a message, and a mailer that has templates renders it in place of the body below — so
     * naming it for any other form sent that form's notification without its fields.
     */
    private const CONTACT_FORM = 'contact';

    private const CONTACT_TEMPLATE = 'contact-notification';

    /**
     * @param UploadedFileNames|null $files     Asked what each file sent with the form is called.
     * @param SubmissionAddress|null $addresses Asked where the stored submission is read. Null on
     *                                          a site with no screen to read one on.
     */
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly ConfigInterface $config,
        private readonly ?UploadedFileNames $files = null,
        private readonly ?SubmissionAddress $addresses = null,
    ) {
    }

    public function __invoke(FormSubmittedEvent $event): void
    {
        $event->notifiedAs($this->dispatch($event));
    }

    public function dispatch(FormSubmittedEvent $event): NotificationDelivery
    {
        $files   = $this->files?->in($event->formSlug, $event->values) ?? [];
        $answers = array_replace($event->values, $files);
        $address = $this->addressOf($event);
        $context = [
            'submission'     => $answers,
            'submission_url' => $address,
            'form'           => ['slug' => $event->formSlug],
            'site'           => ['name' => get_bloginfo('name')],
        ];

        $recipient = (string) $this->config->get('forms.email.recipient', '');
        if ($recipient === '') {
            $recipient = (string) get_option('admin_email');
        }

        $request = new MailRequest(
            to: $recipient !== '' ? [$recipient] : [],
            templateName: $event->formSlug === self::CONTACT_FORM ? self::CONTACT_TEMPLATE : null,
            context: $context,
            subject: sprintf(
                /* translators: %s: form slug */
                __('New "%s" form submission', 'corex'),
                $event->formSlug,
            ),
            // HTML, because every CoreX transport sends `Content-Type: text/html` — the plain-text
            // form arrived as one collapsed run (issue #138, item 4).
            body: NotificationDispatcher::htmlBody($answers) . self::whereFilesAreOpened(count($files), $address),
            // So the recipient can just hit Reply. The submitter's address was only readable inside
            // the body, which makes answering a contact form a copy-and-paste job.
            replyTo: self::submitterAddress($event->values),
        );

        return $this->dispatcher->dispatch('forms.' . $event->formSlug . '.submitted', $context, $request);
    }

    /**
     * Where the stored submission is read, or '' when it was not stored or the site has no
     * screen to read it on.
     */
    private function addressOf(FormSubmittedEvent $event): string
    {
        $submissionId = $event->submissionId();

        return $submissionId !== null && $this->addresses !== null ? $this->addresses->of($submissionId) : '';
    }

    /**
     * The line under the answers that says where a file named among them is opened. Nothing
     * for a submission with no file, or with nowhere to point.
     */
    private static function whereFilesAreOpened(int $files, string $address): string
    {
        if ($files === 0 || $address === '') {
            return '';
        }

        return sprintf(
            '<p>%s <a href="%s">%s</a></p>',
            esc_html(_n(
                'The file is opened from the submission, after signing in to the site.',
                'The files are opened from the submission, after signing in to the site.',
                $files,
                'corex',
            )),
            esc_url($address),
            esc_html__('Open the submission', 'corex'),
        );
    }

    /**
     * The submitter's address, if the submission carries one that is actually an address.
     *
     * Validated rather than trusted: the value reaches a mail header, and a header is exactly where
     * an unvalidated string should not go. Returns null when there is nothing usable, which leaves
     * the message with no Reply-To — the behaviour before this, and the right answer for a form
     * that never asked for an email address.
     *
     * @param array<string,mixed> $values
     */
    private static function submitterAddress(array $values): ?string
    {
        foreach (['email', 'e-mail', 'email_address', 'reply_to'] as $field) {
            $candidate = $values[$field] ?? null;

            if (is_string($candidate) && is_email($candidate) !== false) {
                return sanitize_email($candidate);
            }
        }

        return null;
    }
}
