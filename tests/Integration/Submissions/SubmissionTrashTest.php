<?php

/**
 * The trash, in WordPress (spec 105, US1; plan D1 and D3).
 *
 * What only WordPress can show: that a submission CoreX trashes is out of every read at once,
 * that WordPress's own daily clean-up never takes it, that it is trashed and not deleted on a
 * site set to skip the trash, and that a restore puts back exactly what was there.
 *
 * @package Corex\Tests\Integration\Submissions
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Data\WpSubmissionsReader;
use Corex\Config\Submissions\SubmissionAccessScope;
use Corex\Config\Submissions\SubmissionInboxQuery;
use Corex\Config\Submissions\SubmissionsController;
use Corex\Config\Submissions\SubmissionTrashStore;
use Corex\Config\Submissions\WpSubmissionTrashStore;

beforeEach(function () {
    if (! post_type_exists('corex_submission')) {
        register_post_type('corex_submission', ['public' => false]);
    }
    $this->reader = new WpSubmissionsReader();
    $this->trash = new WpSubmissionTrashStore($this->reader);
    $this->administrator = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
    wp_set_current_user($this->administrator);
    // Before as well as after: these tests list everything of their form, and a run that was
    // stopped, or one made before the clean-up below was right, leaves rows a later run would list.
    deleteTrashTestSubmissions();
});

afterEach(function () {
    deleteTrashTestSubmissions();
    wp_set_current_user(0);
});

/**
 * Delete every submission of the form these tests give theirs, in the inbox or in the trash.
 *
 * Not "the newest posts that were not here before": these are dated a week back, and once the
 * install held 500 newer submissions that way found none of them, and each run left its own behind
 * to be listed by the next.
 */
function deleteTrashTestSubmissions(): void
{
    $ids = get_posts([
        'post_type' => 'corex_submission',
        'post_status' => ['private', 'trash'],
        'posts_per_page' => 200,
        'fields' => 'ids',
        'meta_key' => 'corex_form_slug',
        'meta_value' => 'corex-trash-test',
    ]);
    foreach ($ids as $id) {
        wp_delete_post((int) $id, true);
    }
}

function trashableSubmission(string $team = 'sales', string $email = 'salma@example.com'): int
{
    return (int) wp_insert_post([
        'post_type' => 'corex_submission',
        'post_status' => 'private',
        'post_title' => 'Contact submission',
        'post_date' => '2026-10-01 09:30:00',
        'meta_input' => [
            'corex_form_slug' => 'corex-trash-test',
            'corex_flow_label_snapshot' => 'Trash test',
            'corex_submission_status' => 'in_progress',
            'corex_owner_type' => 'team',
            'corex_owner_key' => $team,
            'corex_is_test' => 0,
            'corex_submitter_email' => $email,
            'corex_values_json' => ['email' => $email, 'message' => 'Call me tomorrow'],
            'corex_submission_notes' => [['id' => 1, 'body' => 'Rang twice', 'visibility' => 'corex-team']],
            'corex_submission_updated_at' => '2026-10-01T09:30:00+00:00',
        ],
    ]);
}

/** @return list<int> */
function listedSubmissions(WpSubmissionsReader $reader, string $view, SubmissionAccessScope $scope): array
{
    $page = $reader->queryInbox(SubmissionInboxQuery::from(['flow' => 'slug:corex-trash-test', 'view' => $view]), $scope);

    return array_map('intval', array_column($page['items'], 'id'));
}

it('trashes a submission as CoreX’s, with who and when, and without WordPress’s trash clock', function () {
    $id = trashableSubmission();

    $this->trash->trash($id, $this->administrator, SubmissionTrashStore::VIA_INBOX);

    expect(get_post_status($id))->toBe('trash')
        ->and((int) get_post_meta($id, 'corex_trashed_by', true))->toBe($this->administrator)
        ->and(get_post_meta($id, 'corex_trashed_via', true))->toBe('inbox')
        ->and(strtotime((string) get_post_meta($id, 'corex_trashed_at', true)))->toBeGreaterThan(time() - 60)
        // The one thing wp_scheduled_delete() selects a trashed post by.
        ->and(metadata_exists('post', $id, '_wp_trash_meta_time'))->toBeFalse();
});

it('is left alone by WordPress’s own daily trash clean-up', function () {
    $ours = trashableSubmission();
    $this->trash->trash($ours, $this->administrator, SubmissionTrashStore::VIA_INBOX);
    // A post trashed the WordPress way, long enough ago: the clean-up does take this one, which
    // is how this test knows the clean-up ran.
    $wordpress = trashableSubmission();
    wp_update_post(['ID' => $wordpress, 'post_status' => 'trash']);
    update_post_meta($wordpress, '_wp_trash_meta_time', time() - (DAY_IN_SECONDS * (EMPTY_TRASH_DAYS + 1)));
    update_post_meta($wordpress, '_wp_trash_meta_status', 'private');

    wp_scheduled_delete();

    expect(get_post($wordpress))->toBeNull()
        ->and(get_post_status($ours))->toBe('trash');
});

