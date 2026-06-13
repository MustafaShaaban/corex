<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

use WP_Query;

/**
 * The WordPress boundary for reading form submissions: queries `corex_submission` posts
 * (newest first, paginated) and reads their `corex_form_slug` + `corex_field_*` meta. The
 * row-shaping lives in SubmissionsSource; this is the only class here that touches WP_Query.
 */
final class WpSubmissionsReader implements SubmissionsReader
{
    /**
     * @return list<array{id:int,date:string,form:string,fields:array<string,mixed>}>
     */
    public function page(int $page, int $perPage): array
    {
        $query = new WP_Query([
            'post_type'      => 'corex_submission',
            'post_status'    => 'private',
            'posts_per_page' => min(max($perPage, 1), 100),
            'paged'          => max($page, 1),
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => false,
        ]);

        $rows = [];
        foreach ($query->posts as $post) {
            $meta   = get_post_meta($post->ID);
            $fields = [];
            foreach ($meta as $key => $value) {
                if (str_starts_with($key, 'corex_field_')) {
                    $fields[substr($key, strlen('corex_field_'))] = maybe_unserialize($value[0] ?? '');
                }
            }

            $rows[] = [
                'id'     => (int) $post->ID,
                'date'   => (string) $post->post_date,
                'form'   => (string) ($meta['corex_form_slug'][0] ?? ''),
                'fields' => $fields,
            ];
        }

        return $rows;
    }

    public function total(): int
    {
        $query = new WP_Query([
            'post_type'      => 'corex_submission',
            'post_status'    => 'private',
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ]);

        return (int) $query->found_posts;
    }

    public function trash(int $id): bool
    {
        return get_post_type($id) === 'corex_submission' && wp_trash_post($id) !== false;
    }

    /**
     * @return list<array{id:int,date:string,form:string,fields:array<string,mixed>}>
     */
    public function query(DataQuery $query): array
    {
        $found = new WP_Query($this->args($query, false));

        $rows = [];
        foreach ($found->posts as $post) {
            $rows[] = $this->shape($post);
        }

        return $rows;
    }

    public function count(DataQuery $query): int
    {
        $args                   = $this->args($query, true);
        $args['fields']         = 'ids';
        $args['posts_per_page'] = 1;

        return (int) (new WP_Query($args))->found_posts;
    }

    /**
     * @return array{id:int,date:string,form:string,fields:array<string,mixed>}|null
     */
    public function find(int $id): ?array
    {
        $post = get_post($id);

        if ($post === null || $post->post_type !== 'corex_submission') {
            return null;
        }

        return $this->shape($post);
    }

    /**
     * Build the WP_Query args for a DataQuery: a `form` filter via the slug meta, a date
     * sort, and pagination. The free-text search uses WP_Query's `s` (post fields); meta
     * value search is a documented limitation of the post-meta driver (a custom-table
     * driver would index it). All values are passed as args (no SQL string-building).
     *
     * @return array<string,mixed>
     */
    private function args(DataQuery $query, bool $forCount): array
    {
        $args = [
            'post_type'      => 'corex_submission',
            'post_status'    => 'private',
            'posts_per_page' => $forCount ? 1 : $query->perPage,
            'paged'          => $query->page,
            'orderby'        => 'date',
            'order'          => $query->sortColumn === 'date' && $query->sortDir === 'asc' ? 'ASC' : 'DESC',
            'no_found_rows'  => false,
        ];

        if ($query->search !== '') {
            $args['s'] = $query->search;
        }

        $form = $query->filters['form'] ?? '';
        if ($form !== '') {
            $args['meta_query'] = [
                [
                    'key'     => 'corex_form_slug',
                    'value'   => $form,
                    'compare' => '=',
                ],
            ];
        }

        return $args;
    }

    /**
     * @return array{id:int,date:string,form:string,fields:array<string,mixed>}
     */
    private function shape(\WP_Post $post): array
    {
        $meta   = get_post_meta($post->ID);
        $fields = [];
        foreach ($meta as $key => $value) {
            if (str_starts_with($key, 'corex_field_')) {
                $fields[substr($key, strlen('corex_field_'))] = maybe_unserialize($value[0] ?? '');
            }
        }

        return [
            'id'     => (int) $post->ID,
            'date'   => (string) $post->post_date,
            'form'   => (string) ($meta['corex_form_slug'][0] ?? ''),
            'fields' => $fields,
        ];
    }
}
