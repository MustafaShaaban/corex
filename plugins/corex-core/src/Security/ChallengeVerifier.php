<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Security;

defined('ABSPATH') || exit;

/**
 * Optional anti-automation challenge seam consumed without coupling to a provider.
 */
interface ChallengeVerifier
{
    public function verify(string $token): bool;
}
