<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use WP_User;

/**
 * Names a submission's owner from WordPress: a user by their display name.
 */
final class WpSubmissionOwnerNames implements SubmissionOwnerNames
{
    public function nameOf(string $ownerType, string $ownerKey): string
    {
        if ($ownerType !== 'user') {
            return '';
        }

        $user = get_userdata((int) $ownerKey);

        return $user instanceof WP_User ? (string) $user->display_name : '';
    }
}
