<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

/**
 * Private WordPress persistence for Email Studio assets and immutable events.
 */
final class WpEmailStudioStore implements EmailStudioStore
{
    public const POST_TYPE = 'corex_email_asset';

    private const META_TYPE    = '_corex_email_asset_type';
    private const META_SLUG    = '_corex_email_asset_slug';
    private const META_PAYLOAD = '_corex_email_asset_payload';
    private const MAX_RECORDS  = 500;

    public function registerPostType(): void
    {
        register_post_type(self::POST_TYPE, [
            'label'           => __('Email Studio assets', 'corex'),
            'public'          => false,
            'show_ui'         => false,
            'supports'        => ['title'],
            'capability_type' => 'post',
            'map_meta_cap'    => true,
        ]);
    }

    public function create(string $type, string $slug, string $name, int $parentId, array $payload): int
    {
        $this->assertKey($type, 'type');
        $this->assertSlug($slug);

        $id = wp_insert_post([
            'post_type'   => self::POST_TYPE,
            'post_status' => 'private',
            'post_title'  => $name,
            'post_name'   => sanitize_title($slug),
            'post_parent' => max(0, $parentId),
        ], true);

        if (is_wp_error($id)) {
            throw new \DomainException($id->get_error_message());
        }

        update_post_meta($id, self::META_TYPE, $type);
        update_post_meta($id, self::META_SLUG, $slug);
        update_post_meta($id, self::META_PAYLOAD, $payload);

        return $id;
    }

    public function update(int $id, string $name, array $payload): bool
    {
        if ($this->find($id) === null) {
            return false;
        }

        $updated = wp_update_post([
            'ID'         => $id,
            'post_title' => $name,
        ], true);
        if (is_wp_error($updated) || $updated === 0) {
            return false;
        }

        update_post_meta($id, self::META_PAYLOAD, $payload);

        return true;
    }

    public function find(int $id): ?array
    {
        $post = get_post($id);
        if (! $post instanceof \WP_Post || $post->post_type !== self::POST_TYPE) {
            return null;
        }

        return $this->record($post);
    }

    public function findBySlug(string $type, string $slug): ?array
    {
        $records = $this->query($type, null, $slug, 1);

        return $records[0] ?? null;
    }

    public function all(string $type, ?int $parentId = null): array
    {
        return $this->query($type, $parentId, null, self::MAX_RECORDS);
    }

    /** @return list<array{id:int,type:string,slug:string,name:string,parentId:int,payload:array<string,mixed>}> */
    private function query(string $type, ?int $parentId, ?string $slug, int $limit): array
    {
        $this->assertKey($type, 'type');
        $metaQuery = [[
            'key'     => self::META_TYPE,
            'value'   => $type,
            'compare' => '=',
        ]];
        if ($slug !== null) {
            $this->assertSlug($slug);
            $metaQuery[] = [
                'key'     => self::META_SLUG,
                'value'   => $slug,
                'compare' => '=',
            ];
        }

        $arguments = [
            'post_type'              => self::POST_TYPE,
            'post_status'            => 'private',
            'posts_per_page'         => $limit,
            'orderby'                => 'ID',
            'order'                  => 'ASC',
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
            'meta_query'             => $metaQuery,
        ];
        if ($parentId !== null) {
            $arguments['post_parent'] = max(0, $parentId);
        }

        $query = new \WP_Query($arguments);

        return array_map($this->record(...), $query->posts);
    }

    /** @return array{id:int,type:string,slug:string,name:string,parentId:int,payload:array<string,mixed>} */
    private function record(\WP_Post $post): array
    {
        $payload = get_post_meta($post->ID, self::META_PAYLOAD, true);

        return [
            'id'       => $post->ID,
            'type'     => (string) get_post_meta($post->ID, self::META_TYPE, true),
            'slug'     => (string) get_post_meta($post->ID, self::META_SLUG, true),
            'name'     => $post->post_title,
            'parentId' => (int) $post->post_parent,
            'payload'  => is_array($payload) ? $payload : [],
        ];
    }

    private function assertKey(string $value, string $label): void
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $value) !== 1) {
            /* translators: %s: Email Studio field name. */
            throw new \InvalidArgumentException(sprintf(__('Email Studio %s is invalid.', 'corex'), $label));
        }
    }

    private function assertSlug(string $slug): void
    {
        if ($slug === '' || strlen($slug) > 191 || preg_match('/^[a-z0-9][a-z0-9_.-]*$/i', $slug) !== 1) {
            throw new \InvalidArgumentException(__('Email Studio slug is invalid.', 'corex'));
        }
    }
}
