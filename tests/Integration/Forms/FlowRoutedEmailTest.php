<?php

/**
 * A flow's emails do not decide whether the submission is assigned or its actions run, and the
 * "Routed owner" recipient falls back with a note and is reached again by "Send again" (spec 122:
 * FR-008, FR-009, FR-018, FR-020, FR-022, SC-002).
 *
 * Mail is captured, never delivered: the mail settings are answered by filters
 * (`MailDelivery::CAPTURE`) and `wp_mail()` is stopped besides. Every post is the test's own.
 *
 * @package Corex\Tests\Integration\Forms
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Email\Capture\CapturedEmailRepository;
use Corex\Email\Studio\EmailTemplateRepository;
use Corex\Email\Studio\EmailTemplateVersion;
use Corex\Email\Studio\WpEmailStudioStore;
use Corex\Forms\Flow\FlowActionRegistry;
use Corex\Forms\Flow\FlowController;
use Corex\Forms\Flow\WpFlowStore;
use Corex\Forms\Routing\Teams\WpTeamStore;
use Corex\Forms\Submission\FlowSubmissionController;
use Corex\Tests\Support\CreatedPosts;
use Corex\Tests\Support\MailDelivery;
use Corex\Tests\Support\NotificationRows;
use Corex\Tests\Support\OptionRow;
use Corex\Tests\Support\WatchedTransients;

function routedEmailRequest(string $method, string $route, array $payload = [], array $url = []): WP_REST_Request
{
    $request = new WP_REST_Request($method, $route);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    $request->set_header('Content-Type', 'application/json');
    $request->set_body((string) wp_json_encode($payload));
    $request->set_url_params($url);

    return $request;
}

/** A published flow with an optional email question and a department; its id. */
function publishRoutedEmailFlow(string $slug, int $ownerId, array $routing, array $emailRoutes, array $actions = []): int
{
    $flows = Boot::app()->container()->make(FlowController::class);
    $created = $flows->create(routedEmailRequest('POST', '/corex/v1/flows', [
        'slug' => $slug,
        'name' => 'Routed email',
        'owner_id' => $ownerId,
        'configuration' => [
            'schema' => [
                ['uuid' => 'f-name', 'key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
                ['uuid' => 'f-email', 'key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => false],
                ['uuid' => 'f-dept', 'key' => 'department', 'label' => 'Department', 'type' => 'select', 'options' => [
                    ['value' => 'Sales', 'label' => 'Sales'], ['value' => 'Other', 'label' => 'Other'],
                ]],
            ],
            'validation' => ['name' => ['required'], 'email' => ['email']],
            'routing' => $routing,
            'email_routes' => $emailRoutes,
            'success' => ['type' => 'inline', 'message' => 'Received.'],
            'placement_snapshot' => ['type' => 'block'],
            'actions' => $actions,
        ],
    ]));
    $flowId = (int) $created->get_data()['data']['flow']['id'];
    expect($flows->publish(routedEmailRequest('POST', "/corex/v1/flows/{$flowId}/publish", ['expected_version' => 1], ['id' => $flowId]))->get_status())->toBe(200);

    return $flowId;
}

function submitRoutedEmail(int $flowId, array $values): WP_REST_Response
{
    $previous = get_current_user_id();
    wp_set_current_user(0);
    $response = Boot::app()->container()->make(FlowSubmissionController::class)->submit(
        routedEmailRequest('POST', "/corex/v1/flows/{$flowId}/submit", $values, ['id' => $flowId]),
    );
    wp_set_current_user($previous);

    return $response;
}

/** @return list<array<string,mixed>> */
function routedEmailTimeline(int $submissionId, string $stage): array
{
    return array_values(array_filter(
        (array) get_post_meta($submissionId, 'corex_submission_timeline', true),
        static fn (array $event): bool => $event['stage'] === $stage,
    ));
}

beforeEach(function () {
    $container = Boot::app()->container();
    $container->make(WpFlowStore::class)->registerPostType();
    $this->emailStore = $container->make(WpEmailStudioStore::class);
    $this->emailStore->registerPostType();
    $this->posts = CreatedPosts::watch(WpFlowStore::POST_TYPE, 'corex_submission', WpEmailStudioStore::POST_TYPE);
    $this->transients = WatchedTransients::watch('corex_throttle_');
    $this->mail = MailDelivery::answerAs(MailDelivery::CAPTURE);
    $this->teams = OptionRow::capture(WpTeamStore::OPTION);
    add_filter('pre_wp_mail', '__return_false');
    $this->slugs = [];

    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    $this->admin = (int) $administrators[0];
    wp_set_current_user($this->admin);
    $this->sara = wp_insert_user([
        'user_login' => 'routed-email-sara-' . wp_generate_password(6, false),
        'user_pass' => wp_generate_password(),
        'user_email' => 'sara-' . wp_generate_password(6, false) . '@example.test',
        'display_name' => 'Sara',
        'role' => 'author',
    ]);

    $now = new DateTimeImmutable('now');
    $templates = new EmailTemplateRepository($this->emailStore);
    $template = $templates->create('routed-email-' . strtolower(wp_generate_password(6, false, false)), 'Routed email', $this->admin, $now);
    $version = $templates->saveDraft(new EmailTemplateVersion(
        id: 1, templateId: $template->id, versionNumber: 1, subject: 'New submission', fromName: 'CoreX',
        fromAddress: 'hello@example.com', htmlBody: '<p>New submission.</p>', plainText: 'New submission.',
        plainTextMode: 'manual', layoutId: 1, layoutVersion: 1, variableKeys: [], createdBy: $this->admin,
        createdAt: $now, checksum: hash('sha256', 'routed-email'),
    ));
    $templates->activate($template->id, $version->versionNumber, $this->admin, $now);
    $this->templateId = $template->id;
    // A template with no active version: an email bound to it cannot be sent (spec 120 records a
    // confirmation with no address to go to as "no recipient", not as a failed email).
    $this->draftTemplateId = $templates->create('routed-email-draft-' . strtolower(wp_generate_password(6, false, false)), 'Routed email draft', $this->admin, $now)->id;
    $this->captured = fn (string $attemptId): array => array_values(array_filter(
        (new CapturedEmailRepository($this->emailStore))->latest(100),
        static fn ($email): bool => $email->attemptId === $attemptId,
    ));
});

afterEach(function () {
    wp_set_current_user($this->admin);
    remove_filter('pre_wp_mail', '__return_false');
    MailDelivery::stop($this->mail);
    $this->posts->delete();
    $this->transients->restore();
    OptionRow::restore(WpTeamStore::OPTION, $this->teams);
    foreach ($this->slugs as $slug) {
        NotificationRows::forget('submission.new:' . $slug);
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($this->sara);
});

it('answers the visitor with success, assigns the submission, records its routing and runs its actions when its email cannot be sent (FR-008, FR-009, FR-022; DECISIONS #476)', function () {
    $ran = [];
    $action = 'routed_email_probe_' . strtolower(wp_generate_password(6, false, false));
    Boot::app()->container()->make(FlowActionRegistry::class)->register($action, 'Probe', static function (array $submission) use (&$ran): void {
        $ran[] = $submission['submission_id'];
    });
    $this->slugs[] = 'routed-email-fails';
    $flowId = publishRoutedEmailFlow(
        'routed-email-fails',
        $this->sara,
        ['rules' => [], 'fallback' => ['type' => 'field_value', 'config' => ['value' => 'department', 'owners' => []]]],
        // The submitter's confirmation, bound to a template that has nothing to send.
        [['event' => 'submitter_confirmation', 'enabled' => true, 'template_id' => $this->draftTemplateId, 'recipient' => 'email', 'reply_to' => '']],
        [$action],
    );

    $response = submitRoutedEmail($flowId, ['name' => 'Visitor', 'email' => 'visitor@example.test', 'department' => 'Other']);
    $submissionId = (int) $response->get_data()['data']['submission_id'];
    $stages = array_column($response->get_data()['data']['stages'], 'state', 'key');
    $stored = get_posts(['post_type' => 'corex_submission', 'post_status' => 'private', 'fields' => 'ids', 'numberposts' => -1, 'meta_key' => 'corex_flow_id', 'meta_value' => (string) $flowId]);

    // A failed email is not the visitor's to fix: sending again would store a second submission.
    expect($response->get_status())->toBe(200)
        ->and($response->get_data()['ok'])->toBeTrue()
        ->and($response->get_data()['data']['success'])->toBe(['type' => 'inline', 'message' => 'Received.'])
        ->and($stored)->toBe([$submissionId])
        ->and($submissionId)->toBeGreaterThan(0)
        ->and(get_post_meta($submissionId, 'corex_email_json', true)['state'])->toBe('failed')
        ->and($stages['email'])->toBe('failure')
        ->and(array_keys($stages))->toBe(['validation', 'protection', 'storage', 'routing', 'inbox', 'timeline', 'email', 'actions'])
        ->and(get_post_meta($submissionId, 'corex_owner_type', true))->toBe('user')
        ->and(get_post_meta($submissionId, 'corex_owner_key', true))->toBe((string) $this->sara)
        ->and(routedEmailTimeline($submissionId, 'routing'))->toHaveCount(1)
        ->and(routedEmailTimeline($submissionId, 'notification')[0]['outcome'])->toBe('failure')
        ->and($ran)->toBe([$submissionId])
        ->and(routedEmailTimeline($submissionId, 'action')[0]['outcome'])->toBe('success');
});

it('sends a routed owner email with no address to the fallback recipient, and says so with the delivery and on the timeline (FR-018)', function () {
    update_option(WpTeamStore::OPTION, [['key' => 'routed-email-empty', 'name' => 'Empty desk', 'members' => []]]);
    $this->slugs[] = 'routed-email-note';
    $flowId = publishRoutedEmailFlow(
        'routed-email-note',
        $this->sara,
        [
            'rules' => [],
            'fallback' => ['type' => 'team', 'config' => ['value' => 'routed-email-empty']],
            'fallback_recipient' => 'desk@example.test',
        ],
        [['event' => 'team_notification', 'enabled' => true, 'template_id' => $this->templateId, 'recipient' => 'routed_owner', 'reply_to' => '']],
    );

    $response = submitRoutedEmail($flowId, ['name' => 'Visitor', 'department' => 'Sales']);
    $submissionId = (int) $response->get_data()['data']['submission_id'];
    $binding = get_post_meta($submissionId, 'corex_email_json', true)['bindings']['team_notification'];

    expect($response->get_status())->toBe(200)
        ->and($binding['recipients'])->toBe(['desk@example.test'])
        ->and($binding['note'])->toBe('Routed owner had no address; sent to desk@example.test')
        ->and(($this->captured)($binding['attempt_id'])[0]->to)->toBe(['desk@example.test'])
        ->and(routedEmailTimeline($submissionId, 'notification')[0]['summary']['note'])->toBe('Routed owner had no address; sent to desk@example.test');
});

it('sends a routed owner email again to the same routed recipients (FR-020)', function () {
    $this->slugs[] = 'routed-email-again';
    $flowId = publishRoutedEmailFlow(
        'routed-email-again',
        $this->sara,
        ['rules' => [], 'fallback' => ['type' => 'flow_owner', 'config' => []]],
        [['event' => 'team_notification', 'enabled' => true, 'template_id' => $this->templateId, 'recipient' => 'routed_owner', 'reply_to' => '']],
    );
    $submissionId = (int) submitRoutedEmail($flowId, ['name' => 'Visitor', 'department' => 'Sales'])->get_data()['data']['submission_id'];
    $sara = get_userdata($this->sara)->user_email;
    // The first send failed and may be retried; its recipients were not recorded, so the inbox
    // works them out again from the stored routing.
    $emails = get_post_meta($submissionId, 'corex_email_json', true);
    $attempt = $this->emailStore->findBySlug('email_attempt', $emails['bindings']['team_notification']['attempt_id']);
    $this->emailStore->update($attempt['id'], $attempt['name'], ['state' => 'failed', 'retryable' => true] + $attempt['payload']);
    $emails['bindings']['team_notification']['retryable'] = true;
    $recorded = $emails['bindings']['team_notification']['recipients'];
    unset($emails['bindings']['team_notification']['recipients']);
    update_post_meta($submissionId, 'corex_email_json', $emails);

    $answer = rest_do_request(routedEmailRequest(
        'POST',
        "/corex/v1/submissions/{$submissionId}/resend",
        ['attempt_id' => $emails['bindings']['team_notification']['attempt_id']],
    ))->get_data()['data'];

    expect($recorded)->toBe([$sara])
        ->and($answer['basis'])->toBe('rechecked')
        ->and($answer['recipients'])->toBe([$sara])
        ->and(($this->captured)($answer['result']['attempt_id'])[0]->to)->toBe([$sara]);
});
