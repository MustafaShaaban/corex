<?php

/**
 * Routing assigns each submission to a person, a team or a role, and they see it without managing
 * every submission (spec 122: US-FLW-03).
 *
 * @package Corex\Tests\Integration\Forms
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Submissions\SubmissionsController;
use Corex\Forms\Flow\FlowController;
use Corex\Forms\Flow\WpFlowStore;
use Corex\Forms\Routing\FlowOwnerUpgrade;
use Corex\Forms\Routing\Teams\TeamController;
use Corex\Forms\Routing\Teams\WpTeamStore;
use Corex\Forms\Submission\FlowSubmissionController;
use Corex\Tests\Support\CreatedPosts;
use Corex\Tests\Support\MailDelivery;
use Corex\Tests\Support\OptionRow;
use Corex\Tests\Support\WatchedTransients;

function routedRequest(string $method, string $route, array $payload = [], array $urlParams = []): WP_REST_Request
{
    $request = new WP_REST_Request($method, $route);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    $request->set_header('Content-Type', 'application/json');
    $request->set_body((string) wp_json_encode($payload));
    $request->set_url_params($urlParams);

    return $request;
}

/** A published flow with a department question, owned by `$ownerId`; its id. */
function publishRoutedFlow(string $slug, int $ownerId, array $routing): int
{
    $controller = Boot::app()->container()->make(FlowController::class);
    $created = $controller->create(routedRequest('POST', '/corex/v1/flows', [
        'slug' => $slug,
        'name' => 'Routed flow',
        'owner_id' => $ownerId,
        'configuration' => [
            'schema' => [
                ['uuid' => 'f-email', 'key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                ['uuid' => 'f-dept', 'key' => 'department', 'label' => 'Department', 'type' => 'select', 'options' => [
                    ['value' => 'Sales', 'label' => 'Sales'], ['value' => 'Other', 'label' => 'Other'],
                ]],
            ],
            'validation' => ['email' => ['required', 'email']],
            'routing' => $routing,
            'email_routes' => [],
            'success' => ['type' => 'inline', 'message' => 'Received.'],
            'placement_snapshot' => ['type' => 'block'],
        ],
    ]));
    $flowId = (int) $created->get_data()['data']['flow']['id'];
    $published = $controller->publish(routedRequest('POST', "/corex/v1/flows/{$flowId}/publish", ['expected_version' => 1], ['id' => $flowId]));
    expect($published->get_status())->toBe(200);

    return $flowId;
}

function submitRouted(int $flowId, array $values): int
{
    $previous = get_current_user_id();
    wp_set_current_user(0);
    $response = Boot::app()->container()->make(FlowSubmissionController::class)->submit(
        routedRequest('POST', "/corex/v1/flows/{$flowId}/submit", $values, ['id' => $flowId]),
    );
    wp_set_current_user($previous);
    expect($response->get_status())->toBe(200);

    return (int) $response->get_data()['data']['submission_id'];
}

function canOpenSubmission(int $userId, int $submissionId): bool
{
    wp_set_current_user($userId);
    $response = Boot::app()->container()->make(SubmissionsController::class)->show(
        routedRequest('GET', "/corex/v1/submissions/{$submissionId}", [], ['id' => $submissionId]),
    );

    return $response->get_status() === 200;
}

beforeEach(function () {
    Boot::app()->container()->make(WpFlowStore::class)->registerPostType();
    $this->posts = CreatedPosts::watch(WpFlowStore::POST_TYPE, 'corex_submission');
    $this->transients = WatchedTransients::watch('corex_throttle_');
    $this->mail = MailDelivery::answerAs(MailDelivery::CAPTURE);
    $this->teams = OptionRow::capture(WpTeamStore::OPTION);
    $this->upgrade = OptionRow::capture(FlowOwnerUpgrade::OPTION);
    $this->users = [];
    foreach (['sara', 'omar', 'lina'] as $name) {
        $this->users[$name] = wp_insert_user([
            'user_login' => 'routed-' . $name . '-' . wp_generate_password(6, false),
            'user_pass' => wp_generate_password(),
            'user_email' => $name . '-' . wp_generate_password(6, false) . '@example.test',
            'display_name' => ucfirst($name),
            'role' => 'author',
        ]);
    }
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    $this->admin = (int) ($administrators[0] ?? 0);
    wp_set_current_user($this->admin);
});

afterEach(function () {
    wp_set_current_user($this->admin);
    MailDelivery::stop($this->mail);
    $this->posts->delete();
    $this->transients->restore();
    OptionRow::restore(WpTeamStore::OPTION, $this->teams);
    OptionRow::restore(FlowOwnerUpgrade::OPTION, $this->upgrade);
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($this->users as $userId) {
        wp_delete_user($userId);
    }
});

