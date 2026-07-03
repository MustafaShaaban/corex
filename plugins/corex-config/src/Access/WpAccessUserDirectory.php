<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Access;

defined('ABSPATH') || exit;

use Corex\Access\AccessUserDirectory;
use RuntimeException;
use WP_User;

final class WpAccessUserDirectory implements AccessUserDirectory
{
    public function userIdsForRole(string $roleKey): array
    {
        return $this->idsForRole($roleKey);
    }

    public function fullAccessAdministratorIds(): array
    {
        $ids = $this->idsForRole('administrator');

        return array_values(array_filter($ids, static fn (int $id): bool => user_can($id, 'manage_options')));
    }

    public function grantUserAbility(int $userId, string $abilityKey): void
    {
        $user = new WP_User($userId);

        if (! $user->exists()) {
            throw new RuntimeException('The access-request user no longer exists.');
        }

        $user->add_cap($abilityKey, true);
    }

    public function displayName(int $userId): string
    {
        $user = get_userdata($userId);

        return $user instanceof WP_User && $user->display_name !== ''
            ? $user->display_name
            : sprintf(
                /* translators: %d: WordPress user ID. */
                __('User %d', 'corex'),
                $userId,
            );
    }

    /** @return list<int> */
    private function idsForRole(string $roleKey): array
    {
        $ids    = [];
        $offset = 0;
        $limit  = 200;

        do {
            $batch = array_map('intval', get_users([
                'role'   => $roleKey,
                'fields' => 'ID',
                'number' => $limit,
                'offset' => $offset,
            ]));
            array_push($ids, ...$batch);
            $offset += $limit;
        } while (count($batch) === $limit);

        return $ids;
    }
}
