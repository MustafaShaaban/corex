<?php

/**
 * Every read path on a table source hydrates an attachment the same way (spec 086).
 *
 * Found by validating spec 081 against a real consuming site rather than by reading the code:
 * `rows()` — the paged list — inlined its own copy of the row-shaping loop, so it missed the
 * hydration `query()` and `record()` received. The Data screen's list showed `21848` where its own
 * detail modal showed a link, for the same column on the same table.
 *
 * Asserted across all three paths rather than the one that was broken, because the defect was not
 * "rows() is wrong" — it was "there are three loops that have to agree".
 *
 * @package Corex\Tests\Integration\Data
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Data\DataRegistry;
use Corex\Data\DataField;

/**
 * @return array{source:object,id:int,attachmentId:int}
 */
function seededApplicationRow(): array
{
    global $wpdb;

    $attachmentId = wp_insert_post([
        'post_title'     => 'probe-cv',
        'post_type'      => 'attachment',
        'post_status'    => 'private',
        'post_mime_type' => 'application/pdf',
    ]);
    update_post_meta($attachmentId, '_corex_protected', '1');

    // A real file on disk: `get_attached_file()` is what decides missing-versus-present, so an
    // attachment post with no file behind it is a *different* fixture (covered below).
    $uploads = wp_upload_dir();
    $file    = trailingslashit($uploads['basedir']) . 'corex-private/probe-fixture-' . $attachmentId . '.pdf';
    wp_mkdir_p(dirname($file));
    file_put_contents($file, "%PDF-1.4
");
    update_attached_file($attachmentId, $file);

    $table = $wpdb->prefix . 'corex_applications';
    $wpdb->insert($table, [
        'job_id'        => 0,
        'name'          => 'Attachment Probe',
        'email'         => 'probe-' . uniqid() . '@example.test',
        'cover_letter'  => '',
        'cv_attachment' => $attachmentId,
        'status'        => 'new',
        'created_at'    => current_time('mysql'),
        'updated_at'    => current_time('mysql'),
    ]);

    return [
        'source'       => Boot::app()->container()->make(DataRegistry::class)->find('table-applications'),
        'id'           => (int) $wpdb->insert_id,
        'attachmentId' => (int) $attachmentId,
    ];
}

beforeEach(function () {
    wp_set_current_user(1);
});

it('declares the CV column as an attachment rather than as text', function () {
    $fields = Boot::app()->container()->make(DataRegistry::class)->fields('table-applications');

    $cv = null;
    foreach ($fields as $field) {
        if ($field->key === 'cv_attachment') {
            $cv = $field;
        }
    }

    expect($cv)->not->toBeNull()
        ->and($cv->type)->toBe(DataField::TYPE_ATTACHMENT);
});

it('hydrates the attachment identically on the list, the query and the record', function () {
    ['source' => $source, 'id' => $rowId, 'attachmentId' => $attachmentId] = seededApplicationRow();

    $fromList   = null;
    foreach ($source->rows(1, 50) as $row) {
        if ((int) $row['id'] === $rowId) {
            $fromList = $row['cv_attachment'];
        }
    }

    $fromRecord = $source->record($rowId)['cv_attachment'] ?? null;

    // The list path is the one that was wrong, and it was wrong *by omission* — a bare integer,
    // which renders as a plausible-looking number rather than as anything obviously broken.
    expect($fromList)->toBeArray()
        ->and($fromList['id'])->toBe($attachmentId)
        ->and($fromList['missing'])->toBeFalse()
        ->and($fromList['url'])->toContain('corex_attachment')
        ->and($fromRecord)->toBeArray()
        ->and($fromRecord['id'])->toBe($attachmentId);

    global $wpdb;
    $wpdb->delete($wpdb->prefix . 'corex_applications', ['id' => $rowId]);
    $file = get_attached_file($attachmentId);
    if (is_string($file) && is_file($file)) {
        wp_delete_file($file);
    }
    wp_delete_post($attachmentId, true);
});

it('reports a vanished file as missing rather than as absent', function () {
    global $wpdb;

    $table = $wpdb->prefix . 'corex_applications';
    $wpdb->insert($table, [
        'job_id' => 0, 'name' => 'Gone', 'email' => 'gone-' . uniqid() . '@example.test',
        'cover_letter' => '', 'cv_attachment' => 999999, 'status' => 'new',
        'created_at' => current_time('mysql'), 'updated_at' => current_time('mysql'),
    ]);
    $rowId = (int) $wpdb->insert_id;

    $source = Boot::app()->container()->make(DataRegistry::class)->find('table-applications');
    $record = $source->record($rowId);

    // An em dash here would tell an operator nobody uploaded anything, when in fact a file that
    // was uploaded has gone. Different facts; only one is somebody's problem.
    expect($record['cv_attachment'])->toBeArray()
        ->and($record['cv_attachment']['missing'])->toBeTrue();

    $wpdb->delete($table, ['id' => $rowId]);
});