it('leaves the inbox and appears in the trash, for the people who may see it', function () {
    $sales = trashableSubmission('sales');
    $legal = trashableSubmission('legal', 'omar@example.com');
    $everyone = new SubmissionAccessScope($this->administrator, true);
    $salesTeam = new SubmissionAccessScope($this->administrator, false, ['sales']);

    $this->trash->trash($sales, $this->administrator, SubmissionTrashStore::VIA_INBOX);
    $this->trash->trash($legal, $this->administrator, SubmissionTrashStore::VIA_INBOX);

    $row = $this->reader->findInbox($sales, $everyone);

    expect(listedSubmissions($this->reader, 'inbox', $everyone))->toBe([])
        ->and(listedSubmissions($this->reader, 'trash', $everyone))->toEqualCanonicalizing([$sales, $legal])
        ->and(listedSubmissions($this->reader, 'trash', $salesTeam))->toBe([$sales])
        ->and($row['trashed'])->toBeTrue()
        ->and($row['trashed_by'])->toBe($this->administrator)
        ->and($row['trashed_by_name'])->toBe(get_userdata($this->administrator)->display_name)
        ->and($row['status'])->toBe('in_progress')
        // Nothing can change it while it is there: every change asks for it this way.
        ->and($this->reader->findWorkflow($sales))->toBeNull()
        ->and($this->trash->findTrashed($sales)['id'])->toBe($sales);
});

it('restores a submission exactly as it was', function () {
    $id = trashableSubmission();
    $before = $this->reader->findWorkflow($id);
    $this->trash->trash($id, $this->administrator, SubmissionTrashStore::VIA_INBOX);

    $this->trash->restore($id);

    $after = $this->reader->findWorkflow($id);
    $unchanged = array_diff_key($before, ['updated_at' => 1]);

    expect(get_post_status($id))->toBe('private')
        ->and(array_diff_key($after, ['updated_at' => 1]))->toBe($unchanged)
        ->and($after['trashed'])->toBeFalse()
        ->and($after['created_at'])->toBe($before['created_at'])
        ->and($after['notes'][0]['body'])->toBe('Rang twice')
        ->and(metadata_exists('post', $id, 'corex_trashed_at'))->toBeFalse()
        ->and($this->trash->findTrashed($id))->toBeNull();
});

it('restores a submission that was trashed the WordPress way before this, and clears what that left', function () {
    $id = trashableSubmission();
    wp_trash_post($id);

    expect($this->trash->findTrashed($id))->not->toBeNull();

    $this->trash->restore($id);

    expect(get_post_status($id))->toBe('private')
        ->and(metadata_exists('post', $id, '_wp_trash_meta_time'))->toBeFalse()
        ->and(metadata_exists('post', $id, '_wp_trash_meta_status'))->toBeFalse();
});

it('refuses to trash what is not in the inbox, and to restore what is not in the trash', function () {
    $id = trashableSubmission();
    $page = (int) wp_insert_post(['post_type' => 'page', 'post_status' => 'private', 'post_title' => 'Not a submission']);

    expect(fn () => $this->trash->restore($id))->toThrow(DomainException::class)
        ->and(fn () => $this->trash->trash($page, $this->administrator, 'inbox'))->toThrow(DomainException::class)
        ->and(get_post_status($page))->toBe('private');

    wp_delete_post($page, true);
});

it('trashes and restores through the routes, and says why a trashed submission refuses a change', function () {
    $id = trashableSubmission();
    $controller = Boot::app()->container()->make(SubmissionsController::class);
    $request = static function (string $method, string $path, array $body = []) use ($id): WP_REST_Request {
        $request = new WP_REST_Request($method, '/corex/v1/submissions/' . $id . $path);
        $request->set_url_params(['id' => $id]);
        $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
        $request->set_header('Content-Type', 'application/json');
        $request->set_body((string) wp_json_encode($body));

        return $request;
    };

    $trashed = $controller->trash($request('POST', '/trash', ['expected_updated_at' => '2026-10-01T09:30:00+00:00']));
    $shown = $controller->show($request('GET', ''))->get_data()['data']['submission'];
    $changed = $controller->update($request('PATCH', '', ['status' => 'closed', 'expected_updated_at' => $shown['updated_at']]));
    $restored = $controller->restore($request('POST', '/restore'));

    expect($trashed->get_status())->toBe(200)
        ->and($shown['trashed'])->toBeTrue()
        ->and($changed->get_status())->toBe(409)
        ->and(wp_json_encode($changed->get_data()))->toContain('in the trash')
        ->and($restored->get_status())->toBe(200)
        ->and(get_post_status($id))->toBe('private')
        ->and(array_column($this->reader->findWorkflow($id)['timeline'], 'stage'))->toBe(['trash', 'restore']);
});

it('declares the two routes with the inbox’s guard', function () {
    $routes = rest_get_server()->get_routes();

    foreach (['/corex/v1/submissions/(?P<id>\d+)/trash', '/corex/v1/submissions/(?P<id>\d+)/restore'] as $route) {
        expect($routes)->toHaveKey($route)
            ->and($routes[$route][0]['permission_callback'])->not->toBe('__return_true');
    }
});
