<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui\Blocks;

defined('ABSPATH') || exit;

/**
 * Registers the Corex DLS **block styles** (spec 054, US3): appearance-only variants on core
 * (and Corex) blocks — card / section / empty-state on `core/group`, striped `core/table`,
 * and secondary / ghost `core/button`. The gap analysis (research D4) routes these through
 * `register_block_style()` instead of new blocks, because they add look, not data or behavior.
 * All styling is token-only and ships in registered stylesheets, loaded with the block that uses them.
 *
 * The three section styles of `core/group` (spec 134, FR-013, DECISIONS #640) are the visitor kit's
 * grounds: the quiet band, the dark band and the brass band, with a sheet of their own. They live here
 * and not in the theme because the hero and call-to-action patterns that use them are corex-ui's. They
 * exist only under a theme that defines the `band` custom colour (the CoreX theme, whatever palette a
 * brand gives it) or whose palette has every slug their rules fall back to (DECISIONS #642): under a
 * client theme with a palette of its own the patterns' band class is inert and the theme draws them as
 * it always did. Reading the theme's settings costs a request, so it is done only when the answer is
 * needed: for the editor, or the first time a band is drawn, and once a request.
 */
final class BlockStyles
{
    public const HANDLE = 'corex-ui-block-styles';

    /** The section bands' own sheet. */
    public const BANDS_HANDLE = 'corex-ui-section-bands';

    /** The palette slugs `section-bands.css` falls back to; a theme needs all of them for the bands. */
    public const BAND_SLUGS = ['primary', 'surface', 'surface-alt', 'accent', 'accent-dark', 'inverse'];

    /** Each style's sheet, by block and style name: what a drawn block is checked against. */
    private const SHEETS = [
        'core/group'  => [
            'corex-card'       => self::HANDLE,
            'corex-section'    => self::HANDLE,
            'corex-empty'      => self::HANDLE,
            'corex-band-quiet' => self::BANDS_HANDLE,
            'corex-band-dark'  => self::BANDS_HANDLE,
            'corex-band-brass' => self::BANDS_HANDLE,
        ],
        'core/table'  => ['corex-striped' => self::HANDLE],
        'core/button' => ['corex-secondary' => self::HANDLE, 'corex-ghost' => self::HANDLE],
    ];

    /** Whether the bands were looked for in this request (the answer is in the band sheet's registration). */
    private bool $bandsLookedFor = false;

    public function __construct(
        private readonly string $stylesheetUrl,
        private readonly string $bandsStylesheetUrl,
    ) {
    }

    /**
     * The declared styles: block type + style name + label. Pure (testable without WP).
     *
     * @return list<array{block:string,name:string,label:string}>
     */
    public function styles(): array
    {
        return [
            ['block' => 'core/group',  'name' => 'corex-card',      'label' => __('Card', 'corex')],
            ['block' => 'core/group',  'name' => 'corex-section',   'label' => __('Section', 'corex')],
            ['block' => 'core/group',  'name' => 'corex-empty',     'label' => __('Empty state', 'corex')],
            ['block' => 'core/table',  'name' => 'corex-striped',   'label' => __('Striped', 'corex')],
            ['block' => 'core/button', 'name' => 'corex-secondary', 'label' => __('Secondary', 'corex')],
            ['block' => 'core/button', 'name' => 'corex-ghost',     'label' => __('Ghost', 'corex')],
        ];
    }

    /**
     * The section styles of the Group block (spec 134, FR-013). Pure.
     *
     * @return list<array{block:string,name:string,label:string}>
     */
    public function bands(): array
    {
        return [
            ['block' => 'core/group', 'name' => 'corex-band-quiet', 'label' => __('Quiet band', 'corex')],
            ['block' => 'core/group', 'name' => 'corex-band-dark',  'label' => __('Dark band', 'corex')],
            ['block' => 'core/group', 'name' => 'corex-band-brass', 'label' => __('Brass band', 'corex')],
        ];
    }

