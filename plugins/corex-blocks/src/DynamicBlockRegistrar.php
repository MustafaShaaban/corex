<?php

/**
 * @package Corex\Blocks
 */

declare(strict_types=1);

namespace Corex\Blocks;

defined('ABSPATH') || exit;

use Corex\Container\ContainerInterface;
use Corex\Support\BootLogger;
use Throwable;

/**
 * Registers a discovered block via `register_block_type` (conditional assets come
 * from its block.json — no global enqueue). When the block's metadata names a
 * `corex.renderer`, the render callback resolves that BlockRenderer from the
 * container and stays non-fatal: a throwable yields empty output + a logged
 * warning (spec FR-005, FR-008, FR-010).
 *
 * The renderer's markup then passes through the BlockWrapper, which puts what the
 * editor set on the block (anchor, alignment, extra classes) on its root element
 * (spec 136, FR-001 to FR-003). A block names its root in block.json
 * (`corex.root`, a list of classes) when its markup does not always start with it.
 */
final class DynamicBlockRegistrar
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly BootLogger $logger,
        private readonly PluginMountMap $mountMap,
        private readonly BlockPathResolver $resolver,
        private readonly BlockWrapper $wrapper,
    ) {
    }

    /**
     * @param array{dir: string, name: string, metadata: array<string, mixed>} $block
     */
    public function register(array $block): void
    {
        // Idempotent: if the same block name is already registered (e.g. the discovery hook ran
        // twice, or the plugin is loaded from two locations in a monorepo dev setup), skip it
        // rather than emit WordPress's "already registered" notice.
        if (\WP_Block_Type_Registry::get_instance()->is_registered($block['name'])) {
            return;
        }

        $args = [];
        $renderer = $block['metadata']['corex']['renderer'] ?? null;

        if (is_string($renderer)) {
            $args['render_callback'] = $this->renderCallback($renderer, $this->rootClasses($block['metadata']));
        }

        // Normalize the block dir back under WP_PLUGIN_DIR so plugins_url() derives a correct asset URL on any
        // mount (junction/symlink/realpath-resolved) — spec 040. A no-op when the dir is already under it.
        $dir = $this->resolver->resolve($block['dir'], $this->mountMap->pluginsDir(), $this->mountMap->mounts());

        $type = register_block_type($dir, $args);

        $this->registerScriptTranslations($type);
    }

    /**
     * The classes that mark the block's root element (`corex.root` in block.json, a list).
     *
     * @param array<string, mixed> $metadata
     * @return list<string>
     */
    private function rootClasses(array $metadata): array
    {
        $root = $metadata['corex']['root'] ?? [];

        return array_values(array_filter(
            is_array($root) ? $root : [],
            static fn (mixed $class): bool => is_string($class) && $class !== '',
        ));
    }

    /**
     * Make each block's scripts i18n-ready: the `__()` strings in index.js (editor) and
     * view.js (front end, e.g. the form's validation messages) only resolve once their
     * handle is bound to the text domain. Covers editor, view, and front-end handles.
     * No-op when the block ships no JS. Safe on older cores that lack a handle list
     * (guarded with `??`).
     *
     * @param \WP_Block_Type|false $type
     */
    private function registerScriptTranslations(mixed $type): void
    {
        if (! $type instanceof \WP_Block_Type) {
            return;
        }

        $handles = array_merge(
            $type->editor_script_handles ?? [],
            $type->view_script_handles ?? [],
            $type->script_handles ?? [],
        );

        foreach (array_unique($handles) as $handle) {
            wp_set_script_translations($handle, 'corex');
        }
    }

    /**
     * The container-resolved render callback (exposed for headless testing).
     *
     * @param class-string<BlockRenderer> $rendererClass
     * @param list<string>                $rootClasses The block's `corex.root`; empty when its root is its first element.
     */
    public function renderCallback(string $rendererClass, array $rootClasses = []): callable
    {
        return function (array $attributes, string $content, object $block) use ($rendererClass, $rootClasses): string {
            try {
                $markup = $this->container->make($rendererClass)->render($attributes, $content, $block);

                // A block that prints nothing stays absent from the page (spec 136, US1 scenario 5).
                return $markup === '' ? '' : $this->wrapper->apply($markup, $rootClasses);
            } catch (Throwable $e) {
                // Non-fatal: a broken block render never takes the page down (FR-010).
                $this->logger->warning(sprintf('Block render failed (%s): %s', $rendererClass, $e->getMessage()));

                return '';
            }
        };
    }
}
