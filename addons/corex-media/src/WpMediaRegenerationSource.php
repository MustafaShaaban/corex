<?php

/**
 * @package Corex\Media
 */

declare(strict_types=1);

namespace Corex\Media;

use WP_Query;

defined('ABSPATH') || exit;

/**
 * The WordPress-backed batch source for {@see MediaRegenerationJob}: it counts image attachments
 * and converts one offset-bounded batch through the pure {@see WebpRegenerator} plan and the
 * {@see WebpConverter}. It mirrors the {@see MediaCommand} backfill: it never overwrites an existing
 * WebP sibling or the original, and it measures and tracks each WebP it writes, so delivery may
 * serve it and `reset-webp` knows CoreX made it.
 *
 * Spec 119 (US-SET-06): an upload whose file is missing or cannot be read, or that the image
 * library refuses to convert, is a failure with its file name and the reason, so the Settings
 * page can list it. It used to be skipped without a word.
 */
final class WpMediaRegenerationSource implements MediaRegenerationSource
{
    private const MIME_TYPES = ['image/jpeg', 'image/png'];

    public function __construct(
        private readonly WebpRegenerator $regenerator,
        private readonly WebpConverter $converter,
        private readonly ImageCapability $capability,
        private readonly MediaSettings $settings,
    ) {
    }

    public function total(): int
    {
        $query = new WP_Query([
            'post_type'      => 'attachment',
            'post_mime_type' => self::MIME_TYPES,
            'post_status'    => 'inherit',
            'fields'         => 'ids',
            'posts_per_page' => 1,
        ]);

        return (int) $query->found_posts;
    }

    /**
     * @return array{succeeded:int,failed:int,failures:list<array{file:string,reason:string}>}
     */
    public function convertBatch(int $offset, int $limit): array
    {
        $failures = [];
        $items    = [];

        foreach ($this->ids(max(0, $offset), max(1, $limit)) as $id) {
            $path = (string) get_attached_file($id);
            $file = $path !== '' ? wp_basename($path) : sprintf('#%d', $id);

            if ($path === '' || ! is_file($path)) {
                $failures[] = ['file' => $file, 'reason' => __('the file is missing', 'corex')];

                continue;
            }

            if (! is_readable($path)) {
                $failures[] = ['file' => $file, 'reason' => __('the file cannot be read', 'corex')];

                continue;
            }

            $webp    = (string) preg_replace('/\.(jpe?g|png)$/i', '.webp', $path);
            $items[] = [
                'id'          => $id,
                'path'        => $path,
                'mime'        => (string) get_post_mime_type($id),
                'webp_exists' => $webp !== $path && is_file($webp),
            ];
        }

        $succeeded = 0;
        foreach ($this->regenerator->plan($items)['actions'] as $action) {
            if ($action['action'] !== 'convert') {
                continue;
            }

            if ($this->convert($action)) {
                $succeeded++;

                continue;
            }

            $failures[] = ['file' => wp_basename($action['path']), 'reason' => __('the image could not be converted to WebP', 'corex')];
        }

        return ['succeeded' => $succeeded, 'failed' => count($failures), 'failures' => $failures];
    }

    /**
     * @param array{id:int,path:string,mime:string} $action
     */
    private function convert(array $action): bool
    {
        $plan = ConversionPlan::for($action['path'], $action['mime'], $this->capability, $this->settings);

        try {
            if (! $this->converter->convert($plan, $action['mime'])) {
                return false;
            }
        } catch (\Throwable) {
            return false;
        }

        // Measured and tracked as an upload's WebP is, so delivery's gate applies to it (spec 062).
        $meta = WebpMeta::measure($action['path'], $plan->outputPath, $this->settings->quality, $this->settings->minSaving);
        update_post_meta($action['id'], WebpMeta::META_KEY, wp_slash($meta->toArray()));

        return true;
    }

    /** @return list<int> */
    private function ids(int $offset, int $limit): array
    {
        $ids = (new WP_Query([
            'post_type'      => 'attachment',
            'post_mime_type' => self::MIME_TYPES,
            'post_status'    => 'inherit',
            'fields'         => 'ids',
            'posts_per_page' => $limit,
            'offset'         => $offset,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ]))->posts;

        return array_map('intval', $ids);
    }
}
