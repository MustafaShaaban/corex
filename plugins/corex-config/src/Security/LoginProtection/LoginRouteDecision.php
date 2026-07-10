<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

/**
 * Decision for custom login route/default endpoint protection.
 */
final readonly class LoginRouteDecision
{
    public function __construct(
        public bool $blocked,
        public int $statusCode,
        public string $reasonCode,
    ) {
    }
}
