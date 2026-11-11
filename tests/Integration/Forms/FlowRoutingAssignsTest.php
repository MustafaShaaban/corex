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

/** A submission as a form defined in code stores it: no flow, assigned in the inbox to `$owner`. */
function storeCodeFormSubmission(string $ownerType, string $ownerKey): int
{
    $id = wp_insert_post(['post_type' => 'corex_submission', 'post_status' => 'private', 'post_title' => 'Code form submission']);
    update_post_meta($id, 'corex_form_slug', 'contact');
    update_post_meta($id, 'corex_owner_type', $ownerType);
    update_post_meta($id, 'corex_owner_key', $ownerKey);

    return (int) $id;
}

/**
 * The capability the Submissions menu entry asks, as WordPress registers it for this person on
 * `admin_menu`; null when WordPress keeps the entry from them.
 */
function submissionsMenuFor(int $userId): ?string
{
    global $submenu, $_wp_submenu_nopriv;
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $kept = [$submenu ?? null, $_wp_submenu_nopriv ?? null];
    $submenu = [];
    $_wp_submenu_nopriv = [];
    wp_set_current_user($userId);

    try {
        Boot::app()->container()->make(\Corex\Config\Submissions\SubmissionsInboxScreen::class)->menu();
        foreach ($submenu['corex-settings'] ?? [] as $entry) {
            if (($entry[2] ?? '') === 'corex-submissions') {
                return (string) $entry[1];
            }
        }

        return null;
    } finally {
        [$submenu, $_wp_submenu_nopriv] = $kept;
    }
}

/** @return list<int> The submissions the person's inbox lists. */
function inboxListFor(int $userId): array
{
    wp_set_current_user($userId);
    $response = Boot::app()->container()->make(SubmissionsController::class)->index(routedRequest('GET', '/corex/v1/submissions'));

    return $response->get_status() === 200
        ? array_map(static fn (array $item): int => (int) $item['id'], $response->get_data()['data']['items'])
        : [];
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
    wp_clear_scheduled_hook(FlowOwnerUpgrade::CRON_HOOK);
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

    while (! $upgrade->step()) {
        // One batch a call, as one admin request or one cron run does.
    }
    $recorded = get_option(FlowOwnerUpgrade::OPTION);
    $owner = [get_post_meta($submissionId, 'corex_owner_type', true), get_post_meta($submissionId, 'corex_owner_key', true)];
    update_post_meta($submissionId, 'corex_owner_type', 'flow_owner');
    $upgrade->step();

    expect($recorded['moved'])->toBeGreaterThanOrEqual(1)
        ->and($recorded['ran_at'])->toBeString()
        ->and($owner)->toBe(['user', (string) $this->users['lina']])
        ->and(get_post($submissionId))->not->toBeNull()
        ->and(get_post_meta($submissionId, 'corex_owner_type', true))->toBe('flow_owner')
        ->and(get_option(FlowOwnerUpgrade::OPTION))->toBe($recorded);
});

