<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

/**
 * The site's sign-in key, made from WordPress's auth salt and the site's id.
 *
 * The salt lives in `wp-config.php`, not in the database, so a copy of the database alone does not
 * carry the key to the hashes in it. A network shares one `wp-config.php`, so the site's id is mixed
 * in: two sites of one network make different values for the same username. The key is derived, so
 * the salt itself is never what is handed out, and it is read on every call, so a run that switches
 * between sites gets each site's key.
 *
 * A site whose salts are changed gets a new key: attempts stored under the old one stop counting,
 * which ends a lockout early and never starts one. That is the direction for it to fail in.
 */
final class WpLoginHashKey implements LoginHashKey
{
    private const CONTEXT = 'corex-login-attempts:';

    public function current(): string
    {
        return hash_hmac('sha256', self::CONTEXT . get_current_blog_id(), wp_salt('auth'));
    }
}
