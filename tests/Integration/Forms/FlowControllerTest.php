<?php

/**
 * Flow REST and middleware contracts (spec 068: FR-027–FR-029, FR-043, FR-044).
 *
 * @package Corex\Tests\Integration\Forms
 */

declare(strict_types=1);

use Corex\Forms\Flow\FlowController;
use Corex\Forms\Flow\WpFlowStore;
use Corex\Forms\Submission\FlowSubmissionController;
use Corex\Boot;
use Corex\Support\BootLogger;
use Corex\Tests\Support\CreatedPosts;
use Corex\Tests\Support\NotificationRows;
use Corex\Tests\Support\WatchedTransients;

beforeEach(function () {
    $container = Boot::app()->container();
    $this->store = $container->make(WpFlowStore::class);
    $this->store->registerPostType();
    $this->controller = $container->make(FlowController::class);
    $this->submissionController = $container->make(FlowSubmissionController::class);
    // The flows and submissions these tests create, remembered as they are inserted. This
    // replaced comparing the newest 500 ids of each before and after, which deleted whatever
    // another process created in the meantime.
    $this->posts = CreatedPosts::watch(WpFlowStore::POST_TYPE, 'corex_submission');
    // A submission goes through the rate limiter, which counts it in a transient.
    $this->transients = WatchedTransients::watch('corex_throttle_');

    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));
});

afterEach(function () {
    $this->posts->delete();
    $this->transients->restore();

    // A submission notifies whoever manages submissions, under a key made from the flow's slug.
    // `integration-flow` exists only in this file, so the notification about it is this file's.
    NotificationRows::forget('submission.new:integration-flow');
});

function flowRestPayload(string $message = 'Thanks'): array
{
    return [
        'slug' => 'integration-flow',
        'name' => 'Integration flow',
        'description' => 'A real persisted integration flow.',
        'owner_id' => get_current_user_id(),
        'placement_type' => 'none',
        'placement_id' => null,
        'test_mode' => false,
        'configuration' => [
            'schema' => [[
                'uuid' => 'field-email',
                'key' => 'email',
                'label' => 'Email',
                'type' => 'email',
                'required' => true,
            ]],
            'validation' => ['email' => ['required', 'email']],
            'routing' => ['rules' => [], 'fallback' => ['type' => 'flow_owner', 'config' => []]],
            'email_routes' => [],
            'success' => ['type' => 'inline', 'message' => $message],
            'placement_snapshot' => ['type' => 'none'],
        ],
    ];
}

function flowRestRequest(string $method, string $route, array $payload = []): WP_REST_Request
{
    $request = new WP_REST_Request($method, $route);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    $request->set_body_params($payload);

    return $request;
}

it('registers collection detail lifecycle preview test and extension routes', function () {
    add_action('rest_api_init', [$this->controller, 'register']);
    add_action('rest_api_init', [$this->submissionController, 'register']);
    do_action('rest_api_init', rest_get_server());
    $routes = rest_get_server()->get_routes();

    expect($routes)->toHaveKey('/corex/v1/flows')
        ->and($routes)->toHaveKey('/corex/v1/flows/(?P<id>\d+)')
        ->and($routes)->toHaveKey('/corex/v1/flows/(?P<id>\d+)/preview')
        ->and($routes)->toHaveKey('/corex/v1/flows/(?P<id>\d+)/publish')
        ->and($routes)->toHaveKey('/corex/v1/flows/(?P<id>\d+)/unpublish')
        ->and($routes)->toHaveKey('/corex/v1/flows/(?P<id>\d+)/close')
        ->and($routes)->toHaveKey('/corex/v1/flows/(?P<id>\d+)/test')
        ->and($routes)->toHaveKey('/corex/v1/flows/(?P<id>\d+)/submit')
        ->and($routes)->toHaveKey('/corex/v1/flows/extensions');
});

