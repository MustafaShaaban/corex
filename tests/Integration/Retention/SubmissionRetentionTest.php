<?php

/**
 * Which submissions a retention run selects, on real ./wp (DECISIONS #232).
 *
 * A run is handed at most `RetentionSettings::MAX_PRUNE` records. Anonymizing or archiving leaves a
 * submission `private`, so a selection that ignores what was already done hands the next run the
 * same records, and anything behind them is never reached. These tests pin the selection: a record
 * the action has nothing left to do for is not selected, not counted and not touched again.
 *
 * @package Corex\Tests\Integration\Retention
 */

declare(strict_types=1);

use Corex\Activity\ActivityService;
use Corex\Boot;
use Corex\Config\Data\WpSubmissionsReader;
use Corex\Config\Retention\RetentionSettings;
use Corex\Config\Retention\SubmissionRetention;
use Corex\Config\Submissions\SubmissionAccessScope;
use Corex\Config\Submissions\SubmissionTimelineStore;
use Corex\Config\Submissions\SubmissionTrashService;
use Corex\Config\Submissions\WpSubmissionTrashStore;
use Corex\Tests\Support\RecordingActivityRepository;
use Corex\Tests\Support\RecordingSubmissionEmailRecords;

/** Where `SubmissionRetention` keeps its window. Private there; named here to put it back. */
const RETENTION_SELECTION_OPTION = 'corex_retention_submissions_days';

function insertRetentionSubmission(int $daysOld, string $team = 'sales'): int
{
    $date = gmdate('Y-m-d H:i:s', strtotime('-' . $daysOld . ' days'));

    return (int) wp_insert_post([
        'post_type' => 'corex_submission',
        'post_status' => 'private',
        'post_title' => 'Retention submission',
        'post_date' => $date,
        'post_date_gmt' => $date,
        'meta_input' => [
            'corex_flow_id' => 90,
            'corex_form_slug' => 'contact',
            'corex_submission_status' => 'new',
            'corex_owner_type' => 'team',
            'corex_owner_key' => $team,
            'corex_is_test' => 0,
            'corex_submitter_name' => 'Dana Retention',
            'corex_submitter_email' => 'dana.retention@example.com',
            'corex_values_json' => ['name' => 'Dana Retention', 'email' => 'dana.retention@example.com'],
        ],
    ]);
}

/** @return list<string> the states retention recorded on the submission's timeline, in order */
function retentionTimeline(int $submissionId): array
{
    $timeline = get_post_meta($submissionId, 'corex_submission_timeline', true);
    $states = [];
    foreach (is_array($timeline) ? $timeline : [] as $event) {
        if (($event['stage'] ?? '') === 'retention') {
            $states[] = (string) ($event['summary']['state'] ?? '');
        }
    }

    return $states;
}

beforeEach(function () {
    if (! post_type_exists('corex_submission')) {
        register_post_type('corex_submission', ['public' => false]);
    }

    // Retention acts on every submission older than its window, and this is a developer's real
    // install. Every retention query in this file is narrowed to the submissions the test itself
    // inserted — to none at all until it has inserted one — and each test asserts the preview
    // count before it prunes, so a narrowing that stopped applying fails there, before anything on
    // the install is touched.
    $this->submissionIds = [];
    $this->scopeRetentionToThisTest = function (WP_Query $query): void {
        if ($query->get('post_type') === 'corex_submission') {
            $query->set('post__in', $this->submissionIds === [] ? [0] : $this->submissionIds);
        }
    };
    add_action('pre_get_posts', $this->scopeRetentionToThisTest);

    $this->aged = function (int $daysOld, string $team = 'sales'): int {
        $id = insertRetentionSubmission($daysOld, $team);
        $this->submissionIds[] = $id;

        return $id;
    };

    $this->savedRetentionDays = get_option(RETENTION_SELECTION_OPTION, null);
    // The site's own readers and stores, and an activity stream that is this test's: a run is
    // recorded, and the record should not be left on a developer's install.
    $reader = new WpSubmissionsReader();
    $this->activity = new RecordingActivityRepository();
    $this->retention = new SubmissionRetention(
        new RetentionSettings(),
        $reader,
        new SubmissionTrashService(
            $reader,
            new WpSubmissionTrashStore($reader),
            Boot::app()->container()->make(SubmissionTimelineStore::class),
            new ActivityService($this->activity),
            new RecordingSubmissionEmailRecords(),
        ),
    );
    $this->retention->setDays(30);
    $this->administrator = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
    $this->everyone = new SubmissionAccessScope($this->administrator, true);
});

afterEach(function () {
    remove_action('pre_get_posts', $this->scopeRetentionToThisTest);

    foreach ($this->submissionIds as $submissionId) {
        wp_delete_post($submissionId, true);
    }

    // Exactly as it was, including "did not exist".
    if ($this->savedRetentionDays === null) {
        delete_option(RETENTION_SELECTION_OPTION);
    } else {
        update_option(RETENTION_SELECTION_OPTION, $this->savedRetentionDays, false);
    }
});

// The defect measured on 2026-10-04 (DECISIONS #231): a run was handed 500 already-anonymized
// submissions, anonymized them again, and never reached the live ones behind them.
it('does not anonymize a submission a second time', function () {
    $submissionId = ($this->aged)(120);
    expect($this->retention->preview()['count'])->toBe(1);

    expect($this->retention->prune($this->everyone, 'anonymize'))->toBe(1)
        ->and($this->retention->prune($this->everyone, 'anonymize'))->toBe(0)
        ->and(retentionTimeline($submissionId))->toBe(['anonymized']);
});

