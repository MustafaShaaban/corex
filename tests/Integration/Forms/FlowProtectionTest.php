<?php

/**
 * A published flow is guarded by its trap field and by the site's challenge, on real WordPress.
 *
 * Neither guarded it: through this route a submission with its trap field filled was stored and
 * answered 200, and one carrying a token the provider accepts was refused, because the stage that
 * judges both was handed neither (measured 2026-10-13, DECISIONS #358).
 *
 * The route is driven through the real middleware, sanitiser and stages. Only the provider is
 * stood in for: nothing here calls a challenge provider.
 *
 * @package Corex\Tests\Integration\Forms
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Events\EventDispatcher;
use Corex\Forms\Flow\FlowController;
use Corex\Forms\Flow\FlowService;
use Corex\Forms\Flow\WpFlowStore;
use Corex\Forms\Submission\FlowSchemaFactory;
use Corex\Forms\Submission\FlowSubmissionController;
use Corex\Forms\Submission\FlowVisitorSubmissionService;
use Corex\Forms\Submission\FormSubmissionPipeline;
use Corex\Forms\Submission\FormSubmissionService;
use Corex\Forms\Submission\Stages\EmailStage;
use Corex\Forms\Submission\Stages\InboxStage;
use Corex\Forms\Submission\Stages\ProtectionStage;
use Corex\Forms\Submission\Stages\RoutingStage;
use Corex\Forms\Submission\Stages\StorageStage;
use Corex\Forms\Submission\Stages\TimelineStage;
use Corex\Forms\Submission\Stages\ValidationStage;
use Corex\Forms\Submission\SubmissionChallenge;
use Corex\Http\ClientAddress;
use Corex\Http\Middleware\MiddlewareResolver;
use Corex\Http\Middleware\Pipeline;
use Corex\Security\ChallengeVerifier;
use Corex\Tests\Support\CreatedPosts;
use Corex\Tests\Support\MailDelivery;
use Corex\Tests\Support\NotificationRows;
use Corex\Tests\Support\WatchedTransients;

beforeEach(function () {
    Boot::app()->container()->make(WpFlowStore::class)->registerPostType();
    $this->flows = CreatedPosts::watch(WpFlowStore::POST_TYPE);
    $this->stored = CreatedPosts::watch('corex_submission');
    // A new flow is bound to the emails CoreX ships, which keep a record of each one sent (spec 120).
    $this->sentMail = CreatedPosts::watch('corex_email_asset', 'corex_email_log');
    // A submission is counted by the rate limiter, and tells whoever manages submissions.
    $this->transients = WatchedTransients::watch('corex_throttle_');
    $this->mailSettings = MailDelivery::answerAs(MailDelivery::CAPTURE);
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));
});

afterEach(function () {
    $this->sentMail->delete();
    $this->stored->delete();
    $this->flows->delete();
    $this->transients->restore();
    NotificationRows::forgetPrefixed('submission.new:guarded-');
    MailDelivery::stop($this->mailSettings);
});

/** @param array<string,mixed> $body */
function guardedFlowRequest(string $route, array $body): WP_REST_Request
{
    $request = new WP_REST_Request('POST', $route);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    $request->set_body_params($body);

    return $request;
}

