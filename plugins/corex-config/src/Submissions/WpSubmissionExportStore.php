<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Corex\Config\Export\ExportDirectory;
use DateTimeImmutable;
use RuntimeException;

/**
 * Private WordPress storage for export jobs and history, and for where each export's file is.
 */
final class WpSubmissionExportStore implements SubmissionExportStore
{
    public const POST_TYPE = 'corex_sub_export';

    public function __construct(private readonly ExportDirectory $directory)
    {
    }

    private const PAYLOAD = '_corex_submission_export_payload';
    private const HASH = '_corex_submission_export_input_hash';
    private const ACTOR = '_corex_submission_export_actor';
    private const ARTIFACT = '_corex_submission_export_csv';
    private const FILE = '_corex_submission_export_file';

    public function registerPostType(): void
    {
        register_post_type(self::POST_TYPE, [
            'label' => __('CoreX Submission Exports', 'corex'),
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'supports' => ['title'],
        ]);
    }

    public function create(SubmissionExportRun $run): SubmissionExportRun
    {
        $id = wp_insert_post([
            'post_type' => self::POST_TYPE,
            'post_status' => 'private',
            'post_title' => sprintf('Submission export — %s', $run->createdAt->format('Y-m-d H:i:s')),
        ], true);
        if (is_wp_error($id) || (int) $id < 1) {
            throw new RuntimeException(__('CoreX could not create the submission export.', 'corex'));
        }

        $stored = $run->withId((int) $id);
        $this->persist($stored);

        return $stored;
    }

    public function attachJob(int $runId, int $jobId): SubmissionExportRun
    {
        $run = $this->find($runId) ?? throw new RuntimeException(__('Submission export was not found.', 'corex'));
        $run = $run->withJob($jobId);
        $this->persist($run);

        return $run;
    }

    public function find(int $runId): ?SubmissionExportRun
    {
        if (get_post_type($runId) !== self::POST_TYPE) {
            return null;
        }

        return $this->hydrate($runId);
    }

    public function findByHash(string $inputHash): ?SubmissionExportRun
    {
        $ids = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'private',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => self::HASH,
            'meta_value' => $inputHash,
        ]);

        return $ids === [] ? null : $this->hydrate((int) $ids[0]);
    }

    public function history(SubmissionAccessScope $scope, int $limit): array
    {
        $args = [
            'post_type' => self::POST_TYPE,
            'post_status' => 'private',
            'posts_per_page' => min(100, max(1, $limit)),
            'fields' => 'ids',
            'orderby' => 'date',
            'order' => 'DESC',
        ];
        if (! $scope->manageAll) {
            $args['meta_key'] = self::ACTOR;
            $args['meta_value'] = $scope->actorId;
        }

        return array_values(array_filter(array_map(
            fn (int $id): ?SubmissionExportRun => $this->hydrate($id),
            array_map('intval', get_posts($args)),
        )));
    }

    public function saveFile(int $runId, array $file, int $recordCount): void
    {
        if ($this->find($runId) === null) {
            throw new RuntimeException(__('Submission export was not found.', 'corex'));
        }
        // Only the file's name is kept. A full path would not survive: WordPress strips
        // backslashes from stored meta, which is every separator of a Windows path, and an
        // absolute path stops being true the day a site is moved.
        update_post_meta($runId, self::FILE, ['name' => basename($file['path'])] + array_diff_key($file, ['path' => true]));
        update_post_meta($runId, '_corex_submission_exported_records', max(0, $recordCount));
        // Kept on the run, so the history can still say how large a file was once it is gone.
        $this->persist($this->hydrate($runId)->withFileSize(is_file($file['path']) ? (int) filesize($file['path']) : 0));
    }

    public function removeFile(int $runId, string $reason, int $actorId): SubmissionExportRun
    {
        $run = $this->find($runId) ?? throw new RuntimeException(__('Submission export was not found.', 'corex'));
        $file = $this->file($runId);
        if ($file !== null && is_file($file['path'])) {
            wp_delete_file($file['path']);
        }
        delete_post_meta($runId, self::FILE);
        delete_post_meta($runId, self::ARTIFACT);

        $run = $run->withoutFile($reason, $actorId, new DateTimeImmutable('now'));
        $this->persist($run);

        return $run;
    }

    public function holdingFilesBefore(DateTimeImmutable $cutoff, int $limit): array
    {
        $ids = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'private',
            'posts_per_page' => max(1, $limit),
            'fields' => 'ids',
            'orderby' => 'date',
            'order' => 'ASC',
            'no_found_rows' => true,
            'date_query' => [['before' => $cutoff->format('Y-m-d H:i:s'), 'column' => 'post_date_gmt']],
            'meta_query' => [
                'relation' => 'OR',
                ['key' => self::FILE, 'compare' => 'EXISTS'],
                ['key' => self::ARTIFACT, 'compare' => 'EXISTS'],
            ],
        ]);

        return array_values(array_filter(array_map(
            fn (int $id): ?SubmissionExportRun => $this->hydrate($id),
            array_map('intval', $ids),
        )));
    }

    public function file(int $runId): ?array
    {
        if ($this->find($runId) === null) {
            return null;
        }
        $file = get_post_meta($runId, self::FILE, true);
        if (! is_array($file) || ! is_string($file['name'] ?? null) || $file['name'] === '') {
            return null;
        }

        $path = $this->directory->path() . '/' . basename($file['name']);
        // A file can go without CoreX removing it: a site moved without its private uploads, a
        // host that clears them. The history then says there is no file, and offers none.
        if (! is_file($path)) {
            return null;
        }

        return [
            'path' => $path,
            'extension' => (string) ($file['extension'] ?? ''),
            'content_type' => (string) ($file['content_type'] ?? ''),
            'subject' => (string) ($file['subject'] ?? ''),
        ];
    }

    public function artifact(int $runId): ?string
    {
        if ($this->find($runId) === null) {
            return null;
        }
        $csv = get_post_meta($runId, self::ARTIFACT, true);

        return is_string($csv) ? $csv : null;
    }

    private function persist(SubmissionExportRun $run): void
    {
        update_post_meta($run->id, self::PAYLOAD, $run->toArray());
        update_post_meta($run->id, self::HASH, $run->inputHash);
        update_post_meta($run->id, self::ACTOR, $run->actorId);
    }

    private function hydrate(int $id): ?SubmissionExportRun
    {
        $payload = get_post_meta($id, self::PAYLOAD, true);
        if (! is_array($payload)) {
            return null;
        }

        return SubmissionExportRun::from([...$payload, 'id' => $id]);
    }
}
