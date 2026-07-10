<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use RuntimeException;

/**
 * Private WordPress storage for export jobs, history, and protected CSV artifacts.
 */
final class WpSubmissionExportStore implements SubmissionExportStore
{
    public const POST_TYPE = 'corex_sub_export';

    private const PAYLOAD = '_corex_submission_export_payload';
    private const HASH = '_corex_submission_export_input_hash';
    private const ACTOR = '_corex_submission_export_actor';
    private const ARTIFACT = '_corex_submission_export_csv';

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

    public function saveArtifact(int $runId, string $csv, int $recordCount): void
    {
        if ($this->find($runId) === null) {
            throw new RuntimeException(__('Submission export was not found.', 'corex'));
        }
        update_post_meta($runId, self::ARTIFACT, $csv);
        update_post_meta($runId, '_corex_submission_exported_records', max(0, $recordCount));
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