    /**
     * Whether a theme can draw the bands: it defines the `band` custom colour, or its palette has every
     * slug their rules fall back to (DECISIONS #642). Pure.
     *
     * @param list<string> $slugs the theme's palette slugs
     * @param array<string,mixed> $customColours the theme's `settings.custom.color`
     */
    public function bandsAvailable(array $slugs, array $customColours): bool
    {
        return isset($customColours['band']) || array_diff(self::BAND_SLUGS, $slugs) === [];
    }

    public function register(): void
    {
        wp_register_style(self::HANDLE, $this->stylesheetUrl, [], COREX_UI_VERSION);
        $this->registerAll($this->styles(), self::HANDLE);

        add_filter('render_block', [$this, 'enqueueWhenStyled'], 10, 2);
        // Before WordPress writes the editor's style picker (10) and enqueues its canvas sheets (30).
        add_action('enqueue_block_editor_assets', [$this, 'registerBands'], 5);
        add_action('enqueue_block_assets', [$this, 'registerBandsForEditor'], 5);
    }

    /** Registers the bands and their sheet if the active theme can draw them; once a request. */
    public function registerBands(): void
    {
        if ($this->bandsLookedFor) {
            return;
        }
        $this->bandsLookedFor = true;

        $customColours = wp_get_global_settings(['custom', 'color']);
        if (! $this->bandsAvailable($this->themeSlugs(), is_array($customColours) ? $customColours : [])) {
            return;
        }

        wp_register_style(self::BANDS_HANDLE, $this->bandsStylesheetUrl, [], COREX_UI_VERSION);
        $this->registerAll($this->bands(), self::BANDS_HANDLE);
    }

    /**
     * The site editor gathers its canvas's sheets before it fires `enqueue_block_editor_assets`, so the
     * bands are registered from `enqueue_block_assets` there too. On the front end that hook fires after
     * the page is drawn, where {@see enqueueWhenStyled()} has already asked.
     */
    public function registerBandsForEditor(): void
    {
        if (is_admin()) {
            $this->registerBands();
        }
    }

    /**
     * Enqueues a style's sheet as a block that uses it is drawn, if the sheet was registered (spec 134,
     * DECISIONS #643). WordPress enqueues a block style's sheet from `enqueue_block_assets`, which a
     * block theme fires in `wp_head`, after it has drawn the template, so on the front end the sheet
     * never arrived.
     *
     * @param mixed $html the block's markup, returned as it came
     * @param array<string,mixed> $block the parsed block
     * @return mixed
     */
    public function enqueueWhenStyled(mixed $html, array $block): mixed
    {
        $sheet = $this->sheetFor($block);
        if ($sheet === self::BANDS_HANDLE) {
            $this->registerBands();
        }

        if ($sheet !== null && wp_style_is($sheet, 'registered')) {
            wp_enqueue_style($sheet);
        }

        return $html;
    }

    /** @param list<array{block:string,name:string,label:string}> $styles */
    private function registerAll(array $styles, string $handle): void
    {
        foreach ($styles as $style) {
            register_block_style($style['block'], [
                'name'         => $style['name'],
                'label'        => $style['label'],
                'style_handle' => $handle,
            ]);
        }
    }

    /** @return list<string> the active theme's own palette slugs */
    private function themeSlugs(): array
    {
        $palette = wp_get_global_settings(['color', 'palette', 'theme']);

        return is_array($palette) ? array_values(array_filter(array_column($palette, 'slug'), 'is_string')) : [];
    }

    /**
     * The sheet of the first of these styles the block uses, or null.
     *
     * @param array<string,mixed> $block
     */
    private function sheetFor(array $block): ?string
    {
        $className = (string) ($block['attrs']['className'] ?? '');
        $sheets = self::SHEETS[(string) ($block['blockName'] ?? '')] ?? [];

        if ($className === '' || $sheets === []) {
            return null;
        }

        foreach (explode(' ', $className) as $class) {
            if (str_starts_with($class, 'is-style-') && isset($sheets[substr($class, 9)])) {
                return $sheets[substr($class, 9)];
            }
        }

        return null;
    }
}
