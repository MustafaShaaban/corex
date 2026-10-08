<?php

/**
 * Deleting a submission for good, in WordPress (spec 105, US2; plan D9 and D11).
 *
 * What only WordPress can show: that a file uploaded with a submission is found by the answer
 * that points at it and goes from disk with the submission, that only a trashed submission can
 * be deleted, and that the route is guarded and asks for the permission of its own.
 *
 * @package Corex\Tests\Integration\Submissions
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Data\WpSubmissionsReader;
use Corex\Config\Submissions\SubmissionsController;
use Corex\Config\Submissions\SubmissionTrashStore;
use Corex\Config\Submissions\WpSubmissionTrashStore;
use Corex\Security\Upload\ProtectedUploads;

beforeEach(function () {
    if (! post_type_exists('corex_submission')) {
        register_post_type('corex_submission', ['public' => false]);
    }
    $this->made = [];
    $this->trash = new WpSubmissionTrashStore(new WpSubmissionsReader());
    $this->administrator = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
    wp_set_current_user($this->administrator);
});

afterEach(function () {
    foreach ($this->made as $id) {
        get_post_type($id) === 'attachment' ? wp_delete_attachment($id, true) : wp_delete_post($id, true);
    }
    remove_all_filters('corex_submission_delete_permanently');
    wp_set_current_user(0);
});

/**
 * A file as a form upload leaves it: in the protected uploads directory, as a private attachment
 * with no parent, marked protected and with the form's upload context.
 */
function uploadedWithAForm(string $context = 'form-cv'): int
{
    $path = ProtectedUploads::ensure() . '/corex-delete-test-' . wp_generate_password(8, false) . '.txt';
    file_put_contents($path, 'A curriculum vitae.');
    $attachment = (int) wp_insert_attachment(['post_title' => 'CV', 'post_status' => 'private', 'post_mime_type' => 'text/plain'], $path);
    update_post_meta($attachment, '_corex_protected', '1');
    update_post_meta($attachment, '_corex_upload_context', $context);

    return $attachment;
}

/** @param array<string,mixed> $meta */
function deletableSubmission(array $meta = []): int
{
    return (int) wp_insert_post([
        'post_type' => 'corex_submission',
        'post_status' => 'private',
        'post_title' => 'Careers submission',
        'meta_input' => $meta + [
            'corex_form_slug' => 'corex-delete-test',
            'corex_submission_status' => 'new',
            'corex_owner_type' => 'none',
            'corex_field_email' => 'salma@example.com',
            'corex_submission_updated_at' => '2026-10-01T09:30:00+00:00',
        ],
    ]);
}

it('finds the files uploaded with a submission by the answers that point at them, and nothing else', function () {
    $this->made[] = $cv = uploadedWithAForm();
    // An answer that is a number and happens to be the id of some other attachment or post.
    $this->made[] = $logo = (int) wp_insert_attachment(['post_title' => 'Site logo', 'post_status' => 'inherit']);
    $this->made[] = $notForm = uploadedWithAForm('careers-cv');
    $this->made[] = $id = deletableSubmission([
        'corex_field_cv' => (string) $cv,
        'corex_field_team_size' => (string) $logo,
        'corex_field_other' => (string) $notForm,
    ]);

    expect($this->trash->uploadsOf($id))->toBe([$cv])
        ->and($this->trash->forgetUpload($logo))->toBeFalse()
        ->and(get_post($logo))->not->toBeNull();
});

it('finds the email attempts made for a submission, wherever it recorded them', function () {
    $this->made[] = $id = deletableSubmission([
        'corex_email_json' => ['state' => 'sent', 'bindings' => [
            'notify' => ['attempt_id' => 'aaaa-1'],
            'autoreply' => ['attempt_id' => 'aaaa-2'],
        ]],
        'corex_notification_delivery' => ['status' => 'sent', 'attempt_id' => 'aaaa-1'],
        'corex_submission_timeline' => [
            ['stage' => 'status', 'summary' => ['to' => 'closed']],
            ['stage' => 'email', 'summary' => ['action' => 'reply', 'attempt_id' => 'aaaa-3']],
        ],
    ]);

    expect($this->trash->emailAttemptsOf($id))->toEqualCanonicalizing(['aaaa-1', 'aaaa-2', 'aaaa-3']);
});

it('deletes a trashed submission, and only a trashed one', function () {
    $this->made[] = $id = deletableSubmission();

    expect(fn () => $this->trash->delete($id))->toThrow(DomainException::class)
        ->and(get_post_status($id))->toBe('private');

    $this->trash->trash($id, $this->administrator, SubmissionTrashStore::VIA_INBOX);
    $this->trash->delete($id);

    expect(get_post($id))->toBeNull()
        ->and(get_post_meta($id, 'corex_field_email', true))->toBe('');
});

it('deletes a submission through its route with the file uploaded with it, and records it without what it held', function () {
    $this->made[] = $cv = uploadedWithAForm();
    $file = (string) get_attached_file($cv);
    $this->made[] = $id = deletableSubmission(['corex_field_cv' => (string) $cv]);
    $controller = Boot::app()->container()->make(SubmissionsController::class);
    $request = static function (string $method, string $path, array $body = []) use ($id): WP_REST_Request {
        $request = new WP_REST_Request($method, '/corex/v1/submissions/' . $id . $path);
        $request->set_url_params(['id' => $id]);
        $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
        $request->set_header('Content-Type', 'application/json');
        $request->set_body((string) wp_json_encode($body));

        return $request;
    };

    // A submission is trashed first (FR-009): from the inbox it is "not found in the trash".
    $fromInbox = $controller->destroy($request('DELETE', ''));
    $controller->trash($request('POST', '/trash', ['expected_updated_at' => '2026-10-01T09:30:00+00:00']));

    // Somebody who manages submissions and may not delete them (FR-010).
    add_filter('corex_submission_delete_permanently', '__return_false');
    $refused = $controller->destroy($request('DELETE', ''));
    remove_all_filters('corex_submission_delete_permanently');

    $deleted = $controller->destroy($request('DELETE', ''));

    $activity = Boot::app()->container()->make(Corex\Activity\ActivityRepository::class)
        ->query(['kind' => 'submission.deleted'], 1, 1)[0] ?? null;

    expect($fromInbox->get_status())->toBe(404)
        ->and($refused->get_status())->toBe(403)
        ->and(is_file($file))->toBeFalse()
        ->and($deleted->get_status())->toBe(200)
        ->and(get_post($id))->toBeNull()
        ->and(get_post($cv))->toBeNull()
        ->and($activity)->not->toBeNull()
        ->and(wp_json_encode([$activity->context, $activity->summary, $activity->targetLabel]))->not->toContain('salma@example.com')
        ->and($activity->context['forms'])->toBe(['corex-delete-test']);
});

it('declares the delete route with the inbox’s guard', function () {
    $routes = rest_get_server()->get_routes();
    $delete = array_values(array_filter(
        $routes['/corex/v1/submissions/(?P<id>\d+)'] ?? [],
        static fn (array $handler): bool => isset($handler['methods']['DELETE']),
    ));

    expect($delete)->toHaveCount(1)
        ->and($delete[0]['permission_callback'])->not->toBe('__return_true');
});
