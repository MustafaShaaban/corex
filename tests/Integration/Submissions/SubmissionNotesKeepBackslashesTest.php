<?php

/**
 * What the team writes on a submission is read as it was written.
 *
 * WordPress takes one level of slashes off a meta value on its way in. A submission's notes,
 * its history and the team it is given to were handed over as they were, so a note saying
 * `C:\offers\new` was read as `C:offersnew`. The notes and the history are each one value,
 * written again whole when one is added: `\\server\share` in the first note was `\servershare`
 * once stored and `servershare` after the next note (measured 2026-10-13, DECISIONS #331).
 *
 * On real WordPress, through the workflow service the inbox's routes call, and read as the
 * inbox reads a submission.
 *
 * @package Corex\Tests\Integration\Submissions
 */

declare(strict_types=1);

use Corex\Config\Data\WpSubmissionsReader;
use Corex\Config\Submissions\SubmissionAccessScope;
use Corex\Config\Submissions\SubmissionAssignment;
use Corex\Config\Submissions\SubmissionTimelineRepository;
use Corex\Config\Submissions\SubmissionWorkflowService;
use Corex\Tests\Support\BackslashedText;
use Corex\Tests\Support\CreatedPosts;

beforeEach(function () {
    if (! post_type_exists('corex_submission')) {
        register_post_type('corex_submission', ['public' => false]);
    }
    $this->submissions = CreatedPosts::watch('corex_submission');
    $this->reader = new WpSubmissionsReader();
    $this->timeline = new SubmissionTimelineRepository();
    $this->workflow = new SubmissionWorkflowService($this->reader, $this->timeline);
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    $this->scope = new SubmissionAccessScope((int) ($administrators[0] ?? 0), true, canViewRestrictedNotes: true);
    $this->submissionId = wp_insert_post([
        'post_type' => 'corex_submission',
        'post_status' => 'private',
        'post_title' => 'A submission the team writes on',
        'meta_input' => [
            'corex_form_slug' => 'contact',
            'corex_submission_status' => 'new',
            'corex_owner_type' => 'none',
            'corex_submission_updated_at' => '2026-10-13T09:00:00+00:00',
        ],
    ]);
});

afterEach(function () {
    $this->submissions->delete();
});

it('shows a note in the inbox as it was written', function (string $text) {
    $note = $this->workflow->addNote($this->scope, $this->submissionId, $text, 'corex-team');

    expect($note['body'])->toBe($text)
        ->and($this->reader->findWorkflow($this->submissionId)['notes'][0]['body'])->toBe($text);
})->with(BackslashedText::cases());

it('keeps a note as it was written when notes are added after it', function () {
    $this->workflow->addNote($this->scope, $this->submissionId, BackslashedText::ALL, 'corex-team');
    $this->workflow->addNote($this->scope, $this->submissionId, 'A second note.', 'corex-team');
    $this->workflow->addNote($this->scope, $this->submissionId, 'A third note.', 'restricted');

    $notes = $this->reader->findWorkflow($this->submissionId)['notes'];

    expect(array_column($notes, 'body'))->toBe([BackslashedText::ALL, 'A second note.', 'A third note.'])
        // A note's number and its writer are read where they are whole numbers.
        ->and(array_column($notes, 'id'))->toBe([1, 2, 3])
        ->and($notes[0]['author_id'])->toBe($this->scope->actorId);
});

it('keeps an event of the history as it was recorded', function (string $text) {
    $event = $this->timeline->append($this->submissionId, 'email', 'failure', ['reason' => $text]);

    expect($event['summary'])->toBe(['reason' => $text])
        ->and($this->timeline->forSubmission($this->submissionId, true)[0]['summary'])->toBe(['reason' => $text])
        ->and($this->reader->findWorkflow($this->submissionId)['timeline'][0]['summary'])->toBe(['reason' => $text]);
})->with(BackslashedText::cases());

it('keeps an event as it was recorded through the events after it', function () {
    $first = ['reason' => BackslashedText::ALL, 'attempt_id' => null, 'actor_id' => 7, 'retryable' => false];

    $this->timeline->append($this->submissionId, 'email', 'failure', $first);
    $this->timeline->append($this->submissionId, 'read', 'success', ['actor_id' => 7]);
    // The retention clean-up adds to the same history from the reader, and writes it whole too.
    $this->reader->archiveForRetention($this->submissionId);
    $events = $this->timeline->forSubmission($this->submissionId, true);

    expect($events[0]['summary'])->toBe($first)
        ->and(array_column($events, 'stage'))->toBe(['email', 'read', 'retention'])
        ->and(array_column($events, 'id'))->toBe([1, 2, 3]);
});

it('gives a submission to the team it was given to, under the team\'s own key', function () {
    // A team's key is the site's to choose, and a site that names its teams after its
    // directory's groups has keys such as this one.
    $given = $this->workflow->assign(
        $this->scope,
        $this->submissionId,
        new SubmissionAssignment('team', 'CORP\\sales'),
        '2026-10-13T09:00:00+00:00',
    );

    $history = $this->timeline->forSubmission($this->submissionId, true);

    expect($given['owner_key'])->toBe('CORP\\sales')
        ->and($this->reader->findWorkflow($this->submissionId)['owner_key'])->toBe('CORP\\sales')
        ->and($history[0]['summary']['to'])->toBe('team:CORP\\sales');
});
