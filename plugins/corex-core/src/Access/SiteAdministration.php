<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Access;

defined('ABSPATH') || exit;

/**
 * Which roles and people can administer the site (spec 117, ACC-D11): those holding
 * `manage_options`. Their members hold every CoreX ability, and the role matrix cannot deny them
 * one. Only they may make the access changes of DECISIONS #428.
 */
interface SiteAdministration
{
    public function roleAdministersSite(string $roleKey): bool;

    /** Whether this person administers the site; on a network a super administrator does. */
    public function userAdministersSite(int $userId): bool;
}
