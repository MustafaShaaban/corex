<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui\Blocks;

defined('ABSPATH') || exit;

use WP_Query;

/**
 * The WordPress-backed posts provider: the most recent published posts via a single
 * bounded query.
 */
final class WpPostsProvider implements PostsProvider
{
    /**
     * @return list<array{title:string,url:string}>
     */
    public function recent(int $count): array
    {
        $query = new WP_Query([
            'post_type'           => 'post',
            'post_status'         => 'publish',
            'posts_per_page'      => $count,
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
        ]);

        $items = [];

        foreach ($query->posts as $post) {
            $items[] = [
                'title' => (string) get_the_title($post),
                'url'   => (string) get_permalink($post),
            ];
        }

        wp_reset_postdata();

        return $items;
    }
}
