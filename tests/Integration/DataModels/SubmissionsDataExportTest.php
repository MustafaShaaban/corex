<?php

/**
 * Integration test: Form submissions exported from CoreX Data, through the routes its dialog uses
 * and the job its runner runs (spec 103, US10).
 *
 * Found in a real export on 2026-10-08. The file began
 * `Submitted,Form,Submission,Email,Name,Message` and its row read
 * `"2026-10-08 13:28",corex-inbox-e2e,"email: someone@example.com",,,`: a column was offered for
 * each answer and nothing filled it. A ticked row lost "Submission" too. This stores a real
 * submission, exports it both ways, and reads the file that comes back.
 *
 * @package Corex\Tests\Integration\DataModels
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Activity\ActivityTable;
use Corex\Config\Data\DataManagementController;
use Corex\Config\DataModels\WpDataExportStore;
use Corex\Database\Schema\Migrator;
use Corex\Tests\Support\CreatedPosts;

function submissionsDataExportRoute(string $method, string $path, array $payload = [], array $params = []): WP_REST_Request
{
    $request = new WP_REST_Request($method, '/corex/v1/data/submissions/' . $path);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    $request->set_body_params($payload);
    $request->set_url_params(['source' => 'submissions'] + $params);

    return $request;
}

/**
 * A submission as the forms pipeline stores it for the Data source: its form's slug, and one
 * `corex_field_*` meta row for each answer.
 *
 * @param array<string,string> $answers
 */
function storeAnsweredSubmission(string $form, array $answers): int
{
    $meta = ['corex_form_slug' => $form];
    foreach ($answers as $key => $value) {
        $meta['corex_field_' . $key] = $value;
    }

    return (int) wp_insert_post([
        'post_type' => 'corex_submission',
        'post_status' => 'private',
        'post_title' => 'Data export test submission',
        'meta_input' => $meta,
    ]);
}

beforeEach(function () {
    if (! post_type_exists('corex_submission')) {
        register_post_type('corex_submission', ['public' => false]);
    }
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));

    $container = Boot::app()->container();
    $container->make(WpDataExportStore::class)->registerPostType();
    $this->exports = $container->make(WpDataExportStore::class);
    $this->controller = $container->make(DataManagementController::class);
    $this->posts = CreatedPosts::watch('corex_submission', WpDataExportStore::POST_TYPE);
    $this->jobs = [];
    $this->files = [];
});

afterEach(function () {
    global $wpdb;

    foreach ($this->files as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
    foreach ($this->jobs as $id) {
        $wpdb->delete($wpdb->prefix . 'corex_bounded_jobs', ['id' => $id]);
    }
    // A finished export is audited against its run. Post ids are unique across types, so every
    // remembered id is asked for and only the runs' events match.
    foreach ($this->posts->ids() as $id) {
        $wpdb->delete((new Migrator())->fullName(ActivityTable::NAME), ['target_type' => 'data_export', 'target_id' => (string) $id]);
    }
    $this->posts->delete();
});

it('writes a form submission with its summary and each answer under its own column', function (string $scope) {
    // A form of this test's own, so that filtering by it finds this submission and no other.
    $form = 'data-export-' . strtolower(wp_generate_password(10, false));
    $submission = storeAnsweredSubmission($form, ['email' => 'someone@example.com', 'name' => 'Sam', 'message' => 'Hello']);
    $chosen = $scope === 'selected'
        ? ['selected_ids' => [$submission], 'query' => []]
        : ['selected_ids' => [], 'query' => ['filters' => ['form' => $form]]];

    $created = $this->controller->createExport(submissionsDataExportRoute('POST', 'exports', $chosen + [
        'scope' => $scope,
        'columns' => ['date', 'form', 'summary', 'email', 'name', 'message'],
        'format' => 'csv',
        'personal_data_acknowledged' => true,
    ]))->get_data();

    expect($created['ok'])->toBeTrue();
    $export = $created['data']['export'];
    $id = (int) $export['id'];
    $this->jobs[] = (int) $export['job_id'];

    // What the dialog does while a person waits: it asks for the export's step.
    $progress = $this->controller->advanceExport(
        submissionsDataExportRoute('POST', "exports/$id/advance", [], ['id' => $id])
    )->get_data()['data']['progress'];
    $this->files[] = (string) ($this->exports->file($id)['path'] ?? '');
    $artifact = $this->controller->downloadExport(
        submissionsDataExportRoute('GET', "exports/$id/download", [], ['id' => $id])
    )->get_data()['data']['artifact'];

    $submitted = substr((string) get_post($submission)->post_date, 0, 16);

    expect($progress)->toMatchArray(['state' => 'completed', 'processed' => 1, 'total' => 1])
        ->and($artifact['filename'])->toEndWith('.csv')
        ->and(base64_decode($artifact['content'], true))->toBe(
            "\xEF\xBB\xBF"
            . "Submitted,Form,Submission,Email,Name,Message\r\n"
            . '"' . $submitted . '",' . $form
            . ',"email: someone@example.com · name: Sam · message: Hello",someone@example.com,Sam,Hello' . "\r\n",
        );
})->with(['selected', 'filtered']);
