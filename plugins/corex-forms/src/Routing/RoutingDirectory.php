<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Routing;

defined('ABSPATH') || exit;

use Corex\Forms\Routing\Teams\Team;

/**
 * What routing asks of the site about people, teams, roles and pages (spec 122). The WordPress
 * answer is {@see WpRoutingDirectory}; tests hand in their own.
 */
interface RoutingDirectory
{
    public function person(int $userId): ?RoutingPerson;

    public function team(string $key): ?Team;

    /** The role's translated name, or null when the site has no such role. */
    public function roleName(string $role): ?string;

    /**
     * Up to `$limit` people who hold the role, in a stable order.
     *
     * @return list<RoutingPerson>
     */
    public function peopleWithRole(string $role, int $limit): array;

    /** The author of a page or post, or null when it does not exist or has no author. */
    public function pageAuthor(int $pageId): ?int;

    /** The site administrator's address, the last place a routed email can go. */
    public function adminEmail(): string;

    /**
     * The people an owner can pick from, by name, at most `$limit` of them.
     *
     * @return list<RoutingPerson>
     */
    public function people(int $limit): array;

    /** @return array<string,string> Every role's key and translated name. */
    public function roles(): array;
}
