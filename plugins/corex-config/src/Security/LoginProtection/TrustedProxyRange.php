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
 * by, which gets around the limit.
 */
final class TrustedProxyRange
{
    /** Not an IPv4 or IPv6 address, or a range with a prefix that is not one for that address. */
    public const REASON_FORMAT = 'format';

    /** A `/0` range: it trusts every address. */
    public const REASON_TRUSTS_EVERY_ADDRESS = 'trusts_every_address';

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
