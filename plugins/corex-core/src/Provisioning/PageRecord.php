<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Provisioning;

defined('ABSPATH') || exit;

/**
 * What a page was before a kit changed it, and what the site's reading settings were (spec 115, FR-001
 * and FR-002). A kit apply writes these once, before it replaces or adopts a page; a reset reads them to
 * put the page and the front page back instead of deleting what the owner had.
 *
 * The page's record lives in its own meta, so it stays with the page whatever happens to the kit's list
 * of pages. The reading record is a `corex_*` option, which a reset removes with the kit's other
 * options once it has used it. This class only shapes the values; the kit writes them and the reset
 * command reads them.
 */
final class PageRecord
{
    public const META           = '_corex_kit_before';
    public const READING_OPTION = 'corex_kit_reading_before';
    public const TEMPLATE_META  = '_wp_page_template';

    /** The reading settings a kit can change. */
    public const READING_KEYS = ['show_on_front', 'page_on_front', 'page_for_posts'];

    /**
     * The fields of a page a restore puts back.
     *
     * @return array{title:string,content:string,template:string,status:string}
     */
    public static function of(\WP_Post $page, string $template): array
    {
        return [
            'title'    => (string) $page->post_title,
            'content'  => (string) $page->post_content,
            'template' => $template,
            'status'   => (string) $page->post_status,
        ];
    }

    /**
     * Whether a stored value is a page record this class wrote.
     */
    public static function isRecord(mixed $value): bool
    {
        return is_array($value)
            && isset($value['title'], $value['content'], $value['template'], $value['status'])
            && is_string($value['title'])
            && is_string($value['content'])
            && is_string($value['template'])
            && is_string($value['status']);
    }

    /**
     * The arguments for `wp_update_post()` that put a page back as it was recorded. WordPress takes one
     * level of slashes off a title and a content on the way in, so they are slashed here.
     *
     * @param array{title:string,content:string,template:string,status:string} $record
     *
     * @return array{ID:int,post_title:string,post_content:string,post_status:string}
     */
    public static function restoreArgs(int $pageId, array $record): array
    {
        return [
            'ID'           => $pageId,
            'post_title'   => wp_slash($record['title']),
            'post_content' => wp_slash($record['content']),
            'post_status'  => $record['status'],
        ];
    }

    /**
     * Whether a stored value is a reading record this class wrote.
     */
    public static function isReadingRecord(mixed $value): bool
    {
        if (! is_array($value)) {
            return false;
        }

        foreach (self::READING_KEYS as $key) {
            if (! array_key_exists($key, $value)) {
                return false;
            }
        }

        return true;
    }
}
