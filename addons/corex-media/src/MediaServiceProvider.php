<?php

/**
 * @package Corex\Media
 */

declare(strict_types=1);

namespace Corex\Media;

defined('ABSPATH') || exit;

use Corex\Container\ContainerInterface;
use Corex\Foundation\ServiceProvider;

/**
 * The optional Corex Media add-on (spec 048): converts uploads to WebP when the server
 * supports it (original preserved) and exposes the {@see MediaImage} helper + an advisory
 * image-support health probe. Self-gating (Principle IX) — with no GD/Imagick/WebP it adds
 * no conversion and the helper degrades to a plain `<img>`; core never depends on it.
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

        // Advisory image-support probe → Site Health + `wp corex doctor` (spec 036 seam).
        add_filter('corex_health_probes', static function (array $probes) use ($capability): array {
            $probes[] = new MediaImageProbe($capability);

            return $probes;
        });

        if (! $capability->canWebp()) {
            return; // graceful: no conversion on a server that cannot write WebP
        }

        $converter = new WebpConverter();

        add_filter('wp_generate_attachment_metadata', static function ($metadata, $attachmentId) use ($converter, $capability) {
            $file = (string) get_attached_file((int) $attachmentId);
            $mime = (string) get_post_mime_type((int) $attachmentId);
            $plan = ConversionPlan::for($file, $mime, $capability);

            if ($plan->convert) {
                $converter->convert($plan, $mime);
            }

            return $metadata; // unchanged — the WebP is a sibling; WP's own sizes are untouched
        }, 20, 2);
    }
}
