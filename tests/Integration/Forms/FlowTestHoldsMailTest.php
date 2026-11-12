<?php

/**
 * Spec 113, US3 (FR-013 to FR-017): a flow's marked test holds every email it would send, on a
 * production site with live delivery on too, lists them, and lets the next real submission send.
 *
 * Production here is MailDelivery's: every email that would reach the transport is stopped
 * before it, and the last `pre_wp_mail` answer counts those emails.
 *
 * @package Corex\Tests\Integration\Forms
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
use Corex\Mail\Capture\WpMailInterceptor;
use Corex\Mail\SubmissionEmailRecords;
use Corex\Tests\Support\CreatedPosts;
use Corex\Tests\Support\MailDelivery;
use Corex\Tests\Support\NotificationRows;
use Corex\Tests\Support\WatchedTransients;

function holdFlowRequest(string $method, string $route, array $payload = []): WP_REST_Request
{
    $request = new WP_REST_Request($method, $route);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    $request->set_body_params($payload);

    return $request;
}

beforeEach(function () {
    $container = Boot::app()->container();
    $container->make(WpMailInterceptor::class)->register();
    $this->container = $container;
    $this->flows = $container->make(FlowController::class);
    $this->visitors = $container->make(FlowSubmissionController::class);
    $this->emailStore = $container->make(WpEmailStudioStore::class);
    $this->emailStore->registerPostType();
    $container->make(WpFlowStore::class)->registerPostType();
    $this->posts = CreatedPosts::watch(WpFlowStore::POST_TYPE, 'corex_submission', WpEmailStudioStore::POST_TYPE, 'corex_email_log');
    $this->transients = WatchedTransients::watch('corex_throttle_');
    $this->settings = MailDelivery::answerAs(MailDelivery::DELIVER);
    $this->transport = [];
    $this->spy = function (mixed $return, array $atts): mixed {
        if ($return === true) {
            $this->transport[] = (string) ($atts['subject'] ?? '');
        }

        return true;
    };
    // After MailDelivery's own stop (which answers `true` before every other answer) and after
    // the gate: an email still answered `true` here is one the site would have sent.
    add_filter('pre_wp_mail', $this->spy, PHP_INT_MAX, 2);
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));
});

afterEach(function () {
    remove_filter('pre_wp_mail', $this->spy, PHP_INT_MAX);
    MailDelivery::stop($this->settings);
    $this->posts->delete();
    $this->transients->restore();
    NotificationRows::forget('submission.new:hold-flow');
    wp_set_current_user(0);
});

it('holds a test\'s emails on a production site, lists them, and sends the next real one', function () {
    $now = new DateTimeImmutable('now');
    $templates = new EmailTemplateRepository($this->emailStore);
    $template = $templates->create('hold-flow-notification', 'Hold flow notification', get_current_user_id(), $now);
    $version = $templates->saveDraft(new EmailTemplateVersion(
        id: 1,
        templateId: $template->id,
        versionNumber: 1,
        subject: 'Held flow submission',
        fromName: 'CoreX',
        fromAddress: 'hello@example.com',
        htmlBody: '<p>A submission.</p>',
        plainText: 'A submission.',
        plainTextMode: 'manual',
        layoutId: 1,
        layoutVersion: 1,
        variableKeys: [],
        createdBy: get_current_user_id(),
        createdAt: $now,
        checksum: hash('sha256', 'hold-flow-notification'),
    ));
    $templates->activate($template->id, $version->versionNumber, get_current_user_id(), $now);
    $created = $this->flows->create(holdFlowRequest('POST', '/corex/v1/flows', [
        'slug' => 'hold-flow',
        'name' => 'Hold flow',
        'owner_id' => get_current_user_id(),
        'placement_type' => 'block',
        'configuration' => [
            'schema' => [['uuid' => 'email-field', 'key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true]],
            'validation' => ['email' => ['required', 'email']],
            'routing' => ['rules' => [], 'fallback' => ['type' => 'flow_owner', 'config' => []]],
            'email_routes' => [[
                'event' => 'team_notification',
                'enabled' => true,
                'template_id' => $template->id,
                'recipient' => 'team@example.com',
            ]],
            'success' => ['type' => 'inline', 'message' => 'Received.'],
            'placement_snapshot' => ['type' => 'block'],
        ],
    ]));
    $flowId = $created->get_data()['data']['flow']['id'];

    $run = holdFlowRequest('POST', '/corex/v1/flows/' . $flowId . '/test', ['expected_version' => 1, 'values' => ['email' => 'tester@example.com']]);
    $run->set_url_params(['id' => $flowId]);
    $tested = $this->flows->test($run)->get_data()['data'];
    $held = $tested['held_emails'];
    $kept = (new CapturedEmailRepository($this->emailStore))->latest(5);
    $emailStage = array_values(array_filter($tested['stages'], static fn (array $stage): bool => $stage['key'] === 'email'))[0];

    expect($this->transport)->toBe([])
        ->and($held)->toHaveCount(1)
        ->and($held[0]['subject'])->toBe('Held flow submission')
        ->and($held[0]['to'])->toBe(['team@example.com'])
        ->and($kept[0]->captureId)->toBe($held[0]['capture_id'])
        ->and($kept[0]->state)->toBe('held_for_test')
        ->and($emailStage['message'])->toContain('held for the test');

    $publish = holdFlowRequest('POST', '/corex/v1/flows/' . $flowId . '/publish', ['expected_version' => 1]);
    $publish->set_url_params(['id' => $flowId]);
    $this->flows->publish($publish);
    $submit = holdFlowRequest('POST', '/corex/v1/flows/' . $flowId . '/submit', ['email' => 'visitor@example.com']);
    $submit->set_url_params(['id' => $flowId]);
    $this->visitors->submit($submit);

    expect($this->transport)->toBe(['Held flow submission']);

    // Deleting the test submission for good lets go of what it held.
    $attempt = (string) get_post_meta((int) $tested['submission_id'], 'corex_email_json', true)['bindings']['team_notification']['attempt_id'];
    $this->container->make(SubmissionEmailRecords::class)->forget([$attempt]);
    $after = array_map(static fn ($capture): string => $capture->captureId, (new CapturedEmailRepository($this->emailStore))->latest(10));

    expect($after)->not->toContain($held[0]['capture_id']);
});
