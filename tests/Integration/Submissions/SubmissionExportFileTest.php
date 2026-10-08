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
use Corex\Config\Retention\RetentionSweep;
use Corex\Config\Submissions\SubmissionAccessScope;
use Corex\Config\Submissions\SubmissionExportHistory;
use Corex\Config\Submissions\SubmissionExportRetention;
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
/**
 * Found by the browser suite in CI, where the web server runs as a user that does not own the
 * uploads directory: every export failed with "could not create the directory". A host with a
 * read-only filesystem is the same. The export is written to the system's temporary directory
 * there, which is not served either.
 */
it('still has somewhere to write an export on a host that cannot write to uploads', function () {
    // A place that cannot be a directory: it is already a file. WordPress forgets an error a
    // filter sets on a path it has already tested, so the path itself has to be the one that fails.
    $nowhere = __FILE__;
    $refuse  = static fn (array $uploads): array => ['basedir' => $nowhere, 'path' => $nowhere] + $uploads;
    add_filter('upload_dir', $refuse);

    try {
        $path = $this->container->make(\Corex\Config\Export\ExportDirectory::class)->path();
    } finally {
        remove_filter('upload_dir', $refuse);
    }

    $written = wp_normalize_path($path);

    expect(is_dir($path))->toBeTrue()
        ->and(is_writable($path))->toBeTrue()
        ->and($written)->not->toContain('/uploads/')
        ->and($written)->toStartWith(wp_normalize_path(untrailingslashit(get_temp_dir())));
});

it('writes a workbook when Excel is asked for, with the form’s questions as its headings', function () {
    $this->posts[] = $submission = seedContactSubmission(
        ['name' => 'سلمى', 'email' => 'salma@example.com', 'message' => 'Hello'],
        '2026-10-07 09:30:00',
    );

    $export = $this->controller->createExport(exportRouteRequest('POST', '/corex/v1/submissions/exports', [
        'scope' => 'selected',
        'selected_ids' => [$submission],
        'columns' => ['id', 'submitted', 'answers'],
        'personal_data_acknowledged' => true,
        'format' => 'xlsx',
    ]))->get_data()['data']['export'];
    $this->posts[] = (int) $export['id'];
    $this->jobs[]  = (int) $export['job_id'];

    $advance = exportRouteRequest('POST', '/corex/v1/submissions/exports/' . $export['id'] . '/advance');
    $advance->set_url_params(['export' => $export['id']]);
    $this->controller->advanceExport($advance);

    $stored = $this->container->make(WpSubmissionExportStore::class)->file((int) $export['id']);
    $this->files[] = (string) ($stored['path'] ?? '');

    $archive = new ZipArchive();
    $archive->open((string) $stored['path']);
    $sheet = (string) $archive->getFromName('xl/worksheets/sheet1.xml');
    $archive->close();

    $download = exportRouteRequest('GET', '/corex/v1/submissions/exports/' . $export['id'] . '/download');
    $download->set_url_params(['export' => $export['id']]);
    $artifact = $this->controller->downloadExport($download)->get_data()['data']['artifact'];

    expect($export['format'])->toBe('xlsx')
        ->and($stored['extension'])->toBe('xlsx')
        ->and($artifact['filename'])->toEndWith('.xlsx')
        ->and($artifact['content_type'])->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        // The stock contact form's labels, the answers, and the time in Cairo as a date: 12:30.
        ->and($sheet)->toContain('>Name<')
        ->and($sheet)->toContain('>Message<')
        ->and($sheet)->toContain('>سلمى<')
        ->and($sheet)->toContain('<v>46302.52083333</v>');
});

/**
 * A finished export of one submission, made through the routes the screen uses (spec 103, US9).
 *
 * @return array<string,mixed> The export as the route answered it.
 */
function finishedExportOf(object $test, string $format = 'xlsx'): array
{
    $test->posts[] = $submission = seedContactSubmission(
        ['name' => 'Salma', 'email' => 'salma@example.com', 'message' => 'Hello'],
        '2026-10-07 09:30:00',
    );
    $export = $test->controller->createExport(exportRouteRequest('POST', '/corex/v1/submissions/exports', [
        'scope' => 'selected',
        'selected_ids' => [$submission],
        'columns' => ['id', 'submitted', 'answers'],
        'personal_data_acknowledged' => true,
        'format' => $format,
    ]))->get_data()['data']['export'];
    $test->posts[] = (int) $export['id'];
    $test->jobs[]  = (int) $export['job_id'];

    $advance = exportRouteRequest('POST', '/corex/v1/submissions/exports/' . $export['id'] . '/advance');
    $advance->set_url_params(['export' => $export['id']]);
    $test->controller->advanceExport($advance);

    $stored = $test->container->make(WpSubmissionExportStore::class)->file((int) $export['id']);
    $test->files[] = (string) ($stored['path'] ?? '');

    return $export + ['path' => (string) ($stored['path'] ?? '')];
}

