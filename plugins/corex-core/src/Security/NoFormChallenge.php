<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Security;

defined('ABSPATH') || exit;

/**
 * A site with no captcha provider: forms carry no check and every request passes, as before spec 115.
 * Bound by core until corex-captcha replaces it.
 */
final class NoFormChallenge implements FormChallenge
{
    public function fields(string $action): string
    {
        return '';
    }

    public function formAttribute(): string
    {
        return '';
    }

    public function refusal(string $token, string $action): ?string
    {
        return null;
    }
}
