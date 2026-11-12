<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

/**
 * Whether a trusted proxy line is an IPv4 or IPv6 address, or a range of one in CIDR form
 * (spec 124, FR-015): the prefix a whole number no longer than the address, so `10.0.0.0/33` is not
 * one. The screen's route and `wp corex security trusted-proxies` both ask here, so they accept the
 * same lines.
 *
 * A `/0` range is refused although it is well-formed. It covers every address, so every client would
 * be a trusted proxy and could name in `X-Forwarded-For` the address its sign-in attempts are counted
 * by, which gets around the limit. The check is for a slip, not a boundary: `0.0.0.0/1` with
 * `128.0.0.0/1` still covers every IPv4 address, and is not refused.
 *
 * A line is stored in one spelling ({@see self::canonical()}), and at most {@see self::MAX_LINES}.
 */
final class TrustedProxyRange
{
    /** The most trusted proxy lines kept: enough for a CDN's published ranges, and a bound on the option. */
    public const MAX_LINES = 100;

    /** Not an IPv4 or IPv6 address, or a range with a prefix that is not one for that address. */
    public const REASON_FORMAT = 'format';

    /** A `/0` range: it trusts every address. */
    public const REASON_TRUSTS_EVERY_ADDRESS = 'trusts_every_address';

    /** Whether a list of this many lines is longer than is kept. */
    public static function tooMany(int $lines): bool
    {
        return $lines > self::MAX_LINES;
    }

    /**
     * The line in the one spelling it is stored in: the address as `inet_ntop()` writes it, so
     * `2001:DB8::1` and the address written in full are both `2001:db8::1`, and the prefix as a plain
     * number. Null for a line {@see self::rejectionReason()} refuses.
     */
    public static function canonical(string $line): ?string
    {
        if (self::rejectionReason($line) !== null) {
            return null;
        }

        [$address, $prefix] = array_pad(explode('/', $line, 2), 2, null);
        $spelled = (string) inet_ntop((string) inet_pton((string) $address));

        return $prefix === null ? $spelled : $spelled . '/' . (int) $prefix;
    }

    /**
     * Whether a stored list holds a range that covers every address. Lists stored before the rule
     * may; they are used as stored, and Hardening says so (DECISIONS #629, amended 2026-11-13).
     *
     * @param list<string> $ranges
     */
    public static function trustsEveryAddress(array $ranges): bool
    {
        foreach ($ranges as $range) {
            if (self::rejectionReason(trim((string) $range)) === self::REASON_TRUSTS_EVERY_ADDRESS) {
                return true;
            }
        }

        return false;
    }

    public static function isValid(string $line): bool
    {
        return self::rejectionReason($line) === null;
    }

    /** Why the line cannot be trusted, or null when it can. */
    public static function rejectionReason(string $line): ?string
    {
        [$address, $prefix] = array_pad(explode('/', $line, 2), 2, null);

        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            return self::REASON_FORMAT;
        }

        $packed = inet_pton((string) $address);
        if ($packed === false) {
            return self::REASON_FORMAT;
        }

        if ($prefix === null) {
            return null;
        }

        if (! ctype_digit($prefix) || (int) $prefix > strlen($packed) * 8) {
            return self::REASON_FORMAT;
        }

        return (int) $prefix === 0 ? self::REASON_TRUSTS_EVERY_ADDRESS : null;
    }
}