it('stops counting a submission as due once it is anonymized', function () {
    ($this->aged)(120);
    expect($this->retention->preview()['count'])->toBe(1);

    $this->retention->prune($this->everyone, 'anonymize');

    expect($this->retention->preview())->toMatchArray(['count' => 0, 'willPrune' => false]);
});

it('keeps an archived submission due, archives it once, and can still anonymize it', function () {
    $submissionId = ($this->aged)(120);
    expect($this->retention->preview()['count'])->toBe(1);

    expect($this->retention->prune($this->everyone, 'archive'))->toBe(1)
        ->and($this->retention->prune($this->everyone, 'archive'))->toBe(0)
        // Archiving keeps the personal data, so the record is still past its window.
        ->and($this->retention->preview()['count'])->toBe(1)
        ->and($this->retention->prune($this->everyone, 'anonymize'))->toBe(1)
        ->and(retentionTimeline($submissionId))->toBe(['archived', 'anonymized'])
        ->and(get_post_meta($submissionId, 'corex_submitter_email', true))->toBe('');
});

it('moves an archived submission to trash', function () {
    $submissionId = ($this->aged)(120);
    expect($this->retention->preview()['count'])->toBe(1);
    $this->retention->prune($this->everyone, 'archive');

    expect($this->retention->prune($this->everyone, 'trash'))->toBe(1)
        ->and(get_post_status($submissionId))->toBe('trash');
});

/**
 * The panel's "Move to trash" is the inbox's trash (spec 105, US4; FR-021). It called
 * `wp_trash_post()`: WordPress's clock on the submission, nothing in its history, nothing recorded,
 * and a deletion on the spot on a site that sets `EMPTY_TRASH_DAYS` to 0.
 */
it('moves what is due to the inbox’s trash as the retention run, with history and one record of the run', function () {
    $first = ($this->aged)(120);
    $second = ($this->aged)(90);
    expect($this->retention->preview()['count'])->toBe(2);

    $moved = $this->retention->prune($this->everyone, 'trash');

    $history = array_column((array) get_post_meta($first, 'corex_submission_timeline', true), 'summary', 'stage');

    expect($moved)->toBe(2)
        ->and(get_post_status($first))->toBe('trash')
        ->and(get_post_status($second))->toBe('trash')
        ->and(get_post_meta($first, 'corex_trashed_via', true))->toBe('retention')
        ->and((int) get_post_meta($first, 'corex_trashed_by', true))->toBe($this->administrator)
        // What WordPress's own daily clean-up selects a trashed post by.
        ->and(metadata_exists('post', $first, '_wp_trash_meta_time'))->toBeFalse()
        ->and($history['trash'])->toMatchArray(['actor_id' => $this->administrator, 'via' => 'retention'])
        ->and($this->activity->events)->toHaveCount(1)
        ->and($this->activity->events[0]->kind)->toBe('submission.trashed')
        ->and($this->activity->events[0]->context)->toMatchArray(['count' => 2, 'via' => 'retention'])
        // And nothing is due any more.
        ->and($this->retention->preview()['count'])->toBe(0);
});

/**
 * FR-023: what a person may see in the inbox is what they may move out of it.
 */
it('leaves a due submission that is not the person’s to see', function () {
    $theirs = ($this->aged)(120, 'sales');
    $notTheirs = ($this->aged)(120, 'legal');
    $salesTeam = new SubmissionAccessScope($this->administrator, false, ['sales']);

    expect($this->retention->prune($salesTeam, 'trash'))->toBe(1)
        ->and(get_post_status($theirs))->toBe('trash')
        ->and(get_post_status($notTheirs))->toBe('private')
        ->and($this->activity->events[0]->context)->toMatchArray(['count' => 1, 'submission_ids' => [$theirs]]);
});

it('neither archives nor trashes a submission that is already anonymized', function () {
    $submissionId = ($this->aged)(120);
    expect($this->retention->preview()['count'])->toBe(1);
    $this->retention->prune($this->everyone, 'anonymize');

    expect($this->retention->prune($this->everyone, 'archive'))->toBe(0)
        ->and($this->retention->prune($this->everyone, 'trash'))->toBe(0)
        ->and(get_post_meta($submissionId, 'corex_retention_state', true))->toBe('anonymized')
        ->and(get_post_status($submissionId))->toBe('private');
});

it('handles the oldest submissions first', function () {
    $middle = ($this->aged)(90);
    $oldest = ($this->aged)(120);
    $newest = ($this->aged)(60);
    expect($this->retention->preview()['count'])->toBe(3);

    // A run is bounded, so the order decides which records wait for the next one. The order is
    // read from the writes themselves: the retention state is the one meta each handled record gets.
    $handled = [];
    $recordHandled = function ($check, $objectId, $metaKey) use (&$handled) {
        if ($metaKey === 'corex_retention_state') {
            $handled[] = (int) $objectId;
        }

        return $check;
    };
    add_filter('update_post_metadata', $recordHandled, 10, 3);
    try {
        $this->retention->prune($this->everyone, 'anonymize');
    } finally {
        remove_filter('update_post_metadata', $recordHandled, 10);
    }

    expect($handled)->toBe([$oldest, $middle, $newest]);
});
