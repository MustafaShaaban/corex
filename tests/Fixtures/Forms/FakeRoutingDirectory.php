<?php

/**
 * @package Corex\Tests\Fixtures\Forms
 */

declare(strict_types=1);

namespace Corex\Tests\Fixtures\Forms;

use Corex\Forms\Routing\RoutingDirectory;
use Corex\Forms\Routing\RoutingPerson;
use Corex\Forms\Routing\Teams\Team;

/**
 * A site's people, teams, roles and pages, held in memory for routing tests.
 */
final class FakeRoutingDirectory implements RoutingDirectory
{
    /**
     * @param array<int,RoutingPerson>       $people
     * @param array<string,Team>             $teams
     * @param array<string,array{name:string,members:list<int>}> $roles
     * @param array<int,int>                 $pageAuthors
     */
    public function __construct(
        public array $people = [],
        public array $teams = [],
        public array $roles = [],
        public array $pageAuthors = [],
        public string $admin = 'admin@example.test',
    ) {
    }

    public function person(int $userId): ?RoutingPerson
    {
        return $this->people[$userId] ?? null;
    }

    public function team(string $key): ?Team
    {
        return $this->teams[$key] ?? null;
    }

    public function roleName(string $role): ?string
    {
        return $this->roles[$role]['name'] ?? null;
    }

    public function peopleWithRole(string $role, int $limit): array
    {
        $members = array_slice($this->roles[$role]['members'] ?? [], 0, $limit);

        return array_values(array_filter(array_map($this->person(...), $members)));
    }

    public function pageAuthor(int $pageId): ?int
    {
        $author = $this->pageAuthors[$pageId] ?? null;

        return $author !== null && isset($this->people[$author]) ? $author : null;
    }

    public function adminEmail(): string
    {
        return $this->admin;
    }

    public function people(int $limit): array
    {
        return array_slice(array_values($this->people), 0, $limit);
    }

    public function roles(): array
    {
        return array_map(static fn (array $role): string => $role['name'], $this->roles);
    }
}