it('moves old submissions in batches and records the upgrade as done only after the last one (FR-014)', function () {
    $flowId = publishRoutedFlow('routed-upgrade-batches', $this->users['lina'], ['rules' => [], 'fallback' => ['type' => 'flow_owner', 'config' => []]]);
    $ids = [];
    foreach (['a', 'b', 'c'] as $name) {
        $ids[] = $id = submitRouted($flowId, ['email' => $name . '@example.test']);
        update_post_meta($id, 'corex_owner_type', 'page_owner');
        update_post_meta($id, 'corex_owner_key', '');
    }
    // The test's three are the only ones: a submission another test left would change the batches.
    expect(array_values(array_diff(Boot::app()->container()->make(\Corex\Forms\Submission\SubmissionRepository::class)->withUnresolvedOwner(), $ids)))->toBe([]);
    delete_option(FlowOwnerUpgrade::OPTION);
    $upgrade = new FlowOwnerUpgrade(
        Boot::app()->container()->make(\Corex\Forms\Submission\SubmissionRepository::class),
        Boot::app()->container()->make(\Corex\Forms\Flow\FlowRepository::class),
        Boot::app()->container()->make(\Corex\Forms\Routing\RoutingDirectory::class),
        batchSize: 2,
    );

    $first = $upgrade->step();
    $halfway = get_option(FlowOwnerUpgrade::OPTION);
    $scheduled = wp_next_scheduled(FlowOwnerUpgrade::CRON_HOOK);
    // A request that died after moving the third one, before it could say so.
    update_post_meta($ids[2], 'corex_owner_type', 'user');
    update_post_meta($ids[2], 'corex_owner_key', (string) $this->users['lina']);
    $second = $upgrade->step();
    $timeline = (array) get_post_meta($ids[2], 'corex_submission_timeline', true);

    expect($first)->toBeFalse()
        ->and($halfway)->not->toHaveKey('ran_at')
        ->and($halfway['moved'])->toBe(2)
        ->and($scheduled)->not->toBeFalse()
        ->and($second)->toBeTrue()
        ->and(get_option(FlowOwnerUpgrade::OPTION)['moved'])->toBe(2)
        ->and(get_option(FlowOwnerUpgrade::OPTION)['ran_at'])->toBeString()
        ->and(wp_next_scheduled(FlowOwnerUpgrade::CRON_HOOK))->toBeFalse()
        ->and(array_filter($timeline, static fn (array $event): bool => $event['stage'] === 'routing' && str_contains((string) ($event['summary']['message'] ?? ''), 'before this update')))->toBe([]);
});

it('runs the upgrade from a signed-in admin request or cron, never from a visitor request (FR-014)', function () {
    $flowId = publishRoutedFlow('routed-upgrade-visitor', $this->users['lina'], ['rules' => [], 'fallback' => ['type' => 'flow_owner', 'config' => []]]);
    $submissionId = submitRouted($flowId, ['email' => 'visitor@example.test']);
    update_post_meta($submissionId, 'corex_owner_type', 'flow_owner');
    delete_option(FlowOwnerUpgrade::OPTION);
    $upgrade = Boot::app()->container()->make(FlowOwnerUpgrade::class);

    wp_set_current_user(0);
    $upgrade->stepFromAdmin();
    $asVisitor = [get_option(FlowOwnerUpgrade::OPTION), get_post_meta($submissionId, 'corex_owner_type', true)];
    wp_set_current_user($this->admin);
    $upgrade->stepFromAdmin();

    expect(has_action('init', [$upgrade, 'step']))->toBeFalse()
        ->and(has_action('admin_init', [$upgrade, 'stepFromAdmin']))->not->toBeFalse()
        ->and(has_action(FlowOwnerUpgrade::CRON_HOOK, [$upgrade, 'step']))->not->toBeFalse()
        ->and($asVisitor)->toBe([false, 'flow_owner'])
        ->and(get_post_meta($submissionId, 'corex_owner_type', true))->toBe('user');
});

it('runs nothing under a lock another request holds, and names its locks for this site (FR-014, FR-030)', function () {
    global $wpdb;
    $repository = Boot::app()->container()->make(\Corex\Forms\Submission\SubmissionRepository::class);
    $other = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    $other->query("SELECT GET_LOCK('{$wpdb->prefix}corex_test_lock', 0)");
    // The same name without this site's prefix: another site's lock on the same database server.
    $other->query("SELECT GET_LOCK('corex_other_lock', 0)");
    $ran = [];

    try {
        $busy = $repository->unlessBusy('corex_test_lock', static function () use (&$ran): void {
            $ran[] = 'held';
        });
        $free = $repository->unlessBusy('corex_other_lock', static function () use (&$ran): void {
            $ran[] = 'free';
        });
    } finally {
        $other->close();
    }

    expect($busy)->toBeFalse()
        ->and($free)->toBeTrue()
        ->and($ran)->toBe(['free']);
});

