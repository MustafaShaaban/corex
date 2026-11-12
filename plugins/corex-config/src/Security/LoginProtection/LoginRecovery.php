<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

/**
 * Whether login recovery is on: `define( 'COREX_LOGIN_UNGUARD', true );` in wp-config.php, the
 * break-glass an owner with no command line can set from the host's file manager.
 *
 * The constant is fixed before WordPress loads, so the container reads it once and hands this one
 * answer to everything that acts on it ({@see LoginRouteGuard}) or reports it (the Security
 * Center, spec 112 FR-022). Nothing else reads the constant.
 */
final class LoginRecovery
{
    public function __construct(private readonly bool $active)
    {
    }

    public function active(): bool
    {
        return $this->active;
    }
}
