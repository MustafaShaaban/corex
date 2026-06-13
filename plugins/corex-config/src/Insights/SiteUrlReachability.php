<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Insights;

defined('ABSPATH') || exit;

/**
 * Decides whether a site URL is publicly reachable (spec 044, US3) — PageSpeed Insights
 * can only crawl a public URL, so a local/private one is caught before the call and
 * explained. Pure (no WordPress, no network): host + IP-range inspection only.
 */
final class SiteUrlReachability
{
    private const LOCAL_SUFFIXES = ['.local', '.test', '.localhost', '.invalid', '.example'];

    public function isPublic(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '' || $host === 'localhost') {
            return false;
        }

        foreach (self::LOCAL_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return false;
            }
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            // A public IP is one NOT in a private or reserved (loopback/link-local) range.
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        return true;
    }
}
