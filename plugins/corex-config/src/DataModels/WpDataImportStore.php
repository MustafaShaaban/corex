<?php

/** @package Corex\Config */

declare(strict_types=1);

namespace Corex\Config\DataModels;

defined('ABSPATH') || exit;

use RuntimeException;

/** Private WordPress persistence for immutable import dry runs and job history. */
final class WpDataImportStore implements DataImportStore
{
    public const POST_TYPE = 'corex_data_import';

    private const PAYLOAD = '_corex_data_import_payload';
    private const HASH = '_corex_data_import_hash';
    private const ACTOR = '_corex_data_import_actor';

    public function registerPostType(): void
    {
        register_post_type(self::POST_TYPE, [
            'label' => __('CoreX Data Imports', 'corex'),
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'supports' => ['title'],
        ]);
    }

    public function create(DataImportRun $run): DataImportRun
    {
        $id = wp_insert_post([
            'post_type' => self::POST_TYPE,
            'post_status' => 'private',
            'post_title' => sprintf('Data import — %s', $run->createdAt->format('Y-m-d H:i:s')),
        ], true);
        if (is_wp_error($id) || (int) $id < 1) {
            throw new RuntimeException(__('CoreX could not create the data import.', 'corex'));
        }
        $stored = $run->withId((int) $id);
        $this->persist($stored);

        return $stored;
    }

    public function find(int $id): ?DataImportRun
    {
        if (get_post_type($id) !== self::POST_TYPE) {
            return null;
        }
        $payload = get_post_meta($id, self::PAYLOAD, true);

        return is_array($payload) ? DataImportRun::from([...$payload, 'id' => $id]) : null;
    }

    public function findByHash(string $inputHash): ?DataImportRun
    {
        $ids = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'private',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => self::HASH,
            'meta_value' => $inputHash,
        ]);

        return $ids === [] ? null : $this->find((int) $ids[0]);
    }

    public function attachJob(int $id, int $jobId): DataImportRun
    {
        $run = $this->find($id) ?? throw new RuntimeException(__('Data import was not found.', 'corex'));
        $run = $run->withJob($jobId);
        $this->persist($run);

        return $run;
    }

    public function finish(int $id, int $succeeded, int $failed): void
    {
        $run = $this->find($id) ?? throw new RuntimeException(__('Data import was not found.', 'corex'));
        $this->persist($run->withResult($succeeded, $failed));
    }

    private function persist(DataImportRun $run): void
    {
        update_post_meta($run->id, self::PAYLOAD, $run->toArray());
        update_post_meta($run->id, self::HASH, $run->inputHash);
        update_post_meta($run->id, self::ACTOR, $run->actorId);
    }
}
