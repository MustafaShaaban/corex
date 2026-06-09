<?php

/**
 * @package Corex\Blocks
 */

declare(strict_types=1);

namespace Corex\Blocks\Connectors;

defined('ABSPATH') || exit;

/**
 * An editor-facing data source bindable to block attributes via the WP Block
 * Bindings API (spec FR-011). Implementations resolve values through a Corex
 * Repository — the only layer that touches the data source.
 */
interface Connector
{
    public function name(): string;

    /**
     * @param array<string, mixed> $args
     */
    public function value(string $field, array $args): mixed;
}
