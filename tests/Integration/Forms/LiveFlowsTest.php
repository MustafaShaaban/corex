<?php

/**
 * A live flow takes changes without leaving the page, and a flow that is not taking answers says
 * so to visitors (spec 122: US-FLW-01, US-VIS-01, US-VIS-02).
 *
 * @package Corex\Tests\Integration\Forms
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Forms\Block\FlowBlockRenderer;
use Corex\Forms\Flow\FlowController;
use Corex\Forms\Flow\FlowService;
use Corex\Forms\Flow\WpFlowStore;
use Corex\Forms\Submission\FlowSubmissionController;
use Corex\Tests\Support\CreatedPosts;
use Corex\Tests\Support\MailDelivery;
use Corex\Tests\Support\WatchedTransients;

function liveFlowRequest(string $method, string $route, array $payload = [], array $urlParams = []): WP_REST_Request
{
    $request = new WP_REST_Request($method, $route);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    $request->set_header('Content-Type', 'application/json');
    $request->set_body((string) wp_json_encode($payload));
    $request->set_url_params($urlParams);

    return $request;
}

/** A flow asking for one email, published at version 1; its id. */
function publishLiveFlow(string $slug, string $label = 'Email'): int
{
    $controller = Boot::app()->container()->make(FlowController::class);
    $created = $controller->create(liveFlowRequest('POST', '/corex/v1/flows', [
        'slug' => $slug,
        'name' => 'Live flow',
        'owner_id' => get_current_user_id(),
        'configuration' => liveFlowDocument($label),
    ]));
    $flowId = (int) $created->get_data()['data']['flow']['id'];
    $controller->publish(liveFlowRequest('POST', "/corex/v1/flows/{$flowId}/publish", ['expected_version' => 1], ['id' => $flowId]));

    return $flowId;
}

/** @return array<string,mixed> */
function liveFlowDocument(string $label): array
{
    return [
        'schema' => [['uuid' => 'email-field', 'key' => 'email', 'label' => $label, 'type' => 'email', 'required' => true]],
        'validation' => ['email' => ['required', 'email']],
        'routing' => ['rules' => [], 'fallback' => ['type' => 'flow_owner', 'config' => []]],
        'email_routes' => [],
        'success' => ['type' => 'inline', 'message' => 'Received.'],
        'placement_snapshot' => ['type' => 'block'],
    ];
}

function renderLiveFlow(int $flowId, int $pageId = 0): string
{
    return Boot::app()->container()->make(FlowBlockRenderer::class)->render(
        ['flowId' => $flowId, 'variant' => 'flow'],
        '',
        (object) ['context' => ['postId' => $pageId]],
    );
}

/** Sent as a visitor, who cannot read the private posts a submission is stored as. */
function submitLiveFlow(int $flowId, array $values): WP_REST_Response
{
    $signedIn = get_current_user_id();
    wp_set_current_user(0);
    try {
        return Boot::app()->container()->make(FlowSubmissionController::class)->submit(
            liveFlowRequest('POST', "/corex/v1/flows/{$flowId}/submit", $values, ['id' => $flowId]),
        );
    } finally {
        wp_set_current_user($signedIn);
    }
}

function setAvailability(int $flowId, array $availability): WP_REST_Response
{
    return Boot::app()->container()->make(FlowController::class)->availability(
        liveFlowRequest('PATCH', "/corex/v1/flows/{$flowId}/availability", ['availability' => $availability], ['id' => $flowId]),
    );
}

beforeEach(function () {
    Boot::app()->container()->make(WpFlowStore::class)->registerPostType();
    $this->posts = CreatedPosts::watch(WpFlowStore::POST_TYPE, 'corex_submission', 'page');
    $this->transients = WatchedTransients::watch('corex_throttle_');
    $this->mail = MailDelivery::answerAs(MailDelivery::CAPTURE);
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));
});

afterEach(function () {
    MailDelivery::stop($this->mail);
    $this->posts->delete();
    $this->transients->restore();
    wp_set_current_user(0);
});

it('publishes changes to a live flow in one step: the page shows the form before, during and after', function () {
    $flowId = publishLiveFlow('live-publish');
    $controller = Boot::app()->container()->make(FlowController::class);
    $detail = $controller->show(liveFlowRequest('GET', "/corex/v1/flows/{$flowId}", [], ['id' => $flowId]))->get_data()['data'];
    $before = renderLiveFlow($flowId);

    $controller->update(liveFlowRequest('PATCH', "/corex/v1/flows/{$flowId}", [
        'expected_version' => 1,
        'expected_checksum' => $detail['versions'][0]['checksum'],
        'configuration' => liveFlowDocument('Work email'),
    ], ['id' => $flowId]));
    $between = renderLiveFlow($flowId);
    $published = $controller->publish(liveFlowRequest('POST', "/corex/v1/flows/{$flowId}/publish", ['expected_version' => 2], ['id' => $flowId]));
    $after = renderLiveFlow($flowId);
    $flow = $published->get_data()['data']['flow'];

    expect($before)->toContain('>Email <span')->toContain('name="corex_flow_version" value="1"')
        ->and($between)->toContain('>Email <span')
        ->and($published->get_status())->toBe(200)
        ->and($flow['state'])->toBe('published')
        ->and($flow['published_version'])->toBe(2)
        ->and(array_column($flow['publications'], 'version'))->toBe([2, 1])
        ->and($flow['publications'][0]['by_name'])->toBe(wp_get_current_user()->display_name)
        ->and($after)->toContain('>Work email <span')->toContain('name="corex_flow_version" value="2"');
});

