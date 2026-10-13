<?php

/**
 * What a visitor wrote in a form is read in the inbox as they wrote it.
 *
 * WordPress takes one level of slashes off a meta value on its way in, and a submission's
 * answers were handed over as they were. A visitor who wrote `C:\offers\new` was read in the
 * inbox as having written `C:offersnew`; `\\server\share` as `\servershare`; a backslash at the
 * end of a message was gone (measured 2026-10-13, DECISIONS #331).
 *
 * On real WordPress: through the form's route for a form defined in code, through the
 * repository for a flow's submission, and read the way the inbox and the Data screen read. The
 * site is answered as a development one, so the notification is kept and reaches nobody.
 *
 * @package Corex\Tests\Integration\Forms
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Data\WpSubmissionsReader;
use Corex\Config\Submissions\SubmissionAccessScope;
use Corex\Config\Submissions\SubmissionInboxQuery;
use Corex\Email\Studio\WpEmailStudioStore;
use Corex\Forms\Submission\FlowSubmissionRecord;
use Corex\Forms\Submission\SubmissionRepository;
use Corex\Forms\Submission\SubmitController;
use Corex\Tests\Support\BackslashedText;
use Corex\Tests\Support\CreatedPosts;
use Corex\Tests\Support\MailDelivery;
use Corex\Tests\Support\NotificationRows;
use Corex\Tests\Support\WatchedTransients;

/** A flow nobody has: these submissions are found by it, and by nothing else on the install. */
const KEPT_FLOW_ID = 990131;

/** @param array<string,mixed> $values @param array<string,mixed> $metadata */
function keptFlowSubmission(array $values, array $metadata = [], bool $isTest = false): int
{
    return Boot::app()->container()->make(SubmissionRepository::class)->storeFlow(new FlowSubmissionRecord(
        flowSlug: 'kept-flow',
        flowId: KEPT_FLOW_ID,
        flowVersionId: 7,
        flowLabel: 'Offers \\ returns',
        isTest: $isTest,
        values: $values,
        metadata: $metadata,
    ));
}

beforeEach(function () {
    $this->mailSettings = MailDelivery::answerAs(MailDelivery::CAPTURE);
    // A form defined in code notifies through `wp_mail()` whatever the site is answered as.
    $this->keepMailHere = static fn (): bool => true;
    add_filter('pre_wp_mail', $this->keepMailHere);
    Boot::app()->container()->make(WpEmailStudioStore::class)->registerPostType();
    $this->posts = CreatedPosts::watch('corex_submission', 'corex_email_log', WpEmailStudioStore::POST_TYPE);
    $this->transients = WatchedTransients::watch('corex_throttle_');
    // The contact form tells whoever manages submissions, under a key a real submission uses too.
    $this->notifications = NotificationRows::snapshotPrefixed('submission.new:contact');
    $this->reader = new WpSubmissionsReader();
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    $this->everything = new SubmissionAccessScope((int) ($administrators[0] ?? 0), true, []);
});

afterEach(function () {
    $this->posts->delete();
    $this->transients->restore();
    NotificationRows::restorePrefixed('submission.new:contact', $this->notifications);
    remove_filter('pre_wp_mail', $this->keepMailHere);
    MailDelivery::stop($this->mailSettings);
});

it('shows a contact form message in the inbox as the visitor wrote it', function (string $text) {
    $request = new WP_REST_Request('POST', '/corex/v1/forms/contact');
    $request->set_url_params(['slug' => 'contact']);
    $request->set_body_params(['name' => 'Salma', 'email' => 'salma@example.com', 'message' => $text]);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));

    $response = Boot::app()->container()->make(SubmitController::class)->submit($request);
    $stored = array_values(array_filter(
        $this->posts->ids(),
        static fn (int $id): bool => get_post_type($id) === 'corex_submission',
    ));

    expect($response->get_status())->toBe(200)
        ->and($stored)->toHaveCount(1)
        // The inbox's record, and the row of the Data screen and of an export.
        ->and($this->reader->findWorkflow($stored[0])['values']['message'])->toBe($text)
        ->and($this->reader->find($stored[0])['fields']['message'])->toBe($text);
})->with(BackslashedText::cases());

