<?php

/**
 * Published flow to visitor pipeline to Inbox integration (spec 068: T088).
 *
 * @package Corex\Tests\Integration\Forms
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Forms\Flow\FlowController;
use Corex\Forms\Flow\WpFlowStore;
use Corex\Forms\Submission\FlowSubmissionController;
use Corex\Email\Capture\CapturedEmailRepository;
use Corex\Email\Studio\EmailTemplateRepository;
use Corex\Email\Studio\EmailTemplateVersion;
use Corex\Email\Studio\WpEmailStudioStore;

function lifecycleFlowRequest(string $method, string $route, array $payload = []): WP_REST_Request
{
    $request = new WP_REST_Request($method, $route);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    $request->set_body_params($payload);

    return $request;
}

beforeEach(function () {
    $container = Boot::app()->container();
    $this->flowStore = $container->make(WpFlowStore::class);
    $this->flowStore->registerPostType();
    $this->flowsController = $container->make(FlowController::class);
    $this->visitorController = $container->make(FlowSubmissionController::class);
    $this->emailStore = $container->make(WpEmailStudioStore::class);
    $this->emailStore->registerPostType();
    $this->previousAppEnvironment = get_option('corex_app_env', null);
    update_option('corex_app_env', 'development');
    $this->lifecycleBaseline = get_posts([
        'post_type' => [WpFlowStore::POST_TYPE, 'corex_submission'],
        'post_status' => 'any',
        'posts_per_page' => 500,
        'fields' => 'ids',
    ]);
    $this->emailBaseline = get_posts([
        'post_type' => WpEmailStudioStore::POST_TYPE,
        'post_status' => 'any',
        'posts_per_page' => 500,
        'fields' => 'ids',
    ]);
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));
});

afterEach(function () {
    $ids = get_posts([
        'post_type' => [WpFlowStore::POST_TYPE, 'corex_submission'],
        'post_status' => 'any',
        'posts_per_page' => 500,
        'fields' => 'ids',
    ]);
    foreach (array_diff($ids, $this->lifecycleBaseline) as $id) {
        wp_delete_post((int) $id, true);
    }
    $emailIds = get_posts([
        'post_type' => WpEmailStudioStore::POST_TYPE,
        'post_status' => 'any',
        'posts_per_page' => 500,
        'fields' => 'ids',
    ]);
    foreach (array_diff($emailIds, $this->emailBaseline) as $id) {
        wp_delete_post((int) $id, true);
    }
    if ($this->previousAppEnvironment === null) {
        delete_option('corex_app_env');
    } else {
        update_option('corex_app_env', $this->previousAppEnvironment);
    }
});

it('runs a published visitor flow through storage routing email inbox and timeline', function () {
    $now = new DateTimeImmutable('2026-07-04T12:00:00+00:00');
    $templates = new EmailTemplateRepository($this->emailStore);
    $template = $templates->create('flow-lifecycle-notification', 'Flow lifecycle notification', get_current_user_id(), $now);
    $version = $templates->saveDraft(new EmailTemplateVersion(
        id: 1,
        templateId: $template->id,
        versionNumber: 1,
        subject: 'New flow submission',
        fromName: 'CoreX',
        fromAddress: 'hello@example.com',
        htmlBody: '<p>A new flow submission was received.</p>',
        plainText: 'A new flow submission was received.',
        plainTextMode: 'manual',
        layoutId: 1,
        layoutVersion: 1,
        variableKeys: [],
        createdBy: get_current_user_id(),
        createdAt: $now,
        checksum: hash('sha256', 'flow-lifecycle-notification'),
    ));
    $templates->activate($template->id, $version->versionNumber, get_current_user_id(), $now);
    $payload = [
        'slug' => 'lifecycle-flow',
        'name' => 'Lifecycle flow',
        'owner_id' => get_current_user_id(),
        'placement_type' => 'block',
        'configuration' => [
            'schema' => [
                ['uuid' => 'email-field', 'key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                [
                    'uuid' => 'consent-field',
                    'key' => 'consent',
                    'label' => 'Privacy consent',
                    'type' => 'consent',
                    'required' => true,
                    'help_text' => 'I agree to the privacy policy.',
                    'personal_data_class' => 'consent',
                ],
            ],
            'validation' => ['email' => ['required', 'email'], 'consent' => ['required']],
            'routing' => ['rules' => [], 'fallback' => ['type' => 'flow_owner', 'config' => []]],
            'email_routes' => [[
                'event' => 'team_notification',
                'enabled' => true,
                'template_id' => $template->id,
                'recipient' => 'team@example.com',
                'reply_to' => 'submission.email',
            ]],
            'success' => ['type' => 'inline', 'message' => 'Received.'],
            'placement_snapshot' => ['type' => 'block'],
        ],
    ];
    $created = $this->flowsController->create(lifecycleFlowRequest('POST', '/corex/v1/flows', $payload));
    $flowId = $created->get_data()['data']['flow']['id'];
    $publish = lifecycleFlowRequest('POST', '/corex/v1/flows/' . $flowId . '/publish', ['expected_version' => 1]);
    $publish->set_url_params(['id' => $flowId]);
    $this->flowsController->publish($publish);

    $submit = lifecycleFlowRequest('POST', '/corex/v1/flows/' . $flowId . '/submit', [
        'email' => 'visitor@example.com',
        'consent' => '1',
        'utm_source' => 'docs',
        'page_url' => 'https://example.test/contact',
    ]);
    $submit->set_url_params(['id' => $flowId]);
    $response = $this->visitorController->submit($submit);
    $data = $response->get_data()['data'];
    $submissionId = $data['submission_id'];

    expect($response->get_status())->toBe(200)
        ->and($data['completed'])->toBeTrue()
        ->and(array_column($data['stages'], 'key'))->toBe([
            'validation', 'protection', 'storage', 'routing', 'email', 'inbox', 'timeline',
        ])
        ->and(get_post_meta($submissionId, 'corex_field_email', true))->toBe('visitor@example.com')
        ->and((int) get_post_meta($submissionId, 'corex_flow_id', true))->toBe($flowId)
        ->and((int) get_post_meta($submissionId, 'corex_flow_version_id', true))->toBeGreaterThan(0)
        ->and(get_post_meta($submissionId, 'corex_utm_json', true)['utm_source'])->toBe('docs')
        ->and(get_post_meta($submissionId, 'corex_consent_snapshot_json', true)['consent']['statement'])->toBe('I agree to the privacy policy.')
        ->and(get_post_meta($submissionId, 'corex_routing_json', true)['target_type'])->toBe('flow_owner')
        ->and(get_post_meta($submissionId, 'corex_email_json', true)['state'])->toBe('complete')
        ->and(get_post_meta($submissionId, 'corex_email_json', true)['bindings']['team_notification']['state'])->toBe('captured')
        ->and((new CapturedEmailRepository($this->emailStore))->latest())->toHaveCount(1)
        ->and(get_post_meta($submissionId, 'corex_inbox_json', true)['status'])->toBe('new')
        ->and(get_post_meta($submissionId, 'corex_submission_timeline', true)[0]['kind'])->toBe('flow.submitted');
});
