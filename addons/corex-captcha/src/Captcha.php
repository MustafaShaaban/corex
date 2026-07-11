<?php

/**
 * @package Corex\Captcha
 */

declare(strict_types=1);

namespace Corex\Captcha;

defined('ABSPATH') || exit;

use Corex\Security\ChallengeVerifier;

/**
 * Verifies an anti-bot challenge. Implementations are fail-closed: anything other
 * than a confirmed pass returns false.
 */
interface Captcha extends ChallengeVerifier
{
    /**
     * @param array<string,mixed> $context
     */
    public function verify(string $token, array $context = []): bool;
}