it('shows each answer of a flow submission in the inbox as it was written', function (string $text) {
    $values = ['name' => 'Salma', 'message' => $text, 'choices' => [$text, 'second']];

    $record = $this->reader->findWorkflow(keptFlowSubmission($values));

    expect($record['values'])->toBe($values)
        ->and($this->reader->find($record['id'])['fields']['message'])->toBe($text)
        ->and($this->reader->find($record['id'])['fields']['choices'])->toBe([$text, 'second']);
})->with(BackslashedText::cases());

it('shows the sender and the form of a flow submission as they were written', function () {
    // A person asked for their name gives the name they sign in with.
    $record = $this->reader->findWorkflow(keptFlowSubmission(['name' => 'CORP\\salma.h', 'email' => 'salma@example.com']));

    expect($record['submitter_name'])->toBe('CORP\\salma.h')
        ->and($record['flow'])->toBe('Offers \\ returns');
});

it('finds a flow submission by the sender its row shows', function () {
    $id = keptFlowSubmission(['name' => 'CORP\\salma.h', 'email' => 'salma@example.com']);

    $found = $this->reader->queryInbox(
        SubmissionInboxQuery::from(['flow' => KEPT_FLOW_ID, 'search' => 'CORP\\salma']),
        $this->everything,
    );

    expect(array_column($found['items'], 'id'))->toBe([$id]);
});

it('keeps what came with a flow submission as it was sent', function () {
    $metadata = [
        'hidden' => ['page_title' => BackslashedText::ALL],
        'utm' => ['utm_campaign' => 'spring\\offers'],
        'consent' => ['consent' => ['statement' => 'I agree \\ accept', 'granted' => true]],
    ];

    $record = $this->reader->findWorkflow(keptFlowSubmission(['name' => 'Salma'], $metadata));

    expect($record['hidden_metadata'])->toBe($metadata['hidden'])
        ->and($record['utm'])->toBe($metadata['utm'])
        ->and($record['consent_snapshot'])->toBe($metadata['consent']);
});

it('reads what is not text in a flow submission as the kind of value it was', function () {
    $values = ['name' => 'Salma', 'guests' => 3, 'newsletter' => true, 'terms' => false, 'company' => null];

    $live = $this->reader->findWorkflow(keptFlowSubmission($values));
    $test = $this->reader->findWorkflow(keptFlowSubmission($values, [], true));

    expect($live['values'])->toBe($values)
        ->and($live['is_test'])->toBeFalse()
        ->and($test['is_test'])->toBeTrue()
        ->and($live['flow_id'])->toBe(KEPT_FLOW_ID)
        ->and($live['flow_version_id'])->toBe(7)
        ->and($live['status'])->toBe('new');
});

it('keeps an event of the pipeline as it was recorded, through the events after it', function () {
    $submissions = Boot::app()->container()->make(SubmissionRepository::class);
    $id = keptFlowSubmission(['name' => 'Salma']);

    // The whole history is written again each time an event is added to it.
    $submissions->appendTimeline($id, 'notification', 'failure', ['reason' => BackslashedText::ALL, 'attempt_id' => null]);
    $submissions->appendTimeline($id, 'submitted', 'success', ['is_test' => false, 'flow_version_id' => 7]);
    $submissions->appendTimeline($id, 'submitted', 'success', ['kind' => 'flow.submitted']);
    $timeline = $this->reader->findWorkflow($id)['timeline'];

    expect($timeline[0]['summary'])->toBe(['reason' => BackslashedText::ALL, 'attempt_id' => null])
        ->and($timeline[1]['summary'])->toBe(['is_test' => false, 'flow_version_id' => 7])
        ->and(array_column($timeline, 'id'))->toBe([1, 2, 3]);
});