/** A published flow with one field, `email`, that inherits the site's protection. Answers its id. */
function publishedGuardedFlow(): int
{
    $flows = Boot::app()->container()->make(FlowController::class);
    $created = $flows->create(guardedFlowRequest('/corex/v1/flows', [
        'slug' => 'guarded-' . strtolower(wp_generate_password(10, false)),
        'name' => 'Guarded flow',
        'owner_id' => get_current_user_id(),
        'placement_type' => 'block',
        'configuration' => [
            'schema' => [['uuid' => 'email-field', 'key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true]],
            'validation' => ['email' => ['required', 'email']],
            'routing' => ['rules' => [], 'fallback' => ['type' => 'flow_owner', 'config' => []]],
            'email_routes' => [],
            'success' => ['type' => 'inline', 'message' => 'Received.'],
            'placement_snapshot' => ['type' => 'block'],
        ],
    ]));
    $flowId = (int) $created->get_data()['data']['flow']['id'];
    $publish = guardedFlowRequest('/corex/v1/flows/' . $flowId . '/publish', ['expected_version' => 1]);
    $publish->set_url_params(['id' => $flowId]);
    $flows->publish($publish);

    return $flowId;
}

/** @param array<string,mixed> $body */
function submitToGuardedFlow(FlowSubmissionController $controller, int $flowId, array $body): WP_REST_Response
{
    $submit = guardedFlowRequest('/corex/v1/flows/' . $flowId . '/submit', $body);
    $submit->set_url_params(['id' => $flowId]);

    return $controller->submit($submit);
}

/**
 * The route's controller with the stages the container builds, in the order the forms plugin
 * lists them, around a provider that accepts one token.
 */
function flowControllerWithProviderAccepting(string $accepted): FlowSubmissionController
{
    $container = Boot::app()->container();
    $provider  = new class($accepted) implements ChallengeVerifier {
        public function __construct(private string $accepted)
        {
        }

        public function verify(string $token): bool
        {
            return $token === $this->accepted;
        }
    };

    return new FlowSubmissionController(
        new FlowVisitorSubmissionService(
            $container->make(FlowService::class),
            new FormSubmissionPipeline([
                $container->make(ValidationStage::class),
                new ProtectionStage(new SubmissionChallenge($provider)),
                $container->make(StorageStage::class),
                $container->make(RoutingStage::class),
                $container->make(EmailStage::class),
                $container->make(InboxStage::class),
                $container->make(TimelineStage::class),
            ]),
            $container->make(FlowSchemaFactory::class),
            $container->make(EventDispatcher::class),
        ),
        $container->make(Pipeline::class),
        $container->make(MiddlewareResolver::class),
        $container->make(ClientAddress::class),
    );
}

it('refuses a submission whose trap field is filled, and stores nothing', function () {
    $flowId = publishedGuardedFlow();

    $response = submitToGuardedFlow(
        Boot::app()->container()->make(FlowSubmissionController::class),
        $flowId,
        ['email' => 'visitor@example.com', FormSubmissionService::HONEYPOT_KEY => 'a bot wrote here'],
    );

    expect($response->get_status())->toBe(422)
        // The answers were in order: it is the protection stage that stopped it.
        ->and(array_column($response->get_data()['details']['stages'], 'state', 'key'))
        ->toBe(['validation' => 'success', 'protection' => 'failure'])
        ->and($this->stored->ids())->toBe([]);
});

it('stores a submission whose token the provider accepts, without the token', function () {
    $flowId = publishedGuardedFlow();

    $response = submitToGuardedFlow(
        flowControllerWithProviderAccepting('good-token'),
        $flowId,
        ['email' => 'visitor@example.com', SubmissionChallenge::TOKEN_KEY => 'good-token'],
    );

    $storedId = $this->stored->ids()[0] ?? 0;

    expect($response->get_status())->toBe(200)
        ->and($this->stored->ids())->toHaveCount(1)
        ->and(get_post_meta($storedId, 'corex_field_email', true))->toBe('visitor@example.com')
        ->and(get_post_meta($storedId, 'corex_spam_json', true)['captcha'] ?? null)->toBe('passed')
        ->and(wp_json_encode(get_post_meta($storedId)))->not->toContain('good-token');
});

it('refuses a submission the provider does not accept, and stores nothing', function (array $sent) {
    $flowId = publishedGuardedFlow();

    $response = submitToGuardedFlow(
        flowControllerWithProviderAccepting('good-token'),
        $flowId,
        ['email' => 'visitor@example.com'] + $sent,
    );

    expect($response->get_status())->toBe(422)
        ->and($this->stored->ids())->toBe([]);
})->with([
    'a token made up' => [[SubmissionChallenge::TOKEN_KEY => 'forged']],
    'no token at all' => [[]],
]);
