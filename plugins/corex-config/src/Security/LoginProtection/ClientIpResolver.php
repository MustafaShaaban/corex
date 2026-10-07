<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

/**
 * Resolves the client's address. A forwarded header is believed only when the request arrives from
 * a trusted proxy, and then only as far as trusted proxies vouch for it: the answer is the nearest
 * hop that is not itself trusted. Every proxy between the visitor and WordPress therefore has to
 * be in the trusted ranges, or the first one that is not is taken for the client.
 */
final readonly class ClientIpResolver
{
    /** What `resolve()` answers when the request carries no usable address. */
    public const UNKNOWN = '0.0.0.0';

    public function __construct(private LoginProtectionSettings $settings)
    {
    }

    /**
     * @param array<string,string> $server
     */
    public function resolve(array $server): string
    {
        $remote = $this->validIp($server['REMOTE_ADDR'] ?? '') ?? self::UNKNOWN;
        if (! $this->settings->trustedProxyMode || ! $this->trusted($remote)) {
            return $remote;
        }

        // Read from the right. Each proxy appends the address it saw, so the rightmost entries are
        // the ones a trusted proxy vouches for and the leftmost are whatever the client sent.
        $hops = explode(',', (string) ($server['HTTP_X_FORWARDED_FOR'] ?? ''));
        foreach (array_reverse($hops) as $hop) {
            $ip = $this->validIp(trim($hop));
            if ($ip === null) {
                // Nothing left of an entry that cannot be read is vouched for by anybody.
                break;
            }
            if (! $this->trusted($ip)) {
                return $ip;
            }
        }

        return $remote;
    }

    private function validIp(string $ip): ?string
    {
        return filter_var($ip, FILTER_VALIDATE_IP) === false ? null : $ip;
    }

    private function trusted(string $ip): bool
    {
        foreach ($this->settings->trustedProxyRanges as $range) {
            if ($this->inCidr($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    private function inCidr(string $ip, string $range): bool
    {
        if (! str_contains($range, '/')) {
            return $ip === $range;
        }

        [$subnet, $bits] = explode('/', $range, 2);
        $ipBytes = inet_pton($ip);
        $subnetBytes = inet_pton($subnet);
        if ($ipBytes === false || $subnetBytes === false || strlen($ipBytes) !== strlen($subnetBytes)) {
            return false;
        }

        $prefix = max(0, min((int) $bits, strlen($ipBytes) * 8));
        $fullBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;

        if ($fullBytes > 0 && substr($ipBytes, 0, $fullBytes) !== substr($subnetBytes, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xff << (8 - $remainingBits)) & 0xff;

        return (ord($ipBytes[$fullBytes]) & $mask) === (ord($subnetBytes[$fullBytes]) & $mask);
    }
}