it('accepts a page loaded before the change and records the version the visitor saw', function () {
    $flowId = publishLiveFlow('live-old-page');
    $service = Boot::app()->container()->make(FlowService::class);
    $versionOne = $service->versions($flowId)[0];
    $controller = Boot::app()->container()->make(FlowController::class);
    $controller->update(liveFlowRequest('PATCH', "/corex/v1/flows/{$flowId}", [
        'expected_version' => 1,
        'expected_checksum' => $versionOne->checksum,
        'configuration' => liveFlowDocument('Work email'),
    ], ['id' => $flowId]));
    $controller->publish(liveFlowRequest('POST', "/corex/v1/flows/{$flowId}/publish", ['expected_version' => 2], ['id' => $flowId]));

    $response = submitLiveFlow($flowId, ['email' => 'visitor@example.test', 'corex_flow_version' => 1]);
    $submissionId = (int) $response->get_data()['data']['submission_id'];

    expect($response->get_status())->toBe(200)
        ->and((int) get_post_meta($submissionId, 'corex_flow_version_id', true))->toBe($versionOne->id);
});

it('refuses to publish from an editor whose draft is out of date', function () {
    $flowId = publishLiveFlow('live-stale');
    $service = Boot::app()->container()->make(FlowService::class);
    $controller = Boot::app()->container()->make(FlowController::class);
    $controller->update(liveFlowRequest('PATCH', "/corex/v1/flows/{$flowId}", [
        'expected_version' => 1,
        'expected_checksum' => $service->versions($flowId)[0]->checksum,
        'configuration' => liveFlowDocument('Changed by someone else'),
    ], ['id' => $flowId]));

    $response = $controller->publish(liveFlowRequest('POST', "/corex/v1/flows/{$flowId}/publish", ['expected_version' => 1], ['id' => $flowId]));

    expect($response->get_status())->toBe(409)
        ->and($response->get_data()['message'])->toBe('This flow changed since you opened it. Reload to see the latest draft.')
        ->and($service->get($flowId)->publishedVersion)->toBe(1);
});

it('copies an older version into the draft and leaves the live version alone', function () {
    $flowId = publishLiveFlow('live-copy');
    $service = Boot::app()->container()->make(FlowService::class);
    $controller = Boot::app()->container()->make(FlowController::class);
    $controller->update(liveFlowRequest('PATCH', "/corex/v1/flows/{$flowId}", [
        'expected_version' => 1,
        'expected_checksum' => $service->versions($flowId)[0]->checksum,
        'configuration' => liveFlowDocument('Work email'),
    ], ['id' => $flowId]));
    $controller->publish(liveFlowRequest('POST', "/corex/v1/flows/{$flowId}/publish", ['expected_version' => 2], ['id' => $flowId]));

    $copied = $controller->copyVersion(liveFlowRequest('POST', "/corex/v1/flows/{$flowId}/versions/1/copy", [
        'expected_version' => 2,
        'expected_checksum' => $service->versions($flowId)[1]->checksum,
    ], ['id' => $flowId, 'version' => 1]));

    expect($copied->get_status())->toBe(200)
        ->and($copied->get_data()['data']['version']['version_number'])->toBe(3)
        ->and($copied->get_data()['data']['version']['configuration']['schema'][0]['label'])->toBe('Email')
        ->and($copied->get_data()['data']['flow']['published_version'])->toBe(2);
});

it('shows a closed flow\'s heading, the closed message and the alternative, and refuses a late submission with them', function () {
    $flowId = publishLiveFlow('live-closed');
    $form = renderLiveFlow($flowId);
    setAvailability($flowId, [
        'closed_message' => 'Applications reopen in March',
        'alternative' => ['type' => 'email', 'label' => 'Email us', 'email' => 'jobs@example.test'],
    ]);
    $controller = Boot::app()->container()->make(FlowController::class);
    $controller->close(liveFlowRequest('POST', "/corex/v1/flows/{$flowId}/close", ['expected_version' => 1], ['id' => $flowId]));
    wp_set_current_user(0);

    $closed = renderLiveFlow($flowId);
    $late = submitLiveFlow($flowId, ['email' => 'late@example.test', 'corex_flow_version' => 1]);

    expect($form)->toContain('<form')
        ->and($closed)->not->toContain('<form')
        ->toContain('<h2 class="corex-flow__title">Live flow</h2>')
        ->toContain('corex-flow-notice__title">Closed<')
        ->toContain('Applications reopen in March')
        ->toContain('href="mailto:jobs@example.test">Email us</a>')
        ->not->toContain('Only editors see this')
        ->and($late->get_status())->toBe(409)
        ->and($late->get_data()['code'])->toBe('flow_closed')
        ->and($late->get_data()['details']['notice'])->toBe([
            'title' => 'Closed',
            'message' => 'Applications reopen in March',
            'alternative' => ['type' => 'email', 'label' => 'Email us', 'url' => 'mailto:jobs@example.test'],
        ]);
});