it('creates updates publishes and reads an immutable flow through REST', function () {
    $created = $this->controller->create(flowRestRequest('POST', '/corex/v1/flows', flowRestPayload()));
    $flow = $created->get_data()['data']['flow'];
    $version = $created->get_data()['data']['version'];

    $updatePayload = flowRestPayload('Updated thanks');
    $updatePayload['expected_version'] = 1;
    $updatePayload['expected_checksum'] = $version['checksum'];
    $update = flowRestRequest('PATCH', '/corex/v1/flows/' . $flow['id'], $updatePayload);
    $update->set_url_params(['id' => $flow['id']]);
    $updated = $this->controller->update($update);

    $publish = flowRestRequest('POST', '/corex/v1/flows/' . $flow['id'] . '/publish', ['expected_version' => 2]);
    $publish->set_url_params(['id' => $flow['id']]);
    $published = $this->controller->publish($publish);

    $visitor = flowRestRequest('POST', '/corex/v1/flows/' . $flow['id'] . '/submit', ['email' => 'visitor@example.com']);
    $visitor->set_url_params(['id' => $flow['id']]);
    $submitted = $this->submissionController->submit($visitor);

    $test = flowRestRequest('POST', '/corex/v1/flows/' . $flow['id'] . '/test', ['expected_version' => 2]);
    $test->set_url_params(['id' => $flow['id']]);
    $tested = $this->controller->test($test);

    $show = flowRestRequest('GET', '/corex/v1/flows/' . $flow['id']);
    $show->set_url_params(['id' => $flow['id']]);
    $found = $this->controller->show($show);

    expect($created->get_status())->toBe(201)
        ->and($updated->get_data()['data']['version']['version_number'])->toBe(2)
        ->and($published->get_data()['data']['flow']['state'])->toBe('published')
        ->and($submitted->get_data()['data']['completed'])->toBeTrue()
        ->and((int) get_post_meta($submitted->get_data()['data']['submission_id'], 'corex_is_test', true))->toBe(0)
        ->and($tested->get_data()['data']['completed'])->toBeTrue()
        ->and($tested->get_data()['data']['is_test'])->toBeTrue()
        ->and($tested->get_data()['data']['stages'])->toHaveCount(7)
        ->and((int) get_post_meta($tested->get_data()['data']['submission_id'], 'corex_is_test', true))->toBe(1)
        ->and(get_post_meta($tested->get_data()['data']['submission_id'], 'corex_consent_snapshot_json', true))->toBe([])
        ->and(get_post_meta($tested->get_data()['data']['submission_id'], 'corex_utm_json', true))->toBe([])
        ->and(get_post_meta($tested->get_data()['data']['submission_id'], 'corex_spam_json', true)['honeypot'])->toBe('passed')
        ->and(get_post_meta($tested->get_data()['data']['submission_id'], 'corex_routing_json', true)['target_type'])->toBe('flow_owner')
        // Two canonical timeline events now: the submission, plus its notification outcome (spec 071 WS4).
        ->and(get_post_meta($tested->get_data()['data']['submission_id'], 'corex_submission_timeline', true))->toHaveCount(2)
        ->and(get_post_meta($tested->get_data()['data']['submission_id'], 'corex_submission_timeline', true)[1]['stage'])->toBe('notification')
        ->and($found->get_data()['data']['versions'])->toHaveCount(2)
        ->and($found->get_data()['data']['flow']['published_version'])->toBe(2);
});

it('returns a 409 conflict without appending when the draft checksum is stale', function () {
    $created = $this->controller->create(flowRestRequest('POST', '/corex/v1/flows', flowRestPayload()));
    $flowId = $created->get_data()['data']['flow']['id'];
    $payload = flowRestPayload('Stale update');
    $payload['expected_version'] = 1;
    $payload['expected_checksum'] = 'stale';
    $request = flowRestRequest('PATCH', '/corex/v1/flows/' . $flowId, $payload);
    $request->set_url_params(['id' => $flowId]);

    $response = $this->controller->update($request);

    expect($response->get_status())->toBe(409)
        ->and($response->get_data()['code'])->toBe('flow_conflict');
});

it('enforces capability and nonce through declared middleware', function () {
    $invalidNonce = flowRestRequest('POST', '/corex/v1/flows', flowRestPayload());
    $invalidNonce->set_header('X-WP-Nonce', 'invalid');
    $nonceResponse = $this->controller->create($invalidNonce);

    wp_set_current_user(0);
    $readResponse = $this->controller->index(flowRestRequest('GET', '/corex/v1/flows'));

    expect($nonceResponse->get_status())->toBe(403)
        ->and($readResponse->get_status())->toBe(403);
});

