<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Routing;

defined('ABSPATH') || exit;

use Corex\Forms\Routing\Teams\Team;
use Corex\Forms\Routing\Teams\TeamService;
use WP_Post;
use WP_User;

/**
 * Routing's questions answered by WordPress: users, roles, posts, and CoreX's teams.
 */
final readonly class WpRoutingDirectory implements RoutingDirectory
{
    public function __construct(private TeamService $teams)
    {
    }

    public function person(int $userId): ?RoutingPerson
    {
        $user = $userId > 0 ? get_userdata($userId) : false;

        return $user instanceof WP_User ? $this->personFrom($user) : null;
    }

    public function team(string $key): ?Team
    {
        return $this->teams->find($key);
    }

    public function roleName(string $role): ?string
    {
        $names = wp_roles()->get_names();

        return isset($names[$role]) ? translate_user_role((string) $names[$role]) : null;
    }

    public function peopleWithRole(string $role, int $limit): array
    {
        $users = get_users([
            'role' => $role,
            'number' => max(1, $limit),
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);

        return array_values(array_map($this->personFrom(...), array_filter($users, static fn ($user): bool => $user instanceof WP_User)));
    }

    public function pageAuthor(int $pageId): ?int
    {
        $post = $pageId > 0 ? get_post($pageId) : null;
        if (! $post instanceof WP_Post || (int) $post->post_author < 1) {
            return null;
        }

        return get_userdata((int) $post->post_author) instanceof WP_User ? (int) $post->post_author : null;
    }

    public function adminEmail(): string
    {
        return (string) get_option('admin_email');
    }

    public function people(int $limit): array
    {
        $users = get_users(['orderby' => 'display_name', 'order' => 'ASC', 'number' => max(1, $limit)]);

        return array_values(array_map($this->personFrom(...), array_filter($users, static fn ($user): bool => $user instanceof WP_User)));
    }

    public function roles(): array
    {
        return array_map(static fn (string $name): string => translate_user_role($name), wp_roles()->get_names());
    }

    private function personFrom(WP_User $user): RoutingPerson
    {
        $email = (string) $user->user_email;

        return new RoutingPerson(
            (int) $user->ID,
            (string) $user->display_name,
            is_email($email) ? $email : '',
        );
    }
}
