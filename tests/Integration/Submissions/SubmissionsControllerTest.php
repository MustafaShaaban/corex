<?php

/**
 * Inbox REST contract and security tests for spec 068 T102 / FR-046-FR-058.
 *
 * @package Corex\Tests\Integration\Submissions
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Submissions\SubmissionsController;

function submissionsRequest(string $method, string $route, array $payload = []): WP_REST_Request
{
    $request = new WP_REST_Request($method, $route);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    if ($method === 'GET') {
        $request->set_query_params($payload);
    } else {
        $request->set_body_params($payload);
    }

    return $request;
}

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
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));
    $this->submissionsController = Boot::app()->container()->make(SubmissionsController::class);
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

it('registers the complete Inbox query workflow bulk email and export routes', function () {
    add_action('rest_api_init', [$this->submissionsController, 'register']);
    do_action('rest_api_init', rest_get_server());
    $routes = rest_get_server()->get_routes();

    expect($routes)->toHaveKeys([
        '/corex/v1/submissions',
        '/corex/v1/submissions/(?P<id>\d+)',
        '/corex/v1/submissions/(?P<id>\d+)/notes',
        '/corex/v1/submissions/(?P<id>\d+)/reply',
        '/corex/v1/submissions/(?P<id>\d+)/resend',
        '/corex/v1/submissions/(?P<id>\d+)/email-log',
        '/corex/v1/submissions/bulk/preview',
        '/corex/v1/submissions/bulk/apply',
        '/corex/v1/submissions/exports',
        '/corex/v1/submissions/exports/(?P<export>\d+)/download',
    ]);
});

it('queries details mutates status and adds notes through canonical envelopes', function () {
    $submissionId = wp_insert_post([
        'post_type' => 'corex_submission',
        'post_status' => 'private',
        'post_title' => 'REST submission',
        'meta_input' => [
            'corex_flow_id' => 90,
            'corex_form_slug' => 'contact',
            'corex_flow_label_snapshot' => 'Contact',
            'corex_submission_status' => 'new',
            'corex_owner_type' => 'none',
            'corex_owner_key' => '',
            'corex_is_test' => 0,
            'corex_submitter_name' => 'Sam',
            'corex_submitter_email' => 'sam@example.com',
            'corex_values_json' => ['name' => 'Sam', 'email' => 'sam@example.com'],
            'corex_submission_updated_at' => '2026-07-04T12:00:00+00:00',
        ],
    ]);

    $list = $this->submissionsController->index(submissionsRequest('GET', '/corex/v1/submissions', ['flow' => 90]));
    $detailRequest = submissionsRequest('GET', '/corex/v1/submissions/' . $submissionId);
    $detailRequest->set_param('id', $submissionId);
    $detail = $this->submissionsController->show($detailRequest);
    $patch = submissionsRequest('PATCH', '/corex/v1/submissions/' . $submissionId, [
        'status' => 'in_progress',
        'expected_updated_at' => '2026-07-04T12:00:00+00:00',
    ]);
    $patch->set_param('id', $submissionId);
    $updated = $this->submissionsController->update($patch);
    $note = submissionsRequest('POST', '/corex/v1/submissions/' . $submissionId . '/notes', [
        'body' => 'Follow up tomorrow.',
        'visibility' => 'corex-team',
    ]);
    $note->set_param('id', $submissionId);
    $noted = $this->submissionsController->addNote($note);

    expect($list->get_status())->toBe(200)
        ->and($list->get_data()['data']['total'])->toBe(1)
        ->and($detail->get_data()['data']['submission'])->toMatchArray(['id' => $submissionId, 'submitter_name' => 'Sam'])
        ->and($updated->get_data()['data']['submission']['status'])->toBe('in_progress')
        ->and($noted->get_data()['data']['note'])->toMatchArray(['body' => 'Follow up tomorrow.', 'author_id' => get_current_user_id()])
        ->and(get_post_meta($submissionId, 'corex_submission_timeline', true))->toHaveCount(2);
});

it('rejects mutation without a valid REST nonce before changing state', function () {
    $submissionId = wp_insert_post([
        'post_type' => 'corex_submission',
        'post_status' => 'private',
        'post_title' => 'Protected submission',
        'meta_input' => [
            'corex_submission_status' => 'new',
            'corex_owner_type' => 'none',
            'corex_owner_key' => '',
            'corex_submission_updated_at' => 'v1',
        ],
    ]);
    $request = new WP_REST_Request('PATCH', '/corex/v1/submissions/' . $submissionId);
    $request->set_param('id', $submissionId);
    $request->set_body_params(['status' => 'closed', 'expected_updated_at' => 'v1']);

    $response = $this->submissionsController->update($request);

    expect($response->get_status())->toBe(403)
        ->and(get_post_meta($submissionId, 'corex_submission_status', true))->toBe('new');
});