it('schedules the next step when another request is moving a batch, and moves nothing (FR-014)', function () {
    global $wpdb;
    $flowId = publishRoutedFlow('routed-upgrade-busy', $this->users['lina'], ['rules' => [], 'fallback' => ['type' => 'flow_owner', 'config' => []]]);
    $submissionId = submitRouted($flowId, ['email' => 'visitor@example.test']);
    update_post_meta($submissionId, 'corex_owner_type', 'flow_owner');
    delete_option(FlowOwnerUpgrade::OPTION);
    wp_clear_scheduled_hook(FlowOwnerUpgrade::CRON_HOOK);
    $other = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    $other->query("SELECT GET_LOCK('{$wpdb->prefix}corex_flow_owner_upgrade', 0)");

    try {
        $done = Boot::app()->container()->make(FlowOwnerUpgrade::class)->step();
    } finally {
        $other->close();
    }

    expect($done)->toBeFalse()
        ->and(wp_next_scheduled(FlowOwnerUpgrade::CRON_HOOK))->not->toBeFalse()
        ->and(get_option(FlowOwnerUpgrade::OPTION))->toBeFalse()
        ->and(get_post_meta($submissionId, 'corex_owner_type', true))->toBe('flow_owner');
});

it('does not run the upgrade from admin-ajax, which a visitor can reach (FR-014)', function () {
    $flowId = publishRoutedFlow('routed-upgrade-ajax', $this->users['lina'], ['rules' => [], 'fallback' => ['type' => 'flow_owner', 'config' => []]]);
    $submissionId = submitRouted($flowId, ['email' => 'visitor@example.test']);
    update_post_meta($submissionId, 'corex_owner_type', 'flow_owner');
    delete_option(FlowOwnerUpgrade::OPTION);
    add_filter('wp_doing_ajax', '__return_true');

    try {
        Boot::app()->container()->make(FlowOwnerUpgrade::class)->stepFromAdmin();
    } finally {
        remove_filter('wp_doing_ajax', '__return_true');
    }

    expect(get_option(FlowOwnerUpgrade::OPTION))->toBeFalse()
        ->and(get_post_meta($submissionId, 'corex_owner_type', true))->toBe('flow_owner');
});

it('opens no inbox for someone only ever assigned a submission outside a flow (FR-013)', function () {
    $submissionId = storeCodeFormSubmission('user', (string) $this->users['omar']);
    $teamOnCode = storeCodeFormSubmission('role', 'author');

    $menu = submissionsMenuFor($this->users['omar']);
    wp_set_current_user($this->users['omar']);
    $scope = Boot::app()->container()->make(\Corex\Config\Submissions\SubmissionAccessPolicy::class)->scopeFor($this->users['omar']);

    expect($scope)->toBeNull()
        ->and($menu)->toBeNull()
        ->and(canOpenSubmission($this->users['omar'], $submissionId))->toBeFalse()
        ->and(canOpenSubmission($this->users['omar'], $teamOnCode))->toBeFalse();
});

it('shows an assigned-only person their flow work and never a non-flow submission assigned to them (FR-013)', function () {
    $flowId = publishRoutedFlow('routed-scope', $this->users['sara'], ['rules' => [], 'fallback' => ['type' => 'user', 'config' => ['value' => (string) $this->users['omar']]]]);
    $flowWork = submitRouted($flowId, ['email' => 'visitor@example.test']);
    $codeWork = storeCodeFormSubmission('user', (string) $this->users['omar']);

    $listed = inboxListFor($this->users['omar']);

    expect($listed)->toContain($flowWork)
        ->and($listed)->not->toContain($codeWork)
        ->and(canOpenSubmission($this->users['omar'], $flowWork))->toBeTrue()
        ->and(canOpenSubmission($this->users['omar'], $codeWork))->toBeFalse();
});

it('sees what is assigned to their role, and the Submissions menu asks only to be signed in (FR-013)', function () {
    $flowId = publishRoutedFlow('routed-role', $this->users['sara'], ['rules' => [], 'fallback' => ['type' => 'role', 'config' => ['value' => 'author']]]);
    $submissionId = submitRouted($flowId, ['email' => 'visitor@example.test']);

    $menu = submissionsMenuFor($this->users['lina']);

    expect(get_post_meta($submissionId, 'corex_owner_type', true))->toBe('role')
        ->and($menu)->toBe('read')
        ->and(submissionsMenuFor($this->admin))->toBe(\Corex\Access\CorexAbility::MANAGE_SUBMISSIONS)
        ->and(inboxListFor($this->users['lina']))->toContain($submissionId)
        ->and(canOpenSubmission($this->users['lina'], $submissionId))->toBeTrue();
});

