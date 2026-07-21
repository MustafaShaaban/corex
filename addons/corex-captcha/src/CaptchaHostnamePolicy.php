<?php

/**
 * @package Corex\Captcha
 */

declare(strict_types=1);

namespace Corex\Captcha;

defined('ABSPATH') || exit;

/**
 * Decides whether the hostname a provider reports is one this site permits.
 *
 * Exact, normalised comparison against an explicit allowlist — never a substring match. The
 * substring shortcut (`str_contains($allowed, $reported)`) is the classic reCAPTCHA
 * verification hole: it accepts `corex.local.evil.com` because it "contains" `corex.local`.
 */
final class CaptchaHostnamePolicy
{
    /** @var list<string> */
    private array $allowed;

    /** @param list<string> $allowed */
    public function __construct(array $allowed)
    {
        $normalised = [];
        foreach ($allowed as $host) {
            $host = strtolower(trim($host));
            if ($host !== '') {
                $normalised[] = $host;
            }
        }
        $this->allowed = array_values(array_unique($normalised));
    }

    public function allows(string $hostname): bool
    {
        return in_array(strtolower(trim($hostname)), $this->allowed, true);
    }

    /** @return list<string> */
    public function allowed(): array
    {
        return $this->allowed;
    }
}
