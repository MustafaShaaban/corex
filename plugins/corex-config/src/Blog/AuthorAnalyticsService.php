<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Blog;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * Projects native WordPress authors with first-party Blog analytics.
 */
final class AuthorAnalyticsService
{
    private const AUTHOR_LIMIT = 20;
    private const POSTS_PER_AUTHOR = 100;

    public function __construct(private readonly BlogAnalyticsService $analytics)
    {
    }

    /**
     * @return list<array{name:string,role:string,post_count:int,views:int,reads:int,engagement:float}>
     */
    public function authors(DateTimeImmutable $since, DateTimeImmutable $until): array
    {
        $users = get_users([
            // 'who' => 'authors' was deprecated in WP 5.9; 'capability' selects the
            // same author-capable users without emitting a deprecation notice.
            'capability' => ['edit_posts'],
            'number' => self::AUTHOR_LIMIT,
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);

        $authors = [];
        foreach (is_array($users) ? $users : [] as $user) {
            $authors[] = $this->author((object) $user, $since, $until);
        }

        return $authors;
    }

    /**
     * @return array{name:string,role:string,post_count:int,views:int,reads:int,engagement:float}
     */
    private function author(object $user, DateTimeImmutable $since, DateTimeImmutable $until): array
    {
        $userId = (int) ($user->ID ?? 0);
        $totals = $this->totals($this->postIds($userId), $since, $until);

        return [
            'name' => (string) ($user->display_name ?? ''),
            'role' => $this->role($user),
            'post_count' => (int) count_user_posts($userId, 'post', true),
            'views' => $totals['views'],
            'reads' => $totals['reads'],
            'engagement' => $totals['views'] === 0 ? 0.0 : round(($totals['reads'] / $totals['views']) * 100, 1),
        ];
    }

    /**
     * @return list<int>
     */
    private function postIds(int $userId): array
    {
        $posts = get_posts([
            'author' => $userId,
            'post_type' => 'post',
            'post_status' => 'publish',
            'fields' => 'ids',
            'numberposts' => self::POSTS_PER_AUTHOR,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        return array_values(array_map('intval', is_array($posts) ? $posts : []));
    }

    /**
     * @param list<int> $postIds
     *
     * @return array{views:int,reads:int}
     */
    private function totals(array $postIds, DateTimeImmutable $since, DateTimeImmutable $until): array
    {
        $views = 0;
        $reads = 0;
        foreach ($postIds as $postId) {
            $aggregate = $this->analytics->aggregate($postId, $since, $until);
            $views += $aggregate->views;
            $reads += $aggregate->reads;
        }

        return ['views' => $views, 'reads' => $reads];
    }

    private function role(object $user): string
    {
        $roles = $user->roles ?? [];

        return is_array($roles) && $roles !== [] ? (string) reset($roles) : 'author';
    }
}
