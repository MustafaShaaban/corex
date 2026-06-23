<?php

/**
 * @package Corex\Media
 */

declare(strict_types=1);

namespace Corex\Media;

defined('ABSPATH') || exit;

use Corex\Container\ContainerInterface;
use Corex\Foundation\ServiceProvider;
use Corex\Support\Config\ConfigInterface;

/**
 * The optional Corex Media add-on (spec 048; settings + regeneration spec 061): converts uploads
 * to WebP when the server supports it (original preserved), exposes the {@see MediaImage} helper +
 * an advisory image-support probe, and registers the `wp corex media regenerate-webp` command. The
 * conversion respects {@see MediaSettings} (enable/quality/per-format) read from Config. Self-gating
 * (Principle IX) — with no GD/Imagick/WebP it adds no conversion and the helper degrades to `<img>`.
 */
final class MediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(PictureRenderer::class, static fn (): PictureRenderer => new PictureRenderer());
        $this->container->singleton(
            MediaImage::class,
            static fn (ContainerInterface $c): MediaImage => new MediaImage($c->make(PictureRenderer::class)),
        );
    }

    public function boot(): void
    {
        $capability = ImageCapability::detect();
        $settings   = MediaSettings::fromConfig($this->container->make(ConfigInterface::class));

        // Advisory image-support probe → Site Health + `wp corex doctor` (spec 036 seam).
        add_filter('corex_health_probes', static function (array $probes) use ($capability): array {
            $probes[] = new MediaImageProbe($capability);

            return $probes;
        });

        // Live server-support summary for the CoreX Media settings panel (decoupled seam — corex-config
        // reads this filter and never hard-depends on this add-on).
        add_filter('corex_media_support_summary', static fn (): string => (new MediaSupport($capability))->summary());

        // Frontend delivery seam (spec 061): any block holding an image URL can opt into optimized
        // <picture> output via this filter, without depending on corex-media. Returns the given
        // fallback markup unchanged when there is no WebP sibling.
        $mediaImage = $this->container->make(MediaImage::class);
        add_filter('corex_media_optimize_image', static function ($fallback, array $args = []) use ($mediaImage) {
            $url = (string) ($args['url'] ?? '');
            if ($url === '') {
                return $fallback;
            }

            $opts = ['class' => (string) ($args['class'] ?? ''), 'lcp' => ! empty($args['lcp'])];
            if (isset($args['loading'])) {
                $opts['loading'] = (string) $args['loading'];
            }

            $html = $mediaImage->pictureForUrl($url, (string) ($args['alt'] ?? ''), $opts);

            return $html !== '' ? $html : $fallback;
        }, 10, 2);

        // CLI: backfill (regenerate-webp) + safe cleanup (reset-webp) for existing uploads (spec 061/062).
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('corex media regenerate-webp', new MediaCommand($capability, $settings));
            \WP_CLI::add_command('corex media reset-webp', new WebpResetCommand());
        }

        // Clean up tracked derivatives when an attachment is deleted (never touch untracked files).
        add_action('delete_attachment', static function ($attachmentId): void {
            (new WebpResetCommand())->forgetAttachment((int) $attachmentId);
        });

        if (! $capability->canWebp() || ! $settings->enabled) {
            return; // graceful: nothing to do when the server can't, or the operator turned it off
        }

        $converter = new WebpConverter($settings->quality);

        add_filter('wp_generate_attachment_metadata', static function ($metadata, $attachmentId) use ($converter, $capability, $settings) {
            $id   = (int) $attachmentId;
            $file = (string) get_attached_file($id);
            $mime = (string) get_post_mime_type($id);
            $plan = ConversionPlan::for($file, $mime, $capability, $settings);

            if ($plan->convert && $converter->convert($plan, $mime)) {
                // Measure the derivative + apply the activation gate (spec 062), and track the result so
                // delivery can decide whether to serve it and reset-webp knows it is CoreX-generated.
                $meta = WebpMeta::measure($file, $plan->outputPath, $settings->quality, $settings->minSaving);
                update_post_meta($id, WebpMeta::META_KEY, $meta->toArray());
            }

            return $metadata; // unchanged — the WebP is a sibling; WP's own sizes are untouched
        }, 20, 2);
    }
}