it('assigns a submission to the flow owner, who sees it without managing all submissions', function () {
    $flowId = publishRoutedFlow('routed-owner', $this->users['sara'], ['rules' => [], 'fallback' => ['type' => 'flow_owner', 'config' => []]]);

    $submissionId = submitRouted($flowId, ['email' => 'visitor@example.test']);

    expect(get_post_meta($submissionId, 'corex_owner_type', true))->toBe('user')
        ->and(get_post_meta($submissionId, 'corex_owner_key', true))->toBe((string) $this->users['sara'])
        ->and(canOpenSubmission($this->users['sara'], $submissionId))->toBeTrue()
        ->and(canOpenSubmission($this->users['omar'], $submissionId))->toBeFalse();
});

it('routes to a team made on the Teams view, and only its members see the submission', function () {
    $team = Boot::app()->container()->make(TeamController::class)->create(routedRequest('POST', '/corex/v1/teams', [
        'name' => 'Sales',
        'members' => [$this->users['omar']],
    ]));
    $key = $team->get_data()['data']['team']['key'];
    $flowId = publishRoutedFlow('routed-team', $this->users['sara'], [
        'rules' => [[
            'uuid' => 'rule-sales',
            'position' => 10,
            'condition' => ['field' => 'department', 'operator' => 'equals', 'value' => 'Sales'],
            'target' => ['type' => 'team', 'config' => ['value' => $key]],
        ]],
        'fallback' => ['type' => 'flow_owner', 'config' => []],
    ]);

    $submissionId = submitRouted($flowId, ['email' => 'visitor@example.test', 'department' => 'Sales']);

    expect($team->get_status())->toBe(200)
        ->and(get_post_meta($submissionId, 'corex_owner_type', true))->toBe('team')
        ->and(canOpenSubmission($this->users['omar'], $submissionId))->toBeTrue()
        ->and(canOpenSubmission($this->users['sara'], $submissionId))->toBeFalse()
        ->and(canOpenSubmission($this->users['lina'], $submissionId))->toBeFalse();
});

it('takes the fallback when the picked value has no owner and says so on the timeline', function () {
    $flowId = publishRoutedFlow('routed-value', $this->users['sara'], [
        'rules' => [],
        'fallback' => ['type' => 'field_value', 'config' => ['value' => 'department', 'owners' => [
            ['value' => 'Sales', 'target' => ['type' => 'user', 'value' => (string) $this->users['omar']]],
        ]]],
    ]);

    $submissionId = submitRouted($flowId, ['email' => 'visitor@example.test', 'department' => 'Other']);
    $timeline = get_post_meta($submissionId, 'corex_submission_timeline', true);
    $routing = array_values(array_filter($timeline, static fn (array $event): bool => $event['stage'] === 'routing'))[0];

    expect(get_post_meta($submissionId, 'corex_owner_key', true))->toBe((string) $this->users['sara'])
        ->and($routing['summary']['message'])->toBe('The answer "Other" to "department" has no owner. Assigned to Sara instead.');
});

it('gives old flow submissions assigned to nobody to their flow owner, once, and counts them', function () {
    $flowId = publishRoutedFlow('routed-upgrade', $this->users['lina'], ['rules' => [], 'fallback' => ['type' => 'flow_owner', 'config' => []]]);
    $submissionId = submitRouted($flowId, ['email' => 'visitor@example.test']);
    // As every flow submission was stored before this release.
    update_post_meta($submissionId, 'corex_owner_type', 'flow_owner');
    update_post_meta($submissionId, 'corex_owner_key', '');
    delete_option(FlowOwnerUpgrade::OPTION);
    $upgrade = Boot::app()->container()->make(FlowOwnerUpgrade::class);

    $upgrade->runOnce();
    $recorded = get_option(FlowOwnerUpgrade::OPTION);
    $owner = [get_post_meta($submissionId, 'corex_owner_type', true), get_post_meta($submissionId, 'corex_owner_key', true)];
    update_post_meta($submissionId, 'corex_owner_type', 'flow_owner');
    $upgrade->runOnce();

    expect($recorded['moved'])->toBeGreaterThanOrEqual(1)
        ->and($owner)->toBe(['user', (string) $this->users['lina']])
        ->and(get_post($submissionId))->not->toBeNull()
        ->and(get_post_meta($submissionId, 'corex_owner_type', true))->toBe('flow_owner')
        ->and(get_option(FlowOwnerUpgrade::OPTION))->toBe($recorded);
});

it('names the fallback in words in the flow list', function () {
    $flowId = publishRoutedFlow('routed-words', $this->users['sara'], ['rules' => [], 'fallback' => ['type' => 'flow_owner', 'config' => []]]);

    $flows = Boot::app()->container()->make(FlowController::class)->index(routedRequest('GET', '/corex/v1/flows'))->get_data()['data']['flows'];
    $row = array_values(array_filter($flows, static fn (array $flow): bool => $flow['id'] === $flowId))[0];

    expect($row['routing_owner'])->toBe('Form owner (Sara)');
});
