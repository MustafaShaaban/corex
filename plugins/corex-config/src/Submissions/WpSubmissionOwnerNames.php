<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Corex\Access\CorexAbility;
use WP_User;

/**
 * Names a submission's owner from WordPress: a user by their display name.
 */
final class WpSubmissionOwnerNames implements SubmissionOwnerNames
{
    private const MOST_PEOPLE_LISTED = 100;

    public function nameOf(string $ownerType, string $ownerKey): string
    {
        if ($ownerType !== 'user') {
            return '';
        }

        $user = get_userdata((int) $ownerKey);

        return $user instanceof WP_User ? (string) $user->display_name : '';
    }

    /**
     * Everybody who may manage submissions, by name. Capped: this fills a list a person picks from,
     * and a site with more managers than this assigns by team or role through the bulk action.
     */
    public function people(): array
    {
        $users = get_users([
            'capability__in' => [CorexAbility::MANAGE_SUBMISSIONS, 'manage_options'],
            'fields' => ['ID', 'display_name'],
            'orderby' => 'display_name',
            'number' => self::MOST_PEOPLE_LISTED,
        ]);

        return array_map(
            static fn (object $user): array => ['key' => (string) $user->ID, 'label' => (string) $user->display_name],
            array_values($users),
        );
    }
}
