<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Access;

defined('ABSPATH') || exit;

/**
 * Which roles can administer the site (spec 117, ACC-D11): a role holding `manage_options`. Their
 * members hold every CoreX ability, and the role matrix cannot deny them one.
 */
interface SiteAdministration
{
    public function roleAdministersSite(string $roleKey): bool;
}
