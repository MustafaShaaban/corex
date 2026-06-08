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
 */
final class DynamicBlockRegistrar
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly BootLogger $logger,
    ) {
    }

    /**
     * @param array{dir: string, name: string, metadata: array<string, mixed>} $block
     */
    public function register(array $block): void
    {
        $args = [];
        $renderer = $block['metadata']['corex']['renderer'] ?? null;

        if (is_string($renderer)) {
            $args['render_callback'] = $this->renderCallback($renderer);
        }

        register_block_type($block['dir'], $args);
    }

    /**
     * The container-resolved render callback (exposed for headless testing).
     *
     * @param class-string<BlockRenderer> $rendererClass
     */
    public function renderCallback(string $rendererClass): callable
    {
        return function (array $attributes, string $content, object $block) use ($rendererClass): string {
            try {
                return $this->container->make($rendererClass)->render($attributes, $content, $block);
            } catch (Throwable $e) {
                // Non-fatal: a broken block render never takes the page down (FR-010).
                $this->logger->warning(sprintf('Block render failed (%s): %s', $rendererClass, $e->getMessage()));

                return '';
            }
        };
    }
}
