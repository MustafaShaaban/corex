<?php

/**
 * Integration test: an export of a real submission, written by the real job (spec 103, US1).
 *
 * The owner, of the file the export used to write: "the exported file should be readable not
 * serialized cells". This asks the real container for the export, runs its job as the runner
 * would, and reads the bytes the download hands back.
 *
 * @package Corex\Tests\Integration\Submissions
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Jobs\JobRunner;
use Corex\Config\Submissions\SubmissionsController;
use Corex\Config\Submissions\WpSubmissionExportStore;
use Corex\Security\Upload\ProtectedUploads;

function exportRouteRequest(string $method, string $route, array $payload = []): WP_REST_Request
{
    $request = new WP_REST_Request($method, $route);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    $request->set_body_params($payload);

    return $request;
}

/**
 * @param array<string,mixed> $answers
 */
function seedContactSubmission(array $answers, string $createdGmt): int
{
    return (int) wp_insert_post([
        'post_type' => 'corex_submission',
        'post_status' => 'private',
        'post_title' => 'Export test submission',
        'post_date_gmt' => $createdGmt,
        'post_date' => get_date_from_gmt($createdGmt),
        'meta_input' => [
            'corex_form_slug' => 'contact',
            'corex_flow_label_snapshot' => 'Contact',
            'corex_submission_status' => 'new',
            'corex_owner_type' => 'none',
            'corex_owner_key' => '',
            'corex_is_test' => 0,
            'corex_values_json' => $answers,
        ],
    ]);
}

beforeEach(function () {
    if (! post_type_exists('corex_submission')) {
        register_post_type('corex_submission', ['public' => false]);
    }
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));

    $this->container  = Boot::app()->container();
    $this->controller = $this->container->make(SubmissionsController::class);
    $this->posts      = [];
    $this->jobs       = [];
    $this->files      = [];
    $this->timezone   = get_option('timezone_string');
    update_option('timezone_string', 'Africa/Cairo');
});

