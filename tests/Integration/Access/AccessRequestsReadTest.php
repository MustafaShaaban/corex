<?php

/**
 * Who is handed the open access requests by `GET corex/v1/access/requests` (spec 079, FR-011a;
 * DECISIONS #346).
 *
 * Through the REST server and not by calling the controller's methods, because the promise is the
 * route's permission callback: a call to `requests()` never reaches it.
 *
 * @package Corex\Tests\Integration\Access
 */

declare(strict_types=1);

use Corex\Access\CorexAbility;
use Corex\Boot;
use Corex\Config\Access\AccessTables;
use Corex\Config\Activity\ActivityTable;
use Corex\Config\Notifications\NotificationTable;
use Corex\Database\Schema\Migrator;

beforeEach(function () {
    $container = Boot::app()->container();
    $this->migrator = $container->make(Migrator::class);
    foreach ($container->make(AccessTables::class)->schemas() as $schema) {
        $this->migrator->create($schema);
    }

    // A site with an email route for a new request mails its administrators when one is filed.
    add_filter('pre_wp_mail', '__return_true');

    $this->people = [];

    // Accounts of the test's own, deleted in `afterEach`: the install's people are not borrowed.
    $this->person = function (string $role): int {
        $id = wp_insert_user([
            'user_login' => 'corex-access-reader-' . wp_generate_password(8, false),
            'user_pass'  => wp_generate_password(),
            'user_email' => uniqid('corex-access-reader-', true) . '@example.test',
            'role'       => $role,
        ]);
        // Checked rather than cast: `(int)` turns a WP_Error into 1, and `afterEach` deletes the
        // user with this id and every request they filed.
        expect($id)->toBeInt();
        $this->people[] = $id;

        return $id;
    };

    $this->send = function (int $userId, string $method, array $body = []): WP_REST_Response {
        wp_set_current_user($userId);
        $request = new WP_REST_Request($method, '/corex/v1/access/requests');
        if ($userId > 0) {
            $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
        }
        $request->set_body_params($body);

        return rest_get_server()->dispatch($request);
    };

    // One open request, from somebody who is not the person a test reads as.
    $this->reason = 'Reason typed by the requester ' . wp_generate_password(12, false);
    $this->requesterId = ($this->person)('subscriber');
    $filed = ($this->send)($this->requesterId, 'POST', [
        'ability' => CorexAbility::MANAGE_FORMS,
        'reason'  => $this->reason,
    ]);
    expect($filed->get_data()['data']['result']['state'] ?? null)->toBe('completed');
});

afterEach(function () {
    remove_filter('pre_wp_mail', '__return_true');
    wp_set_current_user(0);

    global $wpdb;
    $requests = $this->migrator->fullName(AccessTables::REQUESTS);

    if (! function_exists('wp_delete_user')) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }

    foreach ($this->people as $personId) {
        // A request's audit entry and notification are found through its id, so before the row
        // that carries the id is deleted; and the request before its requester, because nothing
        // in CoreX listens for a user being deleted.
        $requestIds = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$requests} WHERE requester_id = %d", $personId));
        foreach ($requestIds as $requestId) {
            $wpdb->delete($this->migrator->fullName(ActivityTable::NAME), [
                'target_type' => 'access_request',
                'target_id'   => (string) $requestId,
            ]);
            $wpdb->delete($this->migrator->fullName(NotificationTable::NAME), [
                'source_type' => 'access_request',
                'source_id'   => (string) $requestId,
            ]);
        }
        $wpdb->delete($requests, ['requester_id' => $personId], ['%d']);
        wp_delete_user($personId);
    }
});

it('hands the open requests to an administrator', function () {
    $response = ($this->send)(($this->person)('administrator'), 'GET');

    expect($response->get_status())->toBe(200)
        ->and(array_column($response->get_data()['data']['requests'], 'reason'))->toContain($this->reason);
});

it('refuses the open requests to a person who does not manage access', function (string $role) {
    $response = ($this->send)(($this->person)($role), 'GET');

    expect($response->get_status())->toBe(403)
        ->and(wp_json_encode($response->get_data()))->not->toContain($this->reason);
})->with(['subscriber', 'contributor', 'author', 'editor']);

it('refuses the open requests to the person who filed one of them', function () {
    $response = ($this->send)($this->requesterId, 'GET');

    expect($response->get_status())->toBe(403)
        ->and(wp_json_encode($response->get_data()))->not->toContain($this->reason);
});

it('refuses the open requests to a visitor who is not signed in', function () {
    $response = ($this->send)(0, 'GET');

    expect($response->get_status())->toBe(401)
        ->and(wp_json_encode($response->get_data()))->not->toContain($this->reason);
});

it('still takes a request for access from a person who manages nothing', function () {
    $response = ($this->send)(($this->person)('author'), 'POST', [
        'ability' => CorexAbility::MANAGE_SUBMISSIONS,
        'reason'  => 'I answer the contact form.',
    ]);

    expect($response->get_status())->toBe(200)
        ->and($response->get_data()['data']['result']['state'])->toBe('completed');
});
