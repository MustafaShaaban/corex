<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Blog;

defined('ABSPATH') || exit;

/**
 * Pure model for the Blog Pro reference surface (spec 067, design: "Corex Blog Pro & Analytics").
 * Blog Pro is a future add-on shown now as a visible, honestly-gated REFERENCE surface — it never
 * fabricates live metrics. The Analytics tab is an explicitly-labelled sample layout (fixed reference
 * figures, "not live"); the Editorial, Comments, and Authors tabs are shaped from REAL WordPress facts
 * (post-status counts, comment counts, and real authors) passed in by the boundary, so those tabs are
 * truthful. WordPress-free, so it is unit-testable.
 */
final class BlogProModel
{
    public const ANALYTICS = 'analytics';
    public const EDITORIAL  = 'editorial';
    public const COMMENTS   = 'comments';
    public const AUTHORS    = 'authors';

    /**
     * @return array<string,string>
     */
    public function tabs(): array
    {
        return [
            self::ANALYTICS => __('Analytics', 'corex'),
            self::EDITORIAL => __('Editorial queue', 'corex'),
            self::COMMENTS  => __('Comments', 'corex'),
            self::AUTHORS   => __('Authors', 'corex'),
        ];
    }

    public function activeTab(string $tab): string
    {
        return array_key_exists($tab, $this->tabs()) ? $tab : self::ANALYTICS;
    }

    /**
     * The Analytics tab is a REFERENCE layout with clearly-labelled sample figures — never live
     * metrics (no first-party analytics engine exists yet). The `reference` flag drives the honest
     * "sample data, not live" labelling in the view.
     *
     * @return array{reference:bool,stats:list<array{label:string,value:string,trend:string}>}
     */
    public function analyticsReference(): array
    {
        return [
            'reference' => true,
            'stats' => [
                ['label' => __('Views', 'corex'), 'value' => '12,480', 'trend' => '+8.2%'],
                ['label' => __('Reads', 'corex'), 'value' => '7,140', 'trend' => '+5.1%'],
                ['label' => __('Avg. read', 'corex'), 'value' => '2m 41s', 'trend' => '+0.3%'],
                ['label' => __('Comments', 'corex'), 'value' => '312', 'trend' => '-2.0%'],
            ],
        ];
    }

    /**
     * Editorial queue from REAL post-status counts (draft/pending/future/publish). Truthful.
     *
     * @param array{draft:int,pending:int,future:int,publish:int} $counts
     *
     * @return list<array{label:string,count:int,tone:string}>
     */
    public function editorial(array $counts): array
    {
        return [
            ['label' => __('Pending review', 'corex'), 'count' => max(0, (int) ($counts['pending'] ?? 0)), 'tone' => 'warning'],
            ['label' => __('Drafts', 'corex'), 'count' => max(0, (int) ($counts['draft'] ?? 0)), 'tone' => 'neutral'],
            ['label' => __('Scheduled', 'corex'), 'count' => max(0, (int) ($counts['future'] ?? 0)), 'tone' => 'info'],
            ['label' => __('Published', 'corex'), 'count' => max(0, (int) ($counts['publish'] ?? 0)), 'tone' => 'success'],
        ];
    }

    /**
     * Comments summary from REAL comment counts. Truthful; moderation happens on the native screen.
     *
     * @param array{approved:int,moderated:int,spam:int,trash:int} $counts
     *
     * @return list<array{label:string,count:int,tone:string}>
     */
    public function comments(array $counts): array
    {
        return [
            ['label' => __('Awaiting moderation', 'corex'), 'count' => max(0, (int) ($counts['moderated'] ?? 0)), 'tone' => 'warning'],
            ['label' => __('Approved', 'corex'), 'count' => max(0, (int) ($counts['approved'] ?? 0)), 'tone' => 'success'],
            ['label' => __('Spam', 'corex'), 'count' => max(0, (int) ($counts['spam'] ?? 0)), 'tone' => 'danger'],
            ['label' => __('Trash', 'corex'), 'count' => max(0, (int) ($counts['trash'] ?? 0)), 'tone' => 'neutral'],
        ];
    }

    /**
     * Authors from REAL users who can author posts + their real published-post counts.
     *
     * @param list<array{name:string,posts:int}> $authors
     *
     * @return list<array{name:string,posts:int}>
     */
    public function authors(array $authors): array
    {
        $clean = [];
        foreach ($authors as $author) {
            $clean[] = [
                'name'  => (string) ($author['name'] ?? ''),
                'posts' => max(0, (int) ($author['posts'] ?? 0)),
            ];
        }

        return $clean;
    }
}
