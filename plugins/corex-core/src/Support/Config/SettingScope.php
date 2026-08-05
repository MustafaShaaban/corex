<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Support\Config;

defined('ABSPATH') || exit;

/**
 * Declares which administration boundary may supply a setting (spec 100 FR-023–FR-024).
 */
enum SettingScope: string
{
    case Site = 'site';
    case NetworkDefault = 'network-default';
    case NetworkLocked = 'network-locked';
}
