<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\DataModels;
defined('ABSPATH') || exit;

use RuntimeException;

/** Private immutable migration and rollback history. */
final class WpMigrationRunStore implements MigrationRunStore
{
    public const POST_TYPE = 'corex_migration';
    private const PAYLOAD = '_corex_migration_payload';
    private const HASH = '_corex_migration_hash';
    private const ACTOR = '_corex_migration_actor';

    public function registerPostType(): void
    {
        register_post_type(self::POST_TYPE, [
            'label' => __('CoreX Migrations', 'corex'), 'public' => false,
            'show_ui' => false, 'show_in_rest' => false, 'supports' => ['title'],
        ]);
    }

    public function create(MigrationRun $run): MigrationRun
    {
        $id = wp_insert_post([
            'post_type' => self::POST_TYPE, 'post_status' => 'private',
            'post_title' => sprintf('Migration — %s — %s', $run->definition->key, $run->createdAt->format('Y-m-d H:i:s')),
        ], true);
        if (is_wp_error($id) || (int) $id < 1) {
            throw new RuntimeException(__('CoreX could not create the migration run.', 'corex'));
        }
        $stored = $run->withId((int) $id);
        $this->persist($stored);

        return $stored;
    }

    public function attachJob(int $id, int $jobId): MigrationRun
    {
        $run = $this->find($id) ?? throw new RuntimeException(__('Migration run was not found.', 'corex'));
        $run = $run->withJob($jobId);
        $this->persist($run);

        return $run;
    }

    public function find(int $id): ?MigrationRun
    {
        if (get_post_type($id) !== self::POST_TYPE) {
            return null;
        }
        $payload = get_post_meta($id, self::PAYLOAD, true);

        return is_array($payload) ? MigrationRun::from([...$payload, 'id' => $id]) : null;
    }

    public function findByHash(string $hash): ?MigrationRun
    {
        $ids = get_posts([
            'post_type' => self::POST_TYPE, 'post_status' => 'private', 'posts_per_page' => 1,
            'fields' => 'ids', 'meta_key' => self::HASH, 'meta_value' => $hash,
        ]);

        return $ids === [] ? null : $this->find((int) $ids[0]);
    }

    public function finish(int $id, string $state, string $message): void
    {
        $run = $this->find($id) ?? throw new RuntimeException(__('Migration run was not found.', 'corex'));
        $this->persist($run->finished($state, $message));
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

        return array_values(array_filter(array_map(fn (int $id): ?MigrationRun => $this->find($id), array_map('intval', get_posts($args)))));
    }

    private function persist(MigrationRun $run): void
    {
        update_post_meta($run->id, self::PAYLOAD, $run->toArray());
        update_post_meta($run->id, self::HASH, $run->inputHash);
        update_post_meta($run->id, self::ACTOR, $run->actorId);
    }
}
