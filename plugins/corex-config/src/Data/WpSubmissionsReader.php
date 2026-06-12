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
}
