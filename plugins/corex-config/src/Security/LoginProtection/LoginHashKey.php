<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

/**
 * The secret a sign-in record's values are made with (spec 124, FR-019).
 *
 * The username and the address of an attempt are kept only as keyed hashes. Without a key, anyone
 * holding the table could hash a list of usernames, or every IPv4 address, and read them back. The
 * key is the site's own, so the same username makes different values on two sites.
 */
interface LoginHashKey
{
    /** The key for the site in scope, read each time it is asked for. */
    public function current(): string;
}
