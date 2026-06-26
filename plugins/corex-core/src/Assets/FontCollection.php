<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Assets;

defined('ABSPATH') || exit;

/**
 * A curated CoreX font collection for the WordPress 7 Font Library (spec 062, Priority 2): registers the
 * framework's self-hosted brand typefaces (Space Grotesk, JetBrains Mono, IBM Plex Sans Arabic — all OFL,
 * safe to redistribute) so an editor can install them from Appearance → Fonts. This is optional, editor-side
 * tooling; the production path for a client's brand fonts is still source-controlled fonts registered in the
 * client theme's `theme.json` (see the branding guide). `definition()` is pure; `register()` is the WP boundary.
 */
final class FontCollection
{
    public const SLUG = 'corex';

    public function __construct(private readonly string $fontsBaseUrl)
    {
    }

    public function register(): void
    {
        // WP 6.5+/7.0 Font Library. Guard so older runtimes degrade silently (Principle IX).
        if (! function_exists('wp_register_font_collection')) {
            return;
        }

        wp_register_font_collection(self::SLUG, $this->definition());
    }

    /**
     * The font-collection definition in WordPress Font Library shape. Pure (URL in → array out) so it is
     * unit-tested. Each family points its `fontFace` `src` at the framework's self-hosted woff2.
     *
     * @return array{name:string,description:string,font_families:list<array<string,mixed>>,categories:list<array{name:string,slug:string}>}
     */
    public function definition(): array
    {
        $base = rtrim($this->fontsBaseUrl, '/') . '/';

        return [
            'name'        => __('CoreX', 'corex'),
            'description' => __('CoreX brand typefaces — self-hosted, open-licensed (OFL).', 'corex'),
            'categories'  => [
                ['name' => __('Sans-serif', 'corex'), 'slug' => 'sans-serif'],
                ['name' => __('Monospace', 'corex'), 'slug' => 'monospace'],
            ],
            'font_families' => [
                $this->family('Space Grotesk', 'space-grotesk', 'sans-serif', [
                    ['weight' => '500 700', 'file' => 'space-grotesk-latin-500-700.woff2'],
                ]),
                $this->family('IBM Plex Sans Arabic', 'ibm-plex-sans-arabic', 'sans-serif', [
                    ['weight' => '400', 'file' => 'ibm-plex-sans-arabic-400.woff2'],
                    ['weight' => '600', 'file' => 'ibm-plex-sans-arabic-600.woff2'],
                ], $base),
                $this->family('JetBrains Mono', 'jetbrains-mono', 'monospace', [
                    ['weight' => '400 600', 'file' => 'jetbrains-mono-latin-400-600.woff2'],
                ], $base),
            ],
        ];
    }

    /**
     * One font family entry (family settings + faces + category). `$faces` is a list of
     * `['weight' => string, 'file' => string]`.
     *
     * @param list<array{weight:string,file:string}> $faces
     *
     * @return array<string,mixed>
     */
    private function family(string $name, string $slug, string $category, array $faces, ?string $base = null): array
    {
        $base ??= rtrim($this->fontsBaseUrl, '/') . '/';

        $fontFace = array_map(static fn (array $face): array => [
            'fontFamily' => $name,
            'fontStyle'  => 'normal',
            'fontWeight' => $face['weight'],
            'fontDisplay' => 'swap',
            'src'        => $base . $face['file'],
        ], $faces);

        return [
            'font_family_settings' => [
                'fontFamily' => sprintf('"%s", %s', $name, $category),
                'slug'       => $slug,
                'name'       => $name,
                'fontFace'   => $fontFace,
            ],
            'categories' => [$category],
        ];
    }
}
