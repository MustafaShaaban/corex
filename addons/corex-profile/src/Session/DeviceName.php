<?php

/**
 * @package Corex\Profile
 */

declare(strict_types=1);

namespace Corex\Profile\Session;

defined('ABSPATH') || exit;

/**
 * What a session is called in the account block: the browser and the system its user agent
 * names, as "Chrome on Windows" (spec 068, FR-161g).
 *
 * It reads the few tokens a person can check against the device in their hand, and no
 * version: a browser's version changes every few weeks, and a user agent has stopped telling
 * one version of a system from the next. It is not a user agent parser and uses none. A
 * browser built on another and saying only the other's name (Brave, Vivaldi) is named as
 * the other, and an iPad that asks for desktop pages says it is a Mac.
 */
final class DeviceName
{
    /**
     * Browser by the token that names it. A browser also carries the tokens of the ones it
     * is built on (Edge says Chrome and Safari, Chrome says Safari), so the order is the
     * rule: the most specific first.
     */
    private const BROWSERS = [
        'Edge'             => '/\bEdg(?:e|A|iOS)?\//',
        'Opera'            => '/\bOPR\/|\bOpera\b/',
        'Samsung Internet' => '/\bSamsungBrowser\//',
        'Firefox'          => '/\b(?:Firefox|FxiOS)\//',
        'Chrome'           => '/(?:Chrome|CriOS)\//',
        'Safari'           => '/\bVersion\/\S+ .*Safari\//',
    ];

    /**
     * System by the token that names it. An iPhone says "like Mac OS X" and Android says
     * Linux, so those two come before what they resemble.
     */
    private const SYSTEMS = [
        'iPhone'   => '/\biPhone\b/',
        'iPad'     => '/\biPad\b/',
        'Android'  => '/\bAndroid\b/',
        'Windows'  => '/\bWindows\b/',
        'ChromeOS' => '/\bCrOS\b/',
        'macOS'    => '/\bMacintosh\b|\bMac OS X\b/',
        'Linux'    => '/\bLinux\b/',
    ];

    public static function fromUserAgent(string $userAgent): string
    {
        $browser = self::firstNamed(self::BROWSERS, $userAgent) ?? __('Unknown browser', 'corex');
        $system  = self::firstNamed(self::SYSTEMS, $userAgent);

        if ($system === null) {
            return $browser;
        }

        /* translators: 1: a browser's name, such as Chrome, 2: a system's name, such as Windows. */
        return sprintf(__('%1$s on %2$s', 'corex'), $browser, $system);
    }

    /**
     * @param array<string, string> $names A name by the pattern that finds it.
     */
    private static function firstNamed(array $names, string $userAgent): ?string
    {
        foreach ($names as $name => $pattern) {
            if (preg_match($pattern, $userAgent) === 1) {
                return $name;
            }
        }

        return null;
    }
}
