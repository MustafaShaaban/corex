<?php

/**
 * Security defaults. Throttle limit/window govern the rate-limit middleware
 * (spec FR-009). Overridable via the Config engine (`security.throttle.*`).
 *
 * @package Corex
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

return [
    'throttle' => [
        'limit'  => 60,
        'window' => 60,
    ],
];
