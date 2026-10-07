<?php

/**
 * @package Corex\Core
 */

declare(strict_types=1);

namespace Corex\Http;

defined('ABSPATH') || exit;

/**
 * Who is making the current request, for anything that counts or limits per client.
 *
 * `REMOTE_ADDR` is not that answer behind a proxy, a load balancer or a CDN: there it is the proxy,
 * and every visitor is one client. corex-core answers with {@see RemoteAddress}; corex-config, which
 * holds the list of proxies a site trusts, replaces it with an answer that reads that list.
 */
interface ClientAddress
{
    /**
     * @return string A valid IPv4 or IPv6 address, or '' when the request has none (WP-CLI, cron).
     */
    public function current(): string;
}
