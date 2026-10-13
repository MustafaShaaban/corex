<?php

/**
 * A reply's subject leaves the site as it was typed.
 *
 * A subject is an email header: plain text, with no markup in it to clean. The reply routes
 * cleaned it as the text of an HTML form, so "Price <500 EGP" reached the customer as
 * "Price &lt;500 EGP" and "Sam <sam@example.com> asked" as "Sam asked". The draft kept it as
 * typed; sending the draft did not.
 *
 * Through the routes and on real WordPress, on a site answered as a development one: the email
 * is read from where such a site keeps it, and reaches nobody.
 *
 * @package Corex\Tests\Integration\Submissions
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Submissions\SubmissionReplyStore;
use Corex\Config\Submissions\SubmissionsController;
use Corex\Email\Capture\CapturedEmailRepository;
use Corex\Email\Delivery\EmailAttemptRepository;
use Corex\Tests\Support\MailDelivery;

function subjectRequest(string $method, int $submission, string $action = '', array $payload = []): WP_REST_Request
{
    $request = new WP_REST_Request($method, '/corex/v1/submissions/' . $submission . ($action === '' ? '' : '/' . $action));
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    $request->set_url_params(['id' => (string) $submission]);
    $request->set_body_params($payload);

    return $request;
}

/** The emails kept for one address, newest first. */
function subjectEmailsFor(string $recipient): array
{
    return array_values(array_filter(
        Boot::app()->container()->make(CapturedEmailRepository::class)->latest(50),
        static fn ($capture): bool => $capture->to === [$recipient],
    ));
}

beforeEach(function () {
    $this->mailSettings = MailDelivery::answerAs(MailDelivery::CAPTURE);
    if (! post_type_exists('corex_submission')) {
        register_post_type('corex_submission', ['public' => false]);
    }
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));
    $this->subjectController = Boot::app()->container()->make(SubmissionsController::class);
    $this->subjectRecipient = 'salma.' . strtolower(wp_generate_password(10, false)) . '@example.com';
    $this->subjectSubmission = wp_insert_post([
        'post_type' => 'corex_submission',
        'post_status' => 'private',
        'post_title' => 'A submission answered with a subject of its own',
        'meta_input' => [
            'corex_form_slug' => 'contact',
            'corex_submission_status' => 'new',
            'corex_owner_type' => 'none',
            'corex_submitter_name' => 'Salma',
            'corex_submitter_email' => $this->subjectRecipient,
        ],
    ]);
});

afterEach(function () {
    MailDelivery::stop($this->mailSettings);
    // Deleting the submission takes the email kept for it with it (spec 105).
    wp_delete_post($this->subjectSubmission, true);
});

dataset('subjects as typed', [
    'a less-than sign' => ['Price <500 EGP'],
    'an address in angle brackets' => ['Sam <sam@example.com> asked'],
    'a percent sign before two hex digits' => ['Save 50%de today'],
    'an ampersand and both quotes' => ['Tom & Jerry\'s "offer"'],
    'an entity somebody meant to type' => ['Use &amp; not &'],
    'Arabic' => ['بخصوص طلبك: السعر أقل من <500> جنيه & "خصم"'],
]);