/** The history's entry for one export, as `GET …/exports` answers it. */
function historyEntryOf(object $test, int $exportId): array
{
    $entries = $test->controller->exports(exportRouteRequest('GET', '/corex/v1/submissions/exports'))->get_data()['data']['exports'];

    return array_values(array_filter($entries, static fn (array $entry): bool => (int) $entry['id'] === $exportId))[0] ?? [];
}

/** Makes an export as old as it has to be to have expired, in both places its date is kept. */
function backdateExport(int $exportId, DateTimeImmutable $madeAt): void
{
    wp_update_post([
        'ID' => $exportId,
        'edit_date' => true,
        'post_date_gmt' => $madeAt->format('Y-m-d H:i:s'),
        'post_date' => get_date_from_gmt($madeAt->format('Y-m-d H:i:s')),
    ]);
    $payload = get_post_meta($exportId, '_corex_submission_export_payload', true);
    update_post_meta($exportId, '_corex_submission_export_payload', ['created_at' => $madeAt->format(DATE_ATOM)] + $payload);
}

it('records what an export was: who made it, its format, its size and when it expires', function () {
    $export = finishedExportOf($this);
    $entry = historyEntryOf($this, (int) $export['id']);
    $madeAt = new DateTimeImmutable((string) $entry['created_at']);

    expect($entry)->toMatchArray([
        'format' => 'xlsx',
        'record_count' => 1,
        'state' => 'ready',
        'actor_name' => wp_get_current_user()->display_name,
        'file_size' => filesize($export['path']),
        'expires_at' => $madeAt->modify('+30 days')->format(DATE_ATOM),
    ])->and($entry['file_size'])->toBeGreaterThan(1000);
});

it('removes the file of an export past its retention, keeps the entry, and no longer hands it back', function () {
    $export = finishedExportOf($this);
    $id = (int) $export['id'];
    // Older than anything else on the install can be, so the cutoff below finds this export alone.
    backdateExport($id, new DateTimeImmutable('2001-01-01 09:00:00 UTC'));
    $cutoff = new DateTimeImmutable('2001-06-01 00:00:00 UTC');
    $store = $this->container->make(WpSubmissionExportStore::class);

    $held = array_map(static fn ($run): int => $run->id, $store->holdingFilesBefore($cutoff, 10));
    $removed = $this->container->make(SubmissionExportRetention::class)->pruneOlderThan($cutoff);
    // Read from the store: an export this old is not on the first page of anybody's history.
    $run = $store->find($id);

    $download = exportRouteRequest('GET', '/corex/v1/submissions/exports/' . $id . '/download');
    $download->set_url_params(['export' => $id]);
    $refused = $this->controller->downloadExport($download);

    expect($held)->toBe([$id])
        ->and($removed)->toBe(1)
        ->and(is_file($export['path']))->toBeFalse()
        ->and($run->removedReason)->toBe('expired')
        ->and($run->removedBy)->toBe(0)
        ->and($run->format)->toBe('xlsx')
        ->and($run->fileSize)->toBeGreaterThan(1000)
        ->and($store->holdingFilesBefore($cutoff, 10))->toBe([])
        ->and($refused->get_status())->toBe(404);
});

it('has the daily retention sweep clean exported files, thirty days after they were made', function () {
    $plan = array_column($this->container->make(RetentionSweep::class)->preview(), null, 'key');

    expect($plan['submission_exports'] ?? null)->toMatchArray(['retentionDays' => 30, 'enabled' => true]);
});

it('deletes an export on request: the file goes, the entry says who, and the activity log has it', function () {
    global $wpdb;

    $export = finishedExportOf($this, 'csv');
    $id = (int) $export['id'];

    $delete = exportRouteRequest('DELETE', '/corex/v1/submissions/exports/' . $id);
    $delete->set_url_params(['export' => $id]);
    $answer = $this->controller->deleteExport($delete);
    $entry = historyEntryOf($this, $id);
    $logged = $wpdb->get_row($wpdb->prepare(
        "SELECT actor_id, outcome FROM {$wpdb->prefix}corex_activity_events WHERE kind = %s AND target_id = %s",
        'submission.export.deleted',
        (string) $id,
    ), ARRAY_A);

    expect($answer->get_status())->toBe(200)
        ->and(is_file($export['path']))->toBeFalse()
        ->and($entry)->toMatchArray([
            'state' => 'deleted',
            'removed_by' => get_current_user_id(),
            'removed_by_name' => wp_get_current_user()->display_name,
        ])
        ->and($logged)->toBe(['actor_id' => (string) get_current_user_id(), 'outcome' => 'success'])
        // A second request has nothing to delete, and says so without doing anything.
        ->and($this->controller->deleteExport($delete)->get_status())->toBe(422);
});