afterEach(function () {
    global $wpdb;

    update_option('timezone_string', $this->timezone);

    foreach ($this->posts as $id) {
        wp_delete_post($id, true);
    }
    foreach ($this->jobs as $id) {
        $wpdb->delete($wpdb->prefix . 'corex_bounded_jobs', ['id' => $id]);
    }
    foreach ($this->posts as $id) {
        $wpdb->delete($wpdb->prefix . 'corex_activity_events', ['target_type' => 'submission_export', 'target_id' => (string) $id]);
    }
    foreach ($this->files as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

it('exports a submission as a file a person can read, and hands it back', function () {
    $this->posts[] = $first = seedContactSubmission(
        ['name' => 'سلمى', 'email' => 'salma@example.com', 'message' => '=HYPERLINK("http://x")'],
        '2026-10-07 09:30:00',
    );
    $this->posts[] = $second = seedContactSubmission(
        ['name' => 'Omar', 'email' => 'omar@example.com', 'message' => 'A website, please.'],
        '2026-10-07 10:00:00',
    );

    $created = $this->controller->createExport(exportRouteRequest('POST', '/corex/v1/submissions/exports', [
        'scope' => 'selected',
        'selected_ids' => [$first, $second],
        'columns' => ['identity', 'workflow', 'submitted_fields'],
        'personal_data_acknowledged' => true,
    ]))->get_data();

    expect($created['ok'])->toBeTrue();
    $export = $created['data']['export'];
    $this->posts[] = (int) $export['id'];
    $this->jobs[]  = (int) $export['job_id'];

    // What cron or Action Scheduler would do, as many times as the job needs.
    $runner = $this->container->make(JobRunner::class);
    for ($step = 0; $step < 5; $step++) {
        $runner->run((int) $export['job_id']);
    }

    $stored = $this->container->make(WpSubmissionExportStore::class)->file((int) $export['id']);
    $this->files[] = (string) ($stored['path'] ?? '');

    $download = exportRouteRequest('GET', '/corex/v1/submissions/exports/' . $export['id'] . '/download');
    $download->set_url_params(['export' => $export['id']]);
    $artifact = $this->controller->downloadExport($download)->get_data()['data']['artifact'];

    expect($stored)->not->toBeNull()
        // A real file: a stored path that had lost its separators still "contained" the directory.
        ->and(is_file((string) $stored['path']))->toBeTrue()
        // In the directory a web server is told not to serve.
        ->and(str_replace('\\', '/', (string) $stored['path']))->toContain('/' . ProtectedUploads::DIRECTORY . '/exports/')
        ->and($artifact['content_type'])->toBe('text/csv; charset=utf-8')
        ->and($artifact['filename'])->toEndWith('-contact-' . gmdate('Y-m-d') . '.csv')
        ->and(base64_decode($artifact['base64'], true))->toBe(
            "\xEF\xBB\xBF"
            // The stock contact form's own labels, in its own order.
            . "ID,Submitted,Form,Status,\"Assigned to\",Read,Test,Name,Email,Message\r\n"
            . $first . ",\"2026-10-07 12:30\",Contact,New,Unassigned,No,No,سلمى,salma@example.com,\"'=HYPERLINK(\"\"http://x\"\")\"\r\n"
            . $second . ",\"2026-10-07 13:00\",Contact,New,Unassigned,No,No,Omar,omar@example.com,\"A website, please.\"\r\n",
        );
});

it('refuses an export of nothing, and says so', function () {
    $response = $this->controller->createExport(exportRouteRequest('POST', '/corex/v1/submissions/exports', [
        'scope' => 'filtered',
        'query' => ['search' => 'nobody-' . wp_generate_password(16, false) . '@example.invalid'],
        'columns' => ['identity'],
    ]));

    expect($response->get_data()['ok'])->toBeFalse()
        ->and($response->get_status())->toBeGreaterThanOrEqual(400);
});

/**
 * The dialog does not wait for the scheduler: it asks for a step, and then another, until the
 * file is ready (spec 103, FR-022). On a quiet site WP-Cron may not fire for minutes.
 */
it('finishes an export for a screen that is waiting on it, without the scheduler', function () {
    $this->posts[] = $submission = seedContactSubmission(
        ['name' => 'Mona', 'email' => 'mona@example.com', 'message' => 'Hello'],
        '2026-10-07 09:30:00',
    );

    $export = $this->controller->createExport(exportRouteRequest('POST', '/corex/v1/submissions/exports', [
        'scope' => 'selected',
        'selected_ids' => [$submission],
        'columns' => ['id', 'answer:email'],
        'personal_data_acknowledged' => true,
        'separator' => 'semicolon',
    ]))->get_data()['data']['export'];
    $this->posts[] = (int) $export['id'];
    $this->jobs[]  = (int) $export['job_id'];

    $advance = exportRouteRequest('POST', '/corex/v1/submissions/exports/' . $export['id'] . '/advance');
    $advance->set_url_params(['export' => $export['id']]);
    $progress = $this->controller->advanceExport($advance)->get_data()['data']['progress'];

    $stored = $this->container->make(WpSubmissionExportStore::class)->file((int) $export['id']);
    $this->files[] = (string) ($stored['path'] ?? '');

    expect($progress)->toMatchArray(['state' => 'completed', 'processed' => 1, 'total' => 1, 'error' => ''])
        // One answer as a column, and the separator, both reach the file through the route.
        ->and(file_get_contents((string) $stored['path']))->toBe("\xEF\xBB\xBF" . "ID;Email\r\n" . $submission . ";mona@example.com\r\n");
});

/**
 * The scheduler and a waiting screen can ask for the same step at the same moment. Two runs would
 * each read the job at the same point and write the same batch twice.
 */
it('does not take a step of a job while another run of it holds the step', function () {
    $this->posts[] = $submission = seedContactSubmission(['name' => 'Mona'], '2026-10-07 09:30:00');

    $export = $this->controller->createExport(exportRouteRequest('POST', '/corex/v1/submissions/exports', [
        'scope' => 'selected',
        'selected_ids' => [$submission],
        'columns' => ['id'],
    ]))->get_data()['data']['export'];
    $this->posts[] = (int) $export['id'];
    $this->jobs[]  = (int) $export['job_id'];

    $runner = $this->container->make(JobRunner::class);
    $store  = $this->container->make(WpSubmissionExportStore::class);
    $lock   = 'corex_job_running_' . $export['job_id'];

    add_option($lock, time(), '', false);
    $runner->run((int) $export['job_id']);
    $whileHeld = $store->file((int) $export['id']);

    delete_option($lock);
    $runner->run((int) $export['job_id']);
    $afterwards = $store->file((int) $export['id']);
    $this->files[] = (string) ($afterwards['path'] ?? '');

    expect($whileHeld)->toBeNull()
        ->and($afterwards)->not->toBeNull()
        // And the run that took the step let go of it.
        ->and(get_option($lock, 'released'))->toBe('released');
});

it('takes over a step from a run that died holding it', function () {
    $this->posts[] = $submission = seedContactSubmission(['name' => 'Mona'], '2026-10-07 09:30:00');

    $export = $this->controller->createExport(exportRouteRequest('POST', '/corex/v1/submissions/exports', [
        'scope' => 'selected',
        'selected_ids' => [$submission],
        'columns' => ['id'],
    ]))->get_data()['data']['export'];
    $this->posts[] = (int) $export['id'];
    $this->jobs[]  = (int) $export['job_id'];

    add_option('corex_job_running_' . $export['job_id'], time() - 600, '', false);
    $this->container->make(JobRunner::class)->run((int) $export['job_id']);

    $stored = $this->container->make(WpSubmissionExportStore::class)->file((int) $export['id']);
    $this->files[] = (string) ($stored['path'] ?? '');

    expect($stored)->not->toBeNull();
});

it('says how many submissions each choice would export', function () {
    $marker = 'count-' . strtolower(wp_generate_password(12, false)) . '@example.com';
    $this->posts[] = $first  = seedContactSubmission(['name' => 'A', 'email' => $marker], '2026-10-07 09:30:00');
    $this->posts[] = $second = seedContactSubmission(['name' => 'B', 'email' => $marker], '2026-10-07 09:31:00');
    update_post_meta($first, 'corex_submitter_email', $marker);
    update_post_meta($second, 'corex_submitter_email', $marker);

    $counts = $this->controller->previewExport(exportRouteRequest('POST', '/corex/v1/submissions/exports/preview', [
        'selected_ids' => [$first],
        'query' => ['search' => $marker],
    ]))->get_data()['data']['counts'];

    expect($counts['selected'])->toBe(1)
        ->and($counts['filtered'])->toBe(2)
        ->and($counts['accessible'])->toBeGreaterThanOrEqual(2);
});