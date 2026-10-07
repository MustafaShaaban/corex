<?php

/**
 * @package Corex\Core
 */

declare(strict_types=1);

namespace Corex\Http;

defined('ABSPATH') || exit;

/**
 * The address the connection came from, and nothing a header claims.
 *
 * The answer for a site that has not said which proxies it trusts. A forwarded header is written
 * by whoever sends the request, so without that list there is nobody to vouch for one.
 */
final class RemoteAddress implements ClientAddress
{
    public function current(): string
    {
        // Validated, not sanitised: an address either is one or the request has none.
        $address = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP);

        return $address === false ? '' : $address;
    }
}
