<?php

/**
 * Integration tests for activity persistence and REST reads (spec 068: FR-005, FR-018, FR-019).
 *
 * @package Corex\Tests\Integration\Activity
 */

declare(strict_types=1);

use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;
use Corex\Config\Activity\ActivityController;
use Corex\Config\Activity\ActivityTable;
use Corex\Config\Activity\WpActivityRepository;
use Corex\Database\Schema\Migrator;

const ACTIVITY_TEST_UUIDS = [
    '9d29afdf-c17e-4867-b9a4-e7a214916021',
    'bd15db5d-3936-4a9f-996b-6ed16cbabf63',
];

beforeEach(function () {
    global $wpdb;

    $this->migrator = new Migrator();
    $this->migrator->create((new ActivityTable())->schema());
    $this->repository = new WpActivityRepository($this->migrator);
    $this->service    = new ActivityService($this->repository);
    $this->controller = new ActivityController($this->service);

    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));

    foreach (ACTIVITY_TEST_UUIDS as $uuid) {
        $wpdb->delete($this->migrator->fullName(ActivityTable::NAME), ['event_uuid' => $uuid]);
    }
});

afterEach(function () {
    global $wpdb;

    foreach (ACTIVITY_TEST_UUIDS as $uuid) {
        $wpdb->delete($this->migrator->fullName(ActivityTable::NAME), ['event_uuid' => $uuid]);
    }
});

function recordActivityFixture(ActivityService $service, string $uuid, string $area): int
{
    return $service->record(
        actorId: get_current_user_id(),
        actorKind: ActivityEvent::ACTOR_USER,
        actorLabel: 'Integration administrator',
        area: $area,
        kind: 'integration.checked',
        targetType: 'module',
        targetId: $area,
        targetLabel: ucfirst($area),
        outcome: ActivityEvent::OUTCOME_SUCCESS,
        summary: ['key' => 'integration.checked', 'args' => ['area' => $area]],
        context: ['source' => 'integration'],
        sensitivity: ActivityEvent::SENSITIVITY_RESTRICTED,
        retentionUntil: new DateTimeImmutable('+1 day'),
        eventUuid: $uuid,
    )->id;
}

it('registers activity collection and detail routes', function () {
    add_action('rest_api_init', [$this->controller, 'register']);
    do_action('rest_api_init', rest_get_server());
    $routes = rest_get_server()->get_routes();

    expect($routes)->toHaveKey('/corex/v1/activity')
        ->and($routes)->toHaveKey('/corex/v1/activity/(?P<id>\d+)');
});

it('returns only activity matching the requested filters', function () {
    recordActivityFixture($this->service, ACTIVITY_TEST_UUIDS[0], ActivityEvent::AREA_FORMS);
    recordActivityFixture($this->service, ACTIVITY_TEST_UUIDS[1], ActivityEvent::AREA_EMAIL);

    $request = new WP_REST_Request('GET', '/corex/v1/activity');
    $request->set_param('area', ActivityEvent::AREA_EMAIL);
    $response = $this->controller->index($request);
    $body     = $response->get_data();

    expect($response->get_status())->toBe(200)
        ->and($body['ok'])->toBeTrue()
        ->and($body['data']['events'])->toHaveCount(1)
        ->and($body['data']['events'][0]['area'])->toBe(ActivityEvent::AREA_EMAIL)
        ->and($body['data']['events'][0]['context_json'])->toBe(['source' => 'integration']);
});

it('returns one persisted event and a truthful not-found envelope', function () {
    $id = recordActivityFixture($this->service, ACTIVITY_TEST_UUIDS[0], ActivityEvent::AREA_SECURITY);

    $foundRequest = new WP_REST_Request('GET', '/corex/v1/activity/' . $id);
    $foundRequest->set_param('id', $id);
    $found = $this->controller->show($foundRequest);

    $missingRequest = new WP_REST_Request('GET', '/corex/v1/activity/999999999');
    $missingRequest->set_param('id', 999999999);
    $missing = $this->controller->show($missingRequest);

    expect($found->get_status())->toBe(200)
        ->and($found->get_data()['data']['event']['id'])->toBe($id)
        ->and($missing->get_status())->toBe(404)
        ->and($missing->get_data()['code'])->toBe('activity_not_found');
});

it('denies activity reads without an administrator capability', function () {
    wp_set_current_user(0);

    expect($this->controller->canManage())->toBeFalse();
});
