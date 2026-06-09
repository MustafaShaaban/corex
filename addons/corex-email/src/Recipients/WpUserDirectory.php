<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Recipients;

defined('ABSPATH') || exit;

/**
 * The WordPress-backed user directory: the emails of the users in a role, via a
 * single bounded query (capped — never an unbounded user scan).
 */
final class WpUserDirectory implements UserDirectory
{
    private const MAX = 500;

    /**
     * @return list<string>
     */
    public function emailsInRole(string $role): array
    {
        $users = get_users([
            'role'   => $role,
            'fields' => ['user_email'],
            'number' => self::MAX,
        ]);

        $emails = [];

        foreach ($users as $user) {
            $email = (string) $user->user_email;

            if ($email !== '') {
                $emails[] = $email;
            }
        }

        return $emails;
    }
}
