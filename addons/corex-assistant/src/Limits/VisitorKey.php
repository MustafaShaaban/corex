<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Limits;

defined('ABSPATH') || exit;

/**
 * Who is asking, as a salted hash of their address (spec 129, User Story 5): enough to count, never the
 * address itself.
 */
final class VisitorKey
{
    /**
     * An IPv6 address counts by its /64 network, which one connection is given whole: counting each
     * address would let one visitor be thousands (Perego's security review, 2026-11-09).
     */
    public static function of(string $address, string $salt): string
    {
        $packed = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? inet_pton($address) : false;
        if ($packed !== false && str_starts_with($packed, str_repeat("\0", 10) . "\xff\xff")) {
            // An IPv4 address a dual-stack server reports as `::ffff:203.0.113.9` is that IPv4 visitor:
            // by its /64 every IPv4 visitor would be one.
            $address = (string) inet_ntop(substr($packed, 12));
        } elseif ($packed !== false) {
            $address = bin2hex(substr($packed, 0, 8)) . '::/64';
        }

        return substr(hash_hmac('sha256', $address, $salt), 0, 24);
    }
}