it('returns field errors and stores nothing when a published visitor payload is invalid', function () {
    $created = $this->controller->create(flowRestRequest('POST', '/corex/v1/flows', flowRestPayload()));
    $flowId = $created->get_data()['data']['flow']['id'];
    $publish = flowRestRequest('POST', '/corex/v1/flows/' . $flowId . '/publish', ['expected_version' => 1]);
    $publish->set_url_params(['id' => $flowId]);
    $this->controller->publish($publish);
    $before = count(get_posts([
        'post_type' => 'corex_submission',
        'post_status' => 'any',
        'posts_per_page' => 500,
        'fields' => 'ids',
    ]));

    $visitor = flowRestRequest('POST', '/corex/v1/flows/' . $flowId . '/submit', ['email' => 'not-an-email']);
    $visitor->set_url_params(['id' => $flowId]);
    $response = $this->submissionController->submit($visitor);

    expect($response->get_status())->toBe(422)
        ->and($response->get_data()['code'])->toBe('validation_failed')
        // This read 'required' until 2026-10-07 and was the defect, asserted: the answer was
        // emptied by `sanitize_email()` before the rules ran, so the email rule never saw it.
        ->and($response->get_data()['errors']['email'])->toBe('email')
        ->and(count(get_posts([
            'post_type' => 'corex_submission',
            'post_status' => 'any',
            'posts_per_page' => 500,
            'fields' => 'ids',
        ])))->toBe($before);
});

it('rejects an unpublished visitor flow without creating a submission', function () {
    $created = $this->controller->create(flowRestRequest('POST', '/corex/v1/flows', flowRestPayload()));
    $flowId = $created->get_data()['data']['flow']['id'];
    $before = count(get_posts([
        'post_type' => 'corex_submission',
        'post_status' => 'any',
        'posts_per_page' => 500,
        'fields' => 'ids',
    ]));
    $visitor = flowRestRequest('POST', '/corex/v1/flows/' . $flowId . '/submit', ['email' => 'visitor@example.com']);
    $visitor->set_url_params(['id' => $flowId]);

    $response = $this->submissionController->submit($visitor);

    expect($response->get_status())->toBe(404)
        ->and($response->get_data()['code'])->toBe('flow_unavailable')
        ->and(count(get_posts([
            'post_type' => 'corex_submission',
            'post_status' => 'any',
            'posts_per_page' => 500,
            'fields' => 'ids',
        ])))->toBe($before);
});

it('answers the flow list with the flows it can read when one stored flow has no payload', function () {
    $this->controller->create(flowRestRequest('POST', '/corex/v1/flows', flowRestPayload()));
    // A record the list selects, with no payload: what storing a flow left behind when the
    // request stopped after its first write, and what a row edited by hand can still be.
    $damaged = wp_insert_post([
        'post_type' => WpFlowStore::POST_TYPE,
        'post_status' => 'private',
        'post_title' => 'Stopped part-way',
    ]);
    update_post_meta($damaged, '_corex_flow_type', 'flow');
    update_post_meta($damaged, '_corex_flow_slug', 'integration-stopped');

    $listed = $this->controller->index(flowRestRequest('GET', '/corex/v1/flows'));

    $slugs = array_column($listed->get_data()['data']['flows'] ?? [], 'slug');
    $warnings = array_column(Boot::app()->container()->make(BootLogger::class)->messages(), 'message');
    expect($listed->get_status())->toBe(200)
        ->and($slugs)->toContain('integration-flow')
        ->and($slugs)->not->toContain('integration-stopped')
        ->and(implode("\n", $warnings))->toContain(sprintf('Stored flow record %d could not be read', $damaged));
});

it('leaves nothing a list can see when storing a flow stops after its first write', function () {
    // The stand-in for a request that was killed: the first meta row lands, and nothing after it runs.
    $stop = static function (int $metaId, int $postId): void {
        if (get_post_type($postId) === WpFlowStore::POST_TYPE) {
            throw new RuntimeException('The request ended here.');
        }
    };
    add_action('added_post_meta', $stop, 10, 2);
    try {
        $this->store->create('flow', 'integration-interrupted', 'Interrupted', 0, ['state' => 'draft']);
    } catch (RuntimeException) {
        // Expected: that is the interruption.
    } finally {
        remove_action('added_post_meta', $stop, 10);
    }

    expect($this->posts->ids())->toHaveCount(1)
        ->and(array_column($this->store->all('flow'), 'id'))->not->toContain($this->posts->ids()[0]);
});

it('stores no flow at all when its payload cannot be written', function () {
    $refuse = static fn (mixed $check, int $postId, string $key): mixed => $key === '_corex_flow_payload' ? false : $check;
    add_filter('add_post_metadata', $refuse, 10, 3);
    try {
        expect(fn () => $this->store->create('flow', 'integration-unwritten', 'Unwritten', 0, ['state' => 'draft']))
            ->toThrow(DomainException::class);
    } finally {
        remove_filter('add_post_metadata', $refuse, 10);
    }

    expect($this->posts->ids())->toHaveCount(1)
        ->and(get_post($this->posts->ids()[0]))->toBeNull();
});
