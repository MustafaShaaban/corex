<?php

/**
 * Resending a flow's email from the inbox reaches the people it was sent to (spec 112, User Story
 * 7, FR-032 to FR-034, SC-008), end to end: a real Email Studio template, a published flow, a
 * visitor's submission, and the resend route.
 *
 * Mail is captured, never delivered: the mail settings are answered by filters
 * (`MailDelivery::CAPTURE`) and `wp_mail()` is stopped besides. Every post is the test's own.
 *
 * @package Corex\Tests\Integration\Submissions
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Email\Capture\CapturedEmailRepository;
use Corex\Email\Studio\EmailTemplateRepository;
use Corex\Email\Studio\EmailTemplateVersion;
use Corex\Email\Studio\WpEmailStudioStore;
use Corex\Forms\Flow\FlowController;
use Corex\Forms\Flow\WpFlowStore;
use Corex\Forms\Submission\FlowSubmissionController;
use Corex\Tests\Support\CreatedPosts;
use Corex\Tests\Support\MailDelivery;
use Corex\Tests\Support\NotificationRows;
use Corex\Tests\Support\WatchedTransients;

const RESEND_FLOW_SLUG = 'resend-recipients-flow';

function resendFlowRequest(string $method, string $route, array $payload = [], array $url = []): WP_REST_Request
{
    $request = new WP_REST_Request($method, $route);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    $request->set_body_params($payload);
    $request->set_url_params($url);

    return $request;
}

beforeEach(function () {
    $container = Boot::app()->container();
    $container->make(WpFlowStore::class)->registerPostType();
    $this->emailStore = $container->make(WpEmailStudioStore::class);
    $this->emailStore->registerPostType();
    $this->posts = CreatedPosts::watch(WpFlowStore::POST_TYPE, 'corex_submission', WpEmailStudioStore::POST_TYPE);
    $this->transients = WatchedTransients::watch('corex_throttle_');
    $this->mail = MailDelivery::answerAs(MailDelivery::CAPTURE);
    add_filter('pre_wp_mail', '__return_false');

    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) $administrators[0]);

    // A template, a flow whose team notification goes to the team, and a visitor's submission.
    $now = new DateTimeImmutable('now');
    $templates = new EmailTemplateRepository($this->emailStore);
    $template = $templates->create('resend-recipients', 'Resend recipients', get_current_user_id(), $now);
    $version = $templates->saveDraft(new EmailTemplateVersion(
        id: 1, templateId: $template->id, versionNumber: 1, subject: 'New submission', fromName: 'CoreX',
        fromAddress: 'hello@example.com', htmlBody: '<p>New submission.</p>', plainText: 'New submission.',
        plainTextMode: 'manual', layoutId: 1, layoutVersion: 1, variableKeys: [], createdBy: get_current_user_id(),
        createdAt: $now, checksum: hash('sha256', 'resend-recipients'),
    ));
    $templates->activate($template->id, $version->versionNumber, get_current_user_id(), $now);

    $flows = $container->make(FlowController::class);
    $created = $flows->create(resendFlowRequest('POST', '/corex/v1/flows', [
        'slug' => RESEND_FLOW_SLUG, 'name' => 'Resend recipients', 'owner_id' => get_current_user_id(), 'placement_type' => 'block',
        'configuration' => [
            'schema' => [['uuid' => 'email-field', 'key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true]],
            'validation' => ['email' => ['required', 'email']],
            'routing' => ['rules' => [], 'fallback' => ['type' => 'flow_owner', 'config' => []]],
            'email_routes' => [[
                'event' => 'team_notification', 'enabled' => true, 'template_id' => $template->id,
                'recipient' => 'team@example.com', 'reply_to' => 'submission.email',
            ]],
            'success' => ['type' => 'inline', 'message' => 'Received.'],
            'placement_snapshot' => ['type' => 'block'],
        ],
    ]));
    $flowId = $created->get_data()['data']['flow']['id'];
    $flows->publish(resendFlowRequest('POST', "/corex/v1/flows/{$flowId}/publish", ['expected_version' => 1], ['id' => $flowId]));

    $submitted = $container->make(FlowSubmissionController::class)->submit(
        resendFlowRequest('POST', "/corex/v1/flows/{$flowId}/submit", ['email' => 'visitor@example.com'], ['id' => $flowId]),
    );
    $this->submissionId = (int) $submitted->get_data()['data']['submission_id'];
    $this->binding = fn (): array => get_post_meta($this->submissionId, 'corex_email_json', true)['bindings']['team_notification'];

    // The spec's independent test resends a failed send. Captured is never failed, and a real
    // transport could reach a real mailbox on this install, so the first send's attempt (this
    // test's own record) is marked as one that failed and may be retried, and so is its binding.
    $attempt = $this->emailStore->findBySlug('email_attempt', ($this->binding)()['attempt_id']);
    $this->emailStore->update($attempt['id'], $attempt['name'], ['state' => 'failed', 'retryable' => true] + $attempt['payload']);
    $emails = get_post_meta($this->submissionId, 'corex_email_json', true);
    $emails['bindings']['team_notification']['retryable'] = true;
    update_post_meta($this->submissionId, 'corex_email_json', $emails);
    $this->resend = fn (): WP_REST_Response => rest_do_request(resendFlowRequest(
        'POST',
        "/corex/v1/submissions/{$this->submissionId}/resend",
        ['attempt_id' => ($this->binding)()['attempt_id']],
    ));
    $this->captured = fn (string $attemptId): array => array_values(array_filter(
        (new CapturedEmailRepository($this->emailStore))->latest(100),
        static fn ($email): bool => $email->attemptId === $attemptId,
    ));
});

afterEach(function () {
    remove_filter('pre_wp_mail', '__return_false');
    MailDelivery::stop($this->mail);
    $this->posts->delete();
    $this->transients->restore();
    NotificationRows::forget('submission.new:' . RESEND_FLOW_SLUG);
});

it('records on the submission who each email was sent to', function () {
    expect(($this->binding)()['recipients'])->toBe(['team@example.com']);
});

it('resends the team notification to the team only, and says so', function () {
    $answer = ($this->resend)()->get_data()['data'];
    $captured = ($this->captured)($answer['result']['attempt_id']);

    expect($answer['recipients'])->toBe(['team@example.com'])
        ->and($answer['basis'])->toBe('recorded')
        ->and($captured)->toHaveCount(1)
        ->and($captured[0]->to)->toBe(['team@example.com']);
});

it('resends an email recorded before its recipients were, once its attempt confirms the flow\'s recipient', function () {
    $emails = get_post_meta($this->submissionId, 'corex_email_json', true);
    unset($emails['bindings']['team_notification']['recipients']);
    update_post_meta($this->submissionId, 'corex_email_json', $emails);

    $answer = ($this->resend)()->get_data()['data'];

    expect($answer['basis'])->toBe('rechecked')
        ->and(($this->captured)($answer['result']['attempt_id'])[0]->to)->toBe(['team@example.com']);
});

it('does not resend, and says why, when an older email\'s recipients cannot be worked out again', function () {
    $emails = get_post_meta($this->submissionId, 'corex_email_json', true);
    unset($emails['bindings']['team_notification']['recipients']);
    update_post_meta($this->submissionId, 'corex_email_json', $emails);
    update_post_meta($this->submissionId, 'corex_flow_version_id', 999999999);

    $response = ($this->resend)();
    $detail = rest_do_request(resendFlowRequest('GET', "/corex/v1/submissions/{$this->submissionId}"))
        ->get_data()['data']['submission']['related_emails']['bindings']['team_notification'];

    expect($response->get_status())->not->toBe(200)
        ->and($detail['resendable'])->toBeFalse()
        ->and($detail['not_resendable_reason'])->toBe('recipients_unknown')
        ->and($detail['kind'])->toBe('team_notification');
});

it('takes the recorded recipients out when the submission is anonymised', function () {
    ($this->resend)();

    Boot::app()->container()->make(\Corex\Config\Data\WpSubmissionsReader::class)->anonymizeForRetention($this->submissionId);

    $history = (array) get_post_meta($this->submissionId, 'corex_submission_timeline', true);
    expect(($this->binding)())->not->toHaveKey('recipients')
        ->and(array_filter(array_column(array_column($history, 'summary'), 'recipients')))->toBe([]);
});
