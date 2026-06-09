<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Recipients;

defined('ABSPATH') || exit;

/**
 * Resolves the email addresses of the users in a role. The interface keeps the
 * resolver headless-testable; the WordPress-backed implementation queries users.
 */
interface UserDirectory
{
    /**
     * @return list<string>
     */
    public function emailsInRole(string $role): array;
}
