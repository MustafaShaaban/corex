<?php

/**
 * @package Corex\Media
 */

declare(strict_types=1);

namespace Corex\Media;

defined('ABSPATH') || exit;

/**
 * `wp corex media regenerate-webp` (spec 061): backfill WebP siblings for existing uploads. Safe by
 * design — it never deletes or overwrites originals, skips attachments that already have a `.webp`
 * sibling, skips unsupported types, respects the current {@see MediaSettings}, and supports a
 * dry-run. The decisions are the pure {@see WebpRegenerator}; this class is the WP/WP-CLI boundary.
 *
 * ## OPTIONS
 *
 * [--dry-run]      Report what would be converted without writing anything.
 * [--limit=<n>]    Process at most N attachments (0 = all). Default: 0.
 * [--attachment=<id>]  Only this attachment ID.
 */
final class MediaCommand
{
    public function __construct(
        private readonly ImageCapability $capability,
        private readonly MediaSettings $settings,
    ) {
    }

    /**
     * @param list<string>         $args
     * @param array<string,string> $assoc
     */
    public function __invoke(array $args, array $assoc = []): void
    {
        $dryRun = isset($assoc['dry-run']);
        $limit  = isset($assoc['limit']) ? max(0, (int) $assoc['limit']) : 0;
        $only   = isset($assoc['attachment']) ? (int) $assoc['attachment'] : 0;

        if (! $this->capability->canWebp()) {
            $this->warn('This server cannot encode WebP (no GD/Imagick WebP support); nothing to do.');

            return;
        }
        if (! $this->settings->enabled) {
            $this->warn('WebP conversion is disabled in CoreX Media settings; enable it or run with it on.');

            return;
        }

        $items = $this->items($only, $limit);
        $plan  = (new WebpRegenerator($this->capability, $this->settings))->plan($items);

        $converter = new WebpConverter($this->settings->quality);
        $converted = 0;
        $failed    = 0;

        foreach ($plan['actions'] as $action) {
            if ($action['action'] !== 'convert') {
                continue;
            }
            $this->log(sprintf('%s #%d %s', $dryRun ? 'would convert' : 'converting', $action['id'], $action['path']));

            if ($dryRun) {
                continue;
            }

            $output = (string) preg_replace('/\.(jpe?g|png)$/i', '', $action['path']) . '.webp';
            $ok = $converter->convert(ConversionPlan::for($action['path'], $action['mime'], $this->capability, $this->settings), $action['mime']);
            $ok && is_file($output) ? $converted++ : $failed++;
        }

        $this->success(sprintf(
            '%sto convert: %d · skipped: %d · converted: %d · failed: %d (of %d).',
            $dryRun ? '[dry-run] ' : '',
            $plan['counts']['convert'],
            $plan['counts']['skipped'],
            $converted,
            $failed,
            $plan['counts']['total'],
        ));
    }

    /**
     * Build the regenerator's item list from WordPress attachments (path + whether a WebP sibling
     * already exists). Missing files are skipped safely.
     *
     * @return list<array{id:int,path:string,mime:string,webp_exists:bool}>
     */
    private function items(int $only, int $limit): array
    {
        $ids = $only > 0 ? [$only] : $this->attachmentIds($limit);
        $items = [];

        foreach ($ids as $id) {
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

    /** @return list<int> */
    private function attachmentIds(int $limit): array
    {
        $query = [
            'post_type'      => 'attachment',
            'post_mime_type' => ['image/jpeg', 'image/png'],
            'post_status'    => 'inherit',
            'fields'         => 'ids',
            'posts_per_page' => $limit > 0 ? $limit : 200,
            'no_found_rows'  => true,
        ];

        return array_map('intval', (array) get_posts($query));
    }

    private function log(string $message): void
    {
        if (class_exists('\WP_CLI')) {
            \WP_CLI::log($message);
        }
    }

    private function warn(string $message): void
    {
        if (class_exists('\WP_CLI')) {
            \WP_CLI::warning($message);
        }
    }

    private function success(string $message): void
    {
        if (class_exists('\WP_CLI')) {
            \WP_CLI::success($message);
        }
    }
}
