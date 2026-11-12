<?php

/**
 * @package Corex\Blocks
 */

declare(strict_types=1);

namespace Corex\Blocks;

defined('ABSPATH') || exit;

/**
 * Sample content in shipped patterns (spec 115, US-SAFE-14): a block whose class list holds
 * `is-corex-sample` is an example someone has to replace, such as invented figures, a quote or a list
 * of partners. A visitor sees nothing of it. Someone who can edit posts sees it with the label
 * "Sample: replace before publishing", on the site and in the block editor. The editor removes the
 * marker the first time the block's content is changed (`assets/sample-editor.js`), and from then on
 * the block is ordinary content.
 *
 * Loaded on every site, so the filter returns at once for a block without the marker. Its
 * stylesheets load only where they are needed: the label's on a page that draws one, the editor's in
 * the editor.
 */
final class SampleBlocks
{
    public const MARKER = 'is-corex-sample';

    public function __construct(private readonly string $pluginFile, private readonly string $version)
    {
    }

    public function register(): void
    {
        add_filter('render_block', [$this, 'render'], 10, 2);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueEditorScript']);
        add_action('enqueue_block_assets', [$this, 'enqueueEditorStyle']);
    }

    public static function isSample(array $block): bool
    {
        $className = $block['attrs']['className'] ?? '';

        return is_string($className)
            && str_contains($className, self::MARKER)
            && in_array(self::MARKER, preg_split('/\s+/', trim($className)) ?: [], true);
    }

    /**
     * @param array<string,mixed> $block
     */
    public function render(string $content, array $block): string
    {
        if (! self::isSample($block)) {
            return $content;
        }

        if (! current_user_can('edit_posts')) {
            return '';
        }

        wp_enqueue_style('corex-sample', plugins_url('assets/sample.css', $this->pluginFile), [], $this->version);

        return sprintf('<p class="corex-sample-label" role="note">%s</p>', esc_html(self::label())) . $content;
    }

    public function enqueueEditorScript(): void
    {
        wp_enqueue_script(
            'corex-sample-editor',
            plugins_url('assets/sample-editor.js', $this->pluginFile),
            ['wp-hooks', 'wp-compose', 'wp-element', 'wp-data', 'wp-block-editor', 'wp-i18n'],
            $this->version,
            true,
        );
        wp_set_script_translations('corex-sample-editor', 'corex');
    }

    /**
     * The editor's canvas is a frame of its own, and `enqueue_block_assets` is the hook that reaches it.
     * It also runs on the site, where the label's own stylesheet does the work.
     */
    public function enqueueEditorStyle(): void
    {
        if (is_admin()) {
            wp_enqueue_style(
                'corex-sample-editor',
                plugins_url('assets/sample-editor.css', $this->pluginFile),
                [],
                $this->version,
            );
        }
    }

    public static function label(): string
    {
        return __('Sample: replace before publishing', 'corex');
    }
}
