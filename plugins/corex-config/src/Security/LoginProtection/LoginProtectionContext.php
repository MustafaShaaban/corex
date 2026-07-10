<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * Normalized facts about one login attempt.
 */
final readonly class LoginProtectionContext
{
    public function __construct(
        public string $identity,
        public string $clientIp,
        public string $userAgent,
    ) {
        if (trim($this->identity) === '' || filter_var($this->clientIp, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException('Login protection context is invalid.');
        }
    }
}
