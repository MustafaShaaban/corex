<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Foundation;

defined('ABSPATH') || exit;

enum AddonBlockReason: string
{
    case NotInstalled = 'not installed';
    case Inactive = 'inactive';
    case MissingDependencies = 'missing dependencies';
    case FeatureFlagDisabled = 'feature flag disabled';
    case ExternalGateUnavailable = 'external gate unavailable';

    public function label(): string
    {
        return match ($this) {
            self::NotInstalled            => __('Not installed', 'corex'),
            self::Inactive                => __('Inactive', 'corex'),
            self::MissingDependencies     => __('Missing dependencies', 'corex'),
            self::FeatureFlagDisabled     => __('Feature flag disabled', 'corex'),
            self::ExternalGateUnavailable => __('External gate unavailable', 'corex'),
        };
    }
}
