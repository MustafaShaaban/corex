<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Addons;

defined('ABSPATH') || exit;

/**
 * One add-on's render model: the add-on plus its computed state (installed, active, flag
 * on) and, when a toggle would break a dependency, the reason it is blocked (null when the
 * add-on is freely togglable). A pure value object the screen renders.
 */
final class AddonView
{
    public function __construct(
        public readonly Addon $addon,
        public readonly bool $installed,
        public readonly bool $active,
        public readonly bool $flagOn,
        public readonly ?string $blockedReason = null,
    ) {
    }

    public function isBlocked(): bool
    {
        return $this->blockedReason !== null;
    }
}
