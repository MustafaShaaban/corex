<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Fields;

defined('ABSPATH') || exit;

use Closure;

/**
 * Picks the active field driver at runtime: ACF when both ACF field functions are
 * available, native post meta otherwise (spec FR-009). The availability check is
 * injectable so it is testable and so runtime activation/deactivation is honored
 * per resolution rather than snapshotted at boot.
 */
final class FieldResolver
{
    private readonly Closure $acfAvailable;

    public function __construct(
        private readonly MetaFieldDriver $meta,
        private readonly AcfFieldDriver $acf,
        ?callable $acfAvailable = null,
    ) {
        $this->acfAvailable = $acfAvailable !== null
            ? Closure::fromCallable($acfAvailable)
            : static fn (): bool => function_exists('get_field') && function_exists('update_field');
    }

    public function driver(): FieldDriver
    {
        return ($this->acfAvailable)() ? $this->acf : $this->meta;
    }
}