it('sends a reply under the subject that was typed, and keeps it and lists it as that', function (string $typed) {
    $sent = $this->subjectController->reply(subjectRequest('POST', $this->subjectSubmission, 'reply', [
        'subject' => $typed,
        'body' => '<p>Hello Salma</p>',
    ]))->get_data();
    $submission = $this->subjectController->show(subjectRequest('GET', $this->subjectSubmission))->get_data()['data']['submission'];
    $emails = subjectEmailsFor($this->subjectRecipient);
    $attempt = Boot::app()->container()->make(EmailAttemptRepository::class)
        ->findByAttemptId((string) ($sent['data']['result']['attempt_id'] ?? ''));

    expect($sent['data']['result']['state'] ?? $sent)->toBe('captured')
        ->and($emails)->toHaveCount(1)
        ->and($emails[0]->subject)->toBe($typed)
        ->and($submission['replies'][0]['subject'])->toBe($typed)
        // What Email Studio lists the email under.
        ->and($attempt?->subject)->toBe($typed)
        // The one place the subject is written into markup: as text, never as a tag.
        ->and($emails[0]->body)->toContain('<title>' . htmlspecialchars($typed, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</title>');
})->with('subjects as typed');

it('previews a reply under the subject that was typed, written into the email as text', function (string $typed) {
    $answer = $this->subjectController->previewReply(subjectRequest('POST', $this->subjectSubmission, 'reply/preview', [
        'subject' => $typed,
        'body' => '<p>Hello Salma</p>',
    ]))->get_data();

    expect($answer['data']['preview']['subject'] ?? $answer)->toBe($typed)
        ->and($answer['data']['preview']['html'])->toContain('<title>' . htmlspecialchars($typed, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</title>')
        ->and(subjectEmailsFor($this->subjectRecipient))->toBe([]);
})->with('subjects as typed');

it('sends a draft under the subject it gave back', function () {
    $typed = 'Price <500 EGP & "more"';
    $draft = $this->subjectController->keepReplyDraft(subjectRequest('POST', $this->subjectSubmission, 'reply/draft', [
        'subject' => $typed,
        'body' => '<p>Hello Salma</p>',
    ]))->get_data()['data']['draft'];

    $this->subjectController->reply(subjectRequest('POST', $this->subjectSubmission, 'reply', [
        'subject' => $draft['subject'],
        'body' => $draft['body'],
    ]));

    expect($draft['subject'])->toBe($typed)
        ->and(subjectEmailsFor($this->subjectRecipient)[0]->subject ?? null)->toBe($typed);
});

it('keeps a path in a subject and in a body: in the preview, with the submission and in Email Studio', function () {
    $typed = 'C:\\offers\\new';
    $payload = ['subject' => $typed, 'body' => '<p>The file is at C:\\offers\\one.</p>'];
    $preview = $this->subjectController->previewReply(subjectRequest('POST', $this->subjectSubmission, 'reply/preview', $payload))->get_data();
    $sent = $this->subjectController->reply(subjectRequest('POST', $this->subjectSubmission, 'reply', $payload))->get_data();
    $submission = $this->subjectController->show(subjectRequest('GET', $this->subjectSubmission))->get_data()['data']['submission'];
    $email = subjectEmailsFor($this->subjectRecipient)[0] ?? null;
    $attempt = Boot::app()->container()->make(EmailAttemptRepository::class)
        ->findByAttemptId((string) ($sent['data']['result']['attempt_id'] ?? ''));

    expect($preview['data']['preview']['subject'] ?? $preview)->toBe($typed)
        ->and($submission['replies'][0]['subject'] ?? null)->toBe($typed)
        // Email Studio's own copy: the email it kept, and the attempt it lists the email under.
        ->and($email?->subject)->toBe($typed)
        ->and($email->body)->toContain('<title>C:\\offers\\new</title>')->toContain('The file is at C:\\offers\\one.')
        ->and($email->plainText)->toContain('The file is at C:\\offers\\one.')
        ->and($attempt?->subject)->toBe($typed);
});

it('makes one line of a subject before it is a header, for a reply, a preview and a draft alike', function (string $typed, string $line) {
    $payload = ['subject' => $typed, 'body' => '<p>Hello Salma</p>'];
    $preview = $this->subjectController->previewReply(subjectRequest('POST', $this->subjectSubmission, 'reply/preview', $payload))->get_data();
    $draft = $this->subjectController->keepReplyDraft(subjectRequest('POST', $this->subjectSubmission, 'reply/draft', $payload))->get_data();
    $sent = $this->subjectController->reply(subjectRequest('POST', $this->subjectSubmission, 'reply', $payload))->get_data();
    $email = subjectEmailsFor($this->subjectRecipient)[0] ?? null;

    expect($sent['data']['result']['state'] ?? $sent)->toBe('captured')
        ->and($email?->subject)->toBe($line)
        ->and($preview['data']['preview']['subject'] ?? $preview)->toBe($line)
        ->and($draft['data']['draft']['subject'] ?? $draft)->toBe($line)
        // What followed a line break is words in the subject, not a header of its own.
        ->and($email->bcc)->toBe([])
        ->and($email->cc)->toBe([])
        ->and(array_keys(array_change_key_case($email->headers)))->not->toContain('bcc');
})->with([
    'a line break and a header after it' => ["Hello\r\nBcc: someone@example.com", 'Hello Bcc: someone@example.com'],
    'a pasted second line, indented' => ["Your order  \n\t  is ready", 'Your order is ready'],
    'a tab and a null byte' => ["One\tTwo\0Three", 'One Two Three'],
    'spaces around it' => ['   Re: your message  ', 'Re: your message'],
    'spaces inside it, as typed' => ['Re:  two  spaces', 'Re:  two  spaces'],
    'longer than a subject is' => [str_repeat('س', 400), str_repeat('س', 300)],
]);

it('refuses a reply whose subject is not text, and keeps nothing', function (mixed $typed) {
    $request = subjectRequest('POST', $this->subjectSubmission, 'reply');
    $request->set_body_params(['subject' => $typed, 'body' => '<p>Hello Salma</p>']);
    $refused = $this->subjectController->reply($request);

    expect($refused->get_status())->toBe(422)
        ->and($refused->get_data()['message'])->toContain('subject')
        ->and(subjectEmailsFor($this->subjectRecipient))->toBe([])
        ->and(Boot::app()->container()->make(SubmissionReplyStore::class)->forSubmission($this->subjectSubmission))->toBe([]);
})->with([
    'bytes that are not UTF-8' => ["Price \xC3\x28 500"],
    'a list' => [['Price', '500']],
    'only a line break' => ["\r\n"],
]);
