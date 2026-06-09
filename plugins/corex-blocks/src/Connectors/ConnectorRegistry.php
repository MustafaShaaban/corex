<?php

/**
 * @package Corex\Blocks
 */

declare(strict_types=1);

namespace Corex\Blocks\Connectors;

defined('ABSPATH') || exit;

/**
 * Registers connectors with the WordPress Block Bindings API so editors can bind
 * block attributes to Corex data (spec FR-011, FR-019).
 */
final class ConnectorRegistry
{
    public function register(Connector ...$connectors): void
    {
        foreach ($connectors as $connector) {
            register_block_bindings_source($connector->name(), [
                'label'              => $connector->name(),
                'get_value_callback' => static fn (array $args, $block, string $key): mixed
                    => $connector->value($key, $args),
            ]);
        }
    }
}
