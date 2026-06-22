<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Assets;

defined('ABSPATH') || exit;

/**
 * Enqueue/register a source-controlled stylesheet by its base-relative path (spec 062), resolving the
 * URL + cache-busting version through the {@see AssetManager} (manifest-hashed build file when present).
 *
 * SCSS is **source only** — passing a `.scss` path never enqueues it (it reports the misuse and skips),
 * so a raw Sass file can never be served. Enqueue the compiled CSS from `assets/css/` instead.
 *
 *   Corex\Assets\Style::enqueue('client-app', 'css/app.css');
 *   Corex\Assets\Style::enqueue('client-app', 'css/app.css', ['base' => 'client', 'deps' => ['wp-block-library']]);
 */
final class Style
{
    /**
     * @param array{base?:string,deps?:list<string>,media?:string,register_only?:bool} $options
     */
    public static function enqueue(string $handle, string $relative, array $options = []): bool
    {
        if (self::isScss($relative)) {
            self::rejectScss($handle, $relative);

            return false;
        }

        $manager = Assets::manager($options['base'] ?? null);
        $deps    = isset($options['deps']) && is_array($options['deps']) ? array_map('strval', $options['deps']) : [];
        $media   = (string) ($options['media'] ?? 'all');
        $url     = $manager->url($relative);
        $version = $manager->version($relative);

        if (! empty($options['register_only'])) {
            wp_register_style($handle, $url, $deps, $version, $media);

            return true;
        }

        wp_enqueue_style($handle, $url, $deps, $version, $media);

        return true;
    }

    /** A `.scss`/`.sass` path is source — never an enqueueable asset. */
    public static function isScss(string $relative): bool
    {
        return (bool) preg_match('/\.s[ac]ss$/i', trim($relative));
    }

    private static function rejectScss(string $handle, string $relative): void
    {
        $message = sprintf(
            'Corex\Assets\Style: "%s" is a Sass source file (%s). SCSS is source only — enqueue the compiled CSS from assets/css/ instead.',
            $handle,
            $relative,
        );

        if (function_exists('_doing_it_wrong')) {
            _doing_it_wrong('Corex\\Assets\\Style::enqueue', esc_html($message), '0.30.0');
        } else {
            error_log($message);
        }
    }
}
