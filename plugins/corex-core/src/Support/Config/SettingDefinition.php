<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Support\Config;

defined('ABSPATH') || exit;

/**
 * Keeps scope metadata beside the module that owns a setting instead of in a core list
 * (spec 100 FR-023).
 */
final class SettingDefinition
{
    public function __construct(
        public readonly string $key,
        public readonly SettingScope $scope,
        public readonly string $label = '',
        public readonly ?string $description = null,
    ) {
    }
}
