<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

/**
 * The accounts a lockout can name (spec 124, FR-016).
 *
 * Asked only on the way into a lockout, never on an ordinary failure, and what it answers is kept
 * where only people who may manage Operations & Security read it: nothing a visitor sees changes
 * with whether the username belongs to an account.
 */
interface LoginAccounts
{
    /** The account a typed username names: by its login, then by its email for an address. */
    public function idFor(string $typed): ?int;

    /**
     * The name a lockout notice gives the account: "<display name> (<login>)", or the login alone
     * when the two are the same. A display name is the account holder's to choose, so it alone could
     * point a notice at someone else.
     */
    public function nameOf(int $userId): string;
}
