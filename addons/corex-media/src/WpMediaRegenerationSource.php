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
 * and converts one offset-bounded batch through the pure {@see WebpRegenerator} plan + the
 * {@see WebpConverter}. It mirrors the proven {@see MediaCommand} backfill — it never overwrites an
 * existing WebP sibling or the original, and missing files are skipped safely.
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
     * @return array{succeeded:int,failed:int}
     */
    public function convertBatch(int $offset, int $limit): array
    {
        $plan      = $this->regenerator->plan($this->items(max(0, $offset), max(1, $limit)));
        $succeeded = 0;
        $failed    = 0;

        foreach ($plan['actions'] as $action) {
            if ($action['action'] !== 'convert') {
                continue;
            }

            try {
                $ok = $this->converter->convert(
                    ConversionPlan::for($action['path'], $action['mime'], $this->capability, $this->settings),
                    $action['mime'],
                );
                $ok ? $succeeded++ : $failed++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        return ['succeeded' => $succeeded, 'failed' => $failed];
    }

    /**
     * @return list<array{id:int,path:string,mime:string,webp_exists:bool}>
     */
    private function items(int $offset, int $limit): array
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

        $items = [];

        foreach ($ids as $id) {
            $id   = (int) $id;
            $path = (string) get_attached_file($id);
            if ($path === '' || ! is_file($path)) {
                continue;
            }

            $webp = (string) preg_replace('/\.(jpe?g|png)$/i', '.webp', $path);

            $items[] = [
                'id'          => $id,
                'path'        => $path,
                'mime'        => (string) get_post_mime_type($id),
                'webp_exists' => $webp !== $path && is_file($webp),
            ];
        }

        return $items;
    }
}
