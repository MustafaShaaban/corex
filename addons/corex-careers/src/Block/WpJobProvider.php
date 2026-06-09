<?php

/**
 * @package Corex\Careers
 */

declare(strict_types=1);

namespace Corex\Careers\Block;

defined('ABSPATH') || exit;

use WP_Query;

/**
 * The WordPress-backed job provider: published `corex_job` posts with their
 * department/location/type terms, via a single bounded query.
 */
final class WpJobProvider implements JobProvider
{
    /**
     * @return list<array{title:string,url:string,department:string,location:string,type:string}>
     */
    public function openJobs(int $count): array
    {
        $query = new WP_Query([
            'post_type'      => 'corex_job',
            'post_status'    => 'publish',
            'posts_per_page' => $count,
            'no_found_rows'  => true,
        ]);

        $jobs = [];

        foreach ($query->posts as $post) {
            $jobs[] = [
                'title'      => (string) get_the_title($post),
                'url'        => (string) get_permalink($post),
                'department' => $this->term($post->ID, 'job_department'),
                'location'   => $this->term($post->ID, 'job_location'),
                'type'       => $this->term($post->ID, 'job_type'),
            ];
        }

        wp_reset_postdata();

        return $jobs;
    }

    private function term(int $postId, string $taxonomy): string
    {
        $terms = get_the_terms($postId, $taxonomy);

        return is_array($terms) && $terms !== [] ? (string) $terms[0]->name : '';
    }
}
