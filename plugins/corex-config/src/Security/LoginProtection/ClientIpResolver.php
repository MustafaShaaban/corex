<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

/**
 * Resolves client IPs while ignoring spoofed forwarded headers from untrusted peers.
 */
final readonly class ClientIpResolver
{
    public function __construct(private LoginProtectionSettings $settings)
    {
    }

    /**
     * @param array<string,string> $server
     */
    public function resolve(array $server): string
    {
        $remote = $this->validIp($server['REMOTE_ADDR'] ?? '') ?? '0.0.0.0';
        if (! $this->settings->trustedProxyMode || ! $this->trusted($remote)) {
            return $remote;
        }

        foreach (explode(',', (string) ($server['HTTP_X_FORWARDED_FOR'] ?? '')) as $candidate) {
            $ip = $this->validIp(trim($candidate));
            if ($ip !== null && ! $this->trusted($ip)) {
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
