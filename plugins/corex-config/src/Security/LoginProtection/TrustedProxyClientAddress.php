<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

use Corex\Http\ClientAddress;

/**
 * The client address as the site's trusted proxies report it.
 *
 * Gives everything that asks corex-core who the client is the answer login protection already
 * uses, so a form's rate limit and a login lockout count the same visitor.
 */
final readonly class TrustedProxyClientAddress implements ClientAddress
{
    public function __construct(private ClientIpResolver $addresses)
    {
    }

    public function current(): string
    {
        $address = $this->addresses->resolve(is_array($_SERVER) ? $_SERVER : []);

        return $address === ClientIpResolver::UNKNOWN ? '' : $address;
    }
}