it('does not delete an export without a nonce', function () {
    $export = finishedExportOf($this, 'csv');
    $id = (int) $export['id'];
    $delete = new WP_REST_Request('DELETE', '/corex/v1/submissions/exports/' . $id);
    $delete->set_url_params(['export' => $id]);

    expect($this->controller->deleteExport($delete)->get_status())->toBe(403)
        ->and(is_file($export['path']))->toBeTrue();
});

it('shows a person their own exports and nobody else’s, unless they manage every submission', function () {
    $export = finishedExportOf($this, 'csv');
    $id = (int) $export['id'];
    $history = $this->container->make(SubmissionExportHistory::class);
    $somebodyElse = new SubmissionAccessScope(get_current_user_id() + 100000, false);
    $ids = static fn (array $entries): array => array_map(static fn (array $entry): int => (int) $entry['id'], $entries);

    expect($ids($history->entries(new SubmissionAccessScope(get_current_user_id(), false))))->toContain($id)
        ->and($ids($history->entries($somebodyElse)))->not->toContain($id)
        ->and($ids($history->entries(new SubmissionAccessScope($somebodyElse->actorId, true))))->toContain($id)
        ->and(fn () => $history->delete($somebodyElse, $id))->toThrow(DomainException::class, 'The submission export is unavailable.')
        ->and(is_file($export['path']))->toBeTrue();
});

/**
 * A PDF, written for real (spec 103, US8). The unit suite tests what the document says; its
 * harness wraps file streams and the PDF library's font reads come back short through it, so the
 * file itself is written here.
 */
it('exports submissions as a PDF, where the server can write one', function () {
    if (! Corex\Config\Export\PdfExportWriter::supported()) {
        $this->markTestSkipped('The PDF library or the PHP extensions it needs are not installed.');
    }
    $this->posts[] = $arabic = seedContactSubmission(
        ['name' => 'سلمى عادل', 'email' => 'salma@example.com', 'message' => 'أريد موقعاً لشركتي. متى يمكن أن نبدأ؟'],
        '2026-10-07 09:30:00',
    );
    $this->posts[] = $english = seedContactSubmission(
        ['name' => 'Omar Hassan', 'email' => 'omar@example.com', 'message' => 'A website, please.'],
        '2026-10-07 10:00:00',
    );

    $offered = $this->controller->previewExport(exportRouteRequest('POST', '/corex/v1/submissions/exports/preview', [
        'selected_ids' => [$arabic, $english],
    ]))->get_data()['data']['formats'];

    $export = $this->controller->createExport(exportRouteRequest('POST', '/corex/v1/submissions/exports', [
        'scope' => 'selected',
        'selected_ids' => [$arabic, $english],
        'columns' => ['id', 'submitted', 'answers'],
        'personal_data_acknowledged' => true,
        'format' => 'pdf',
    ]))->get_data()['data']['export'];
    $this->posts[] = (int) $export['id'];
    $this->jobs[]  = (int) $export['job_id'];

    $advance = exportRouteRequest('POST', '/corex/v1/submissions/exports/' . $export['id'] . '/advance');
    $advance->set_url_params(['export' => $export['id']]);
    $progress = $this->controller->advanceExport($advance)->get_data()['data']['progress'];

    $stored = $this->container->make(WpSubmissionExportStore::class)->file((int) $export['id']);
    $this->files[] = (string) ($stored['path'] ?? '');

    $download = exportRouteRequest('GET', '/corex/v1/submissions/exports/' . $export['id'] . '/download');
    $download->set_url_params(['export' => $export['id']]);
    $artifact = $this->controller->downloadExport($download)->get_data()['data']['artifact'];
    $document = (string) base64_decode($artifact['base64'], true);

    expect($offered)->toBe(['available' => ['xlsx', 'csv', 'pdf'], 'pdf_most_records' => 500])
        ->and($progress)->toMatchArray(['state' => 'completed', 'error' => ''])
        ->and($artifact['filename'])->toEndWith('-contact-' . gmdate('Y-m-d') . '.pdf')
        ->and($artifact['content_type'])->toBe('application/pdf')
        ->and(substr($document, 0, 5))->toBe('%PDF-')
        // Signed as CoreX's: the library records who made the file.
        ->and($document)->toContain('/Creator')
        ->and(strlen($document))->toBeGreaterThan(20000);
});
