<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\DataModels;
defined('ABSPATH') || exit;

use RuntimeException;

/** Private WordPress export history and binary-safe artifact persistence. */
final class WpDataExportStore implements DataExportStore
{
    public const POST_TYPE = 'corex_data_export';
    private const PAYLOAD = '_corex_data_export_payload';
    private const HASH = '_corex_data_export_hash';
    private const ACTOR = '_corex_data_export_actor';
    private const ARTIFACT = '_corex_data_export_artifact';

    public function registerPostType(): void
    {
        register_post_type(self::POST_TYPE, [
            'label' => __('CoreX Data Exports', 'corex'), 'public' => false,
            'show_ui' => false, 'show_in_rest' => false, 'supports' => ['title'],
        ]);
    }

    public function create(DataExportRun $run): DataExportRun
    {
        $id = wp_insert_post([
            'post_type' => self::POST_TYPE, 'post_status' => 'private',
            'post_title' => sprintf('Data export — %s', $run->createdAt->format('Y-m-d H:i:s')),
        ], true);
        if (is_wp_error($id) || (int) $id < 1) {
            throw new RuntimeException(__('CoreX could not create the data export.', 'corex'));
        }
        $stored = $run->withId((int) $id);
        $this->persist($stored);

        return $stored;
    }

    public function attachJob(int $id, int $jobId): DataExportRun
    {
        $run = $this->find($id) ?? throw new RuntimeException(__('Data export was not found.', 'corex'));
        $run = $run->withJob($jobId);
        $this->persist($run);

        return $run;
    }

    public function find(int $id): ?DataExportRun
    {
        if (get_post_type($id) !== self::POST_TYPE) {
            return null;
        }
        $payload = get_post_meta($id, self::PAYLOAD, true);

        return is_array($payload) ? DataExportRun::from([...$payload, 'id' => $id]) : null;
    }

    public function findByHash(string $hash): ?DataExportRun
    {
        $ids = get_posts([
            'post_type' => self::POST_TYPE, 'post_status' => 'private', 'posts_per_page' => 1,
            'fields' => 'ids', 'meta_key' => self::HASH, 'meta_value' => $hash,
        ]);

        return $ids === [] ? null : $this->find((int) $ids[0]);
    }

    public function history(int $actorId, bool $manageAll, int $limit): array
    {
        $args = [
            'post_type' => self::POST_TYPE, 'post_status' => 'private',
            'posts_per_page' => min(100, max(1, $limit)), 'fields' => 'ids',
            'orderby' => 'date', 'order' => 'DESC',
        ];
        if (! $manageAll) {
            $args['meta_key'] = self::ACTOR;
            $args['meta_value'] = $actorId;
        }

        return array_values(array_filter(array_map(fn (int $id): ?DataExportRun => $this->find($id), array_map('intval', get_posts($args)))));
    }

    public function saveArtifact(int $id, string $artifact): void
    {
        if ($this->find($id) === null) {
            throw new RuntimeException(__('Data export was not found.', 'corex'));
        }
        update_post_meta($id, self::ARTIFACT, base64_encode($artifact));
    }

    public function artifact(int $id): ?string
    {
        if ($this->find($id) === null) {
            return null;
        }
        $encoded = get_post_meta($id, self::ARTIFACT, true);
        if (! is_string($encoded) || $encoded === '') {
            return null;
        }
        $artifact = base64_decode($encoded, true);

        return is_string($artifact) ? $artifact : null;
    }

    public function finish(int $id, int $rows): void
    {
        $run = $this->find($id) ?? throw new RuntimeException(__('Data export was not found.', 'corex'));
        $this->persist($run->completed($rows));
    }

    private function persist(DataExportRun $run): void
    {
        update_post_meta($run->id, self::PAYLOAD, $run->toArray());
        update_post_meta($run->id, self::HASH, $run->inputHash);
        update_post_meta($run->id, self::ACTOR, $run->actorId);
    }
}
