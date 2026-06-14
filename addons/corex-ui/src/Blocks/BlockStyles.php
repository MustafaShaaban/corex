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
 * All styling is token-only and ships in one registered stylesheet (loaded with the block).
 */
final class BlockStyles
{
    public const HANDLE = 'corex-ui-block-styles';

    public function __construct(private readonly string $stylesheetUrl)
    {
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

    public function register(): void
    {
        wp_register_style(self::HANDLE, $this->stylesheetUrl, [], COREX_UI_VERSION);

        foreach ($this->styles() as $style) {
            register_block_style($style['block'], [
                'name'         => $style['name'],
                'label'        => $style['label'],
                'style_handle' => self::HANDLE,
            ]);
        }
    }
}
