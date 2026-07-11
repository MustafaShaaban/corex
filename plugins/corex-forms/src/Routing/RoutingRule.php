<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Routing;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * Immutable ordered rule; built-in routing always stops on its first match.
 */
final readonly class RoutingRule
{
    public function __construct(
        public string $uuid,
        public int $position,
        public RoutingCondition $condition,
        public RoutingTarget $target,
        public bool $enabled = true,
    ) {
        if ($uuid === '' || $position < 0) {
            throw new InvalidArgumentException('Routing rule identity is invalid.');
        }
    }
}
