<?php

/**
 * WordPress persistence proof for spec 068 T094-T096.
 *
 * @package Corex\Tests\Integration\Submissions
 */

declare(strict_types=1);

use Corex\Config\Data\WpSubmissionsReader;
use Corex\Config\Submissions\SubmissionAccessScope;
use Corex\Config\Submissions\SubmissionInboxQuery;
use Corex\Config\Submissions\SubmissionTimelineRepository;
use Corex\Config\Submissions\SubmissionWorkflowService;

beforeEach(function () {
    if (! post_type_exists('corex_submission')) {
        register_post_type('corex_submission', ['public' => false]);
    }
    $this->submissionBaseline = get_posts([
        'post_type' => 'corex_submission',
        'post_status' => 'any',
        'posts_per_page' => 500,
        'fields' => 'ids',
    ]);
});

afterEach(function () {
    $ids = get_posts([
        'post_type' => 'corex_submission',
        'post_status' => 'any',
        'posts_per_page' => 500,
        'fields' => 'ids',
    ]);
    foreach (array_diff($ids, $this->submissionBaseline) as $id) {
        wp_delete_post((int) $id, true);
    }
});

it('queries real scoped records and persists workflow notes and timeline', function () {
    $submissionId = wp_insert_post([
        'post_type' => 'corex_submission',
        'post_status' => 'private',
        'post_title' => 'Contact submission',
        'meta_input' => [
            'corex_flow_id' => 44,
            'corex_form_slug' => 'contact',
            'corex_flow_label_snapshot' => 'Contact us',
            'corex_submission_status' => 'new',
            'corex_owner_type' => 'team',
            'corex_owner_key' => 'sales',
            'corex_is_test' => 0,
            'corex_submitter_name' => 'Sam Doe',
            'corex_submitter_email' => 'sam@example.com',
            'corex_values_json' => ['name' => 'Sam Doe', 'email' => 'sam@example.com'],
            'corex_submission_updated_at' => '2026-07-04T12:00:00+00:00',
        ],
    ]);
    wp_insert_post([
        'post_type' => 'corex_submission',
        'post_status' => 'private',
        'post_title' => 'Marked test submission',
        'meta_input' => [
            'corex_flow_id' => 44,
            'corex_submission_status' => 'new',
            'corex_owner_type' => 'team',
            'corex_owner_key' => 'sales',
            'corex_is_test' => 1,
        ],
    ]);

    $reader = new WpSubmissionsReader();
    $scope = new SubmissionAccessScope(7, false, ['sales']);
    $page = $reader->queryInbox(SubmissionInboxQuery::from([
        'flow' => 44,
        'status' => 'new',
        'search' => 'sam@example.com',
    ]), $scope);

    expect($page['total'])->toBe(1)
        ->and($page['items'][0])->toMatchArray([
            'id' => $submissionId,
            'submitter_name' => 'Sam Doe',
            'submitter_email' => 'sam@example.com',
            'owner_type' => 'team',
            'owner_key' => 'sales',
        ]);

    $timeline = new SubmissionTimelineRepository();
    $workflow = new SubmissionWorkflowService($reader, $timeline);
    $updated = $workflow->changeStatus($scope, $submissionId, 'in_progress', '2026-07-04T12:00:00+00:00');
    $note = $workflow->addNote($scope, $submissionId, 'Follow up tomorrow.', 'corex-team');

    expect($updated['status'])->toBe('in_progress')
        ->and($note)->toMatchArray(['author_id' => 7, 'body' => 'Follow up tomorrow.'])
        ->and($timeline->forSubmission($submissionId, false))->toHaveCount(2)
        ->and(get_post_meta($submissionId, 'corex_submission_status', true))->toBe('in_progress');
});