it('says when a scheduled flow opens, refuses a submission before then, and opens again when the date is cleared', function () {
    $flowId = publishLiveFlow('live-scheduled');
    $opens = (new DateTimeImmutable('+2 days', wp_timezone()))->setTime(9, 0);
    setAvailability($flowId, ['opens_at' => $opens->format('Y-m-d\TH:i')]);
    $flow = Boot::app()->container()->make(FlowService::class)->get($flowId);

    $scheduled = renderLiveFlow($flowId);
    $refused = submitLiveFlow($flowId, ['email' => 'early@example.test']);
    setAvailability($flowId, ['opens_at' => '']);
    $open = renderLiveFlow($flowId);

    expect($flow->availability->opensAt?->getTimestamp())->toBe($opens->getTimestamp())
        ->and($scheduled)->toContain(esc_html(sprintf('This form opens on %s at %s.', wp_date(get_option('date_format'), $opens->getTimestamp()), wp_date(get_option('time_format'), $opens->getTimestamp()))))
        ->and($refused->get_status())->toBe(409)
        ->and($open)->toContain('<form');
});

it('expires a flow from its close date and lists it as expired with the reason', function () {
    $flowId = publishLiveFlow('live-expired');
    setAvailability($flowId, ['closes_at' => (new DateTimeImmutable('-1 minute', wp_timezone()))->format('Y-m-d\TH:i')]);
    $controller = Boot::app()->container()->make(FlowController::class);

    $listed = $controller->index(liveFlowRequest('GET', '/corex/v1/flows', [], []));
    $request = new WP_REST_Request('GET', '/corex/v1/flows');
    $request->set_param('state', 'expired');
    $expired = $controller->index($request);
    $row = array_values(array_filter($listed->get_data()['data']['flows'], static fn (array $flow): bool => $flow['id'] === $flowId))[0];

    expect($row['status']['state'])->toBe('expired')
        ->and($row['status']['label'])->toStartWith('Closed on ')
        ->and(array_column($expired->get_data()['data']['flows'], 'id'))->toContain($flowId)
        ->and(renderLiveFlow($flowId))->toContain('This form is closed.');
});

it('counts responses up to the cap, marked tests left out', function () {
    $flowId = publishLiveFlow('live-capped');
    setAvailability($flowId, ['max_responses' => 2]);
    Boot::app()->container()->make(FlowController::class)->test(liveFlowRequest('POST', "/corex/v1/flows/{$flowId}/test", ['expected_version' => 1], ['id' => $flowId]));

    $first = submitLiveFlow($flowId, ['email' => 'one@example.test']);
    $second = submitLiveFlow($flowId, ['email' => 'two@example.test']);
    $third = submitLiveFlow($flowId, ['email' => 'three@example.test']);

    expect([$first->get_status(), $second->get_status(), $third->get_status()])->toBe([200, 200, 409])
        ->and($third->get_data()['message'])->toBe('This form is closed.');
});

it('refuses an availability whose dates are out of order', function () {
    $flowId = publishLiveFlow('live-bad-dates');

    $response = setAvailability($flowId, ['opens_at' => '2026-11-02T09:00', 'closes_at' => '2026-11-01T09:00']);

    expect($response->get_status())->toBe(422)
        ->and($response->get_data()['message'])->toBe('The closing date must be after the opening date.');
});

it('shows a draft flow to nobody but a signed-in editor, who is told it is not published', function () {
    $controller = Boot::app()->container()->make(FlowController::class);
    $created = $controller->create(liveFlowRequest('POST', '/corex/v1/flows', [
        'slug' => 'live-draft',
        'name' => 'Draft flow',
        'owner_id' => get_current_user_id(),
        'configuration' => liveFlowDocument('Email'),
    ]));
    $flowId = (int) $created->get_data()['data']['flow']['id'];

    $editor = renderLiveFlow($flowId);
    wp_set_current_user(0);
    $visitor = renderLiveFlow($flowId);

    expect($editor)->toContain('Only editors see this: the form in this block is not published.')
        ->toContain('page=corex-forms&#038;corex_flow=' . $flowId)
        ->and($visitor)->toBe('');
});

it('carries the signed page the form was printed on', function () {
    $flowId = publishLiveFlow('live-page');
    $pageId = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Apply']);

    expect(renderLiveFlow($flowId, $pageId))->toMatch('/name="corex_page" value="' . $pageId . '\.[a-f0-9]{16}"/');
});
