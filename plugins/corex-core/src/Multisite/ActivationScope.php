<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Multisite;

defined('ABSPATH') || exit;

enum ActivationScope: string
{
    case None = 'none';
    case Site = 'site';
    case Network = 'network';
    case MustUse = 'must-use';

    public function isActive(): bool
    {
        return $this !== self::None;
    }

    public function label(): string
    {
        return match ($this) {
            self::None    => __('Inactive', 'corex'),
            self::Site    => __('Site', 'corex'),
            self::Network => __('Network', 'corex'),
            self::MustUse => __('Must-use', 'corex'),
        };
    }
}
