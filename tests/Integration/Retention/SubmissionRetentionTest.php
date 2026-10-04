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

use Corex\Boot;
use Corex\Config\Retention\SubmissionRetention;

/** Where `SubmissionRetention` keeps its window. Private there; named here to put it back. */
const RETENTION_SELECTION_OPTION = 'corex_retention_submissions_days';

function insertRetentionSubmission(int $daysOld): int
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

    $this->aged = function (int $daysOld): int {
        $id = insertRetentionSubmission($daysOld);
        $this->submissionIds[] = $id;

        return $id;
    };

    $this->savedRetentionDays = get_option(RETENTION_SELECTION_OPTION, null);
    $this->retention = Boot::app()->container()->make(SubmissionRetention::class);
    $this->retention->setDays(30);
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

    expect($this->retention->prune('anonymize'))->toBe(1)
        ->and($this->retention->prune('anonymize'))->toBe(0)
        ->and(retentionTimeline($submissionId))->toBe(['anonymized']);
});

it('stops counting a submission as due once it is anonymized', function () {
    ($this->aged)(120);
    expect($this->retention->preview()['count'])->toBe(1);

    $this->retention->prune('anonymize');

    expect($this->retention->preview())->toMatchArray(['count' => 0, 'willPrune' => false]);
});

it('keeps an archived submission due, archives it once, and can still anonymize it', function () {
    $submissionId = ($this->aged)(120);
    expect($this->retention->preview()['count'])->toBe(1);

    expect($this->retention->prune('archive'))->toBe(1)
        ->and($this->retention->prune('archive'))->toBe(0)
        // Archiving keeps the personal data, so the record is still past its window.
        ->and($this->retention->preview()['count'])->toBe(1)
        ->and($this->retention->prune('anonymize'))->toBe(1)
        ->and(retentionTimeline($submissionId))->toBe(['archived', 'anonymized'])
        ->and(get_post_meta($submissionId, 'corex_submitter_email', true))->toBe('');
});

it('moves an archived submission to trash', function () {
    $submissionId = ($this->aged)(120);
    expect($this->retention->preview()['count'])->toBe(1);
    $this->retention->prune('archive');

    expect($this->retention->prune('trash'))->toBe(1)
        ->and(get_post_status($submissionId))->toBe('trash');
});

it('neither archives nor trashes a submission that is already anonymized', function () {
    $submissionId = ($this->aged)(120);
    expect($this->retention->preview()['count'])->toBe(1);
    $this->retention->prune('anonymize');

    expect($this->retention->prune('archive'))->toBe(0)
        ->and($this->retention->prune('trash'))->toBe(0)
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
        $this->retention->prune('anonymize');
    } finally {
        remove_filter('update_post_metadata', $recordHandled, 10);
    }

    expect($handled)->toBe([$oldest, $middle, $newest]);
});
