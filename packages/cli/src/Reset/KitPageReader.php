<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Reset;

defined('ABSPATH') || exit;

use Corex\Provisioning\PageDisposition;
use Corex\Provisioning\PageRecord;

/**
 * Reads, from WordPress, what a kit left for a reset to act on: the pages it tracked in
 * `corex_kit_seeded_pages`, each with how the kit persisted it and whether it recorded what the page was,
 * and whether it recorded the reading settings it changed (spec 031, spec 115). The read half of the
 * reset boundary; the pure planner decides what to do with it.
 */
final class KitPageReader
{
    public const SEEDED_OPTION = 'corex_kit_seeded_pages';

    /**
     * The tracked pages that still exist.
     *
     * @return list<ResetPage>
     */
    public function pages(): array
    {
        $pages = [];

        foreach ((array) get_option(self::SEEDED_OPTION, []) as $id) {
            $post = get_post((int) $id);

            if (! $post instanceof \WP_Post) {
                continue;
            }

            $pages[] = new ResetPage(
                id: $post->ID,
                title: (string) $post->post_title,
                persistedAs: (string) get_post_meta($post->ID, PageDisposition::META, true),
                hasRecord: PageRecord::isRecord(get_post_meta($post->ID, PageRecord::META, true)),
            );
        }

        return $pages;
    }

    public function hasReadingRecord(): bool
    {
        return PageRecord::isReadingRecord(get_option(PageRecord::READING_OPTION, null));
    }
}
