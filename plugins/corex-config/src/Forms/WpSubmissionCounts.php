<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Forms;

defined('ABSPATH') || exit;

use Corex\Forms\Catalog\SubmissionCounts;

/**
 * How many submissions each form has, counted once for the whole catalog.
 *
 * The catalog renders every form on one screen, so a per-form count would be an N+1 across a list
 * that only ever grows. One grouped, prepared, bounded query answers all of them, and the result is
 * cached for a minute — the number is a scale cue on an admin list, not a ledger, and it is written
 * far more often than a screen is opened.
 */
final class WpSubmissionCounts implements SubmissionCounts
{
    private const CACHE_KEY   = 'corex_form_submission_counts';
    private const CACHE_GROUP = 'corex';
    private const CACHE_TTL   = MINUTE_IN_SECONDS;

    /** Enough for any realistic number of distinct forms on one site. */
    private const MAX_FORMS = 200;

    /** @return array<string,int> */
    public function perFormSlug(): array
    {
        $cached = wp_cache_get(self::CACHE_KEY, self::CACHE_GROUP);

        if (is_array($cached)) {
            return $cached;
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- grouped aggregate over meta; WP_Query cannot GROUP BY, and the result is cached below.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta.meta_value AS form_slug, COUNT(*) AS total
                   FROM {$wpdb->postmeta} AS meta
                   INNER JOIN {$wpdb->posts} AS posts ON posts.ID = meta.post_id
                  WHERE meta.meta_key = %s
                    AND posts.post_type = %s
                    AND posts.post_status != %s
               GROUP BY meta.meta_value
                  LIMIT %d",
                'corex_form_slug',
                'corex_submission',
                'trash',
                self::MAX_FORMS,
            ),
            ARRAY_A,
        );

        $counts = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            $slug = sanitize_key((string) ($row['form_slug'] ?? ''));

            if ($slug !== '') {
                $counts[$slug] = (int) ($row['total'] ?? 0);
            }
        }

        wp_cache_set(self::CACHE_KEY, $counts, self::CACHE_GROUP, self::CACHE_TTL);

        return $counts;
    }
}