it('lists, for an assigned-only person, only the flows they have work in (FR-013)', function () {
    $theirs = publishRoutedFlow('routed-names-theirs', $this->users['sara'], ['rules' => [], 'fallback' => ['type' => 'user', 'config' => ['value' => (string) $this->users['omar']]]]);
    $other = publishRoutedFlow('routed-names-other', $this->users['sara'], ['rules' => [], 'fallback' => ['type' => 'flow_owner', 'config' => []]]);
    submitRouted($theirs, ['email' => 'visitor@example.test']);
    submitRouted($other, ['email' => 'visitor@example.test']);

    wp_set_current_user($this->users['omar']);
    // The catalog remembers the forms for the rest of the request: this one has made two more.
    $catalog = Boot::app()->container()->make(\Corex\Forms\Catalog\FormCatalog::class);
    (new ReflectionProperty($catalog, 'resolved'))->setValue($catalog, null);
    $named = array_column(Boot::app()->container()->make(\Corex\Config\Submissions\SubmissionsInboxScreen::class)->configuration()['flows'], 'id');
    wp_set_current_user($this->admin);
    $managed = array_column(Boot::app()->container()->make(\Corex\Config\Submissions\SubmissionsInboxScreen::class)->configuration()['flows'], 'id');

    expect($named)->toBe([$theirs])
        ->and($managed)->toContain($theirs)
        ->and($managed)->toContain($other);
});

it('finds, in one query, the flows a scope reaches by user, team and role, and no other (FR-013)', function () {
    $byUser = publishRoutedFlow('routed-ids-user', $this->users['sara'], ['rules' => [], 'fallback' => ['type' => 'user', 'config' => ['value' => (string) $this->users['omar']]]]);
    $byTeam = publishRoutedFlow('routed-ids-team', $this->users['sara'], ['rules' => [], 'fallback' => ['type' => 'team', 'config' => ['value' => 'routed-ids-desk']]]);
    $byRole = publishRoutedFlow('routed-ids-role', $this->users['sara'], ['rules' => [], 'fallback' => ['type' => 'role', 'config' => ['value' => 'author']]]);
    $other = publishRoutedFlow('routed-ids-other', $this->users['sara'], ['rules' => [], 'fallback' => ['type' => 'flow_owner', 'config' => []]]);
    update_option(WpTeamStore::OPTION, [['key' => 'routed-ids-desk', 'name' => 'Desk', 'members' => []]]);
    foreach ([$byUser, $byUser, $byTeam, $byRole, $other] as $flowId) {
        submitRouted($flowId, ['email' => 'visitor@example.test']);
    }
    storeCodeFormSubmission('user', (string) $this->users['omar']);
    $reader = Boot::app()->container()->make(\Corex\Config\Submissions\SubmissionInboxReader::class);
    $scope = new \Corex\Config\Submissions\SubmissionAccessScope(
        actorId: $this->users['omar'],
        manageAll: false,
        teamKeys: ['routed-ids-desk'],
        roleKeys: ['author'],
        flowsOnly: true,
    );
    global $wpdb;
    $before = $wpdb->num_queries;

    $found = $reader->flowIdsIn($scope);
    $queries = $wpdb->num_queries - $before;
    sort($found);

    expect($found)->toBe([$byUser, $byTeam, $byRole])
        ->and($queries)->toBe(1)
        ->and($reader->flowIdsIn(new \Corex\Config\Submissions\SubmissionAccessScope(actorId: $this->users['lina'], manageAll: false, flowsOnly: true)))->toBe([]);
});

it('names the fallback in words in the flow list', function () {
    $flowId = publishRoutedFlow('routed-words', $this->users['sara'], ['rules' => [], 'fallback' => ['type' => 'flow_owner', 'config' => []]]);

    $flows = Boot::app()->container()->make(FlowController::class)->index(routedRequest('GET', '/corex/v1/flows'))->get_data()['data']['flows'];
    $row = array_values(array_filter($flows, static fn (array $flow): bool => $flow['id'] === $flowId))[0];

    expect($row['routing_owner'])->toBe('Form owner (Sara)');
});
