<?php

/**
 * @package Corex\Portfolio
 */

declare(strict_types=1);

namespace Corex\Portfolio\Blocks;

defined('ABSPATH') || exit;

use WP_Query;

/**
 * The WordPress-backed ProjectsProvider: a bounded, no-found-rows query of published
 * `corex_project` posts, mapped to {title, url, thumbnail} rows. The only place this
 * block touches WP_Query (Principle VI/III); kept thin so the renderer stays pure.
 */
final class WpProjectsProvider implements ProjectsProvider
{
    public const POST_TYPE = 'corex_project';

    /**
     * @return list<array{title:string,url:string,thumbnail:string}>
     */
    public function recent(int $count): array
    {
        $query = new WP_Query([
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => $count,
            'no_found_rows'  => true,
            'ignore_sticky_posts' => true,
        ]);

        $projects = [];

        foreach ($query->posts as $post) {
            $thumb = get_the_post_thumbnail_url($post, 'medium');

            $projects[] = [
                'title'     => (string) get_the_title($post),
                'url'       => (string) get_permalink($post),
                'thumbnail' => is_string($thumb) ? $thumb : '',
            ];
        }

        return $projects;
    }
}
