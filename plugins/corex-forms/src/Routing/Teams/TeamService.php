<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Routing\Teams;

defined('ABSPATH') || exit;

use DomainException;

/**
 * Creates, changes and removes teams, and says which teams a person belongs to (spec 122, FR-012).
 *
 * A team's key is made from its first name and never changes, so a submission assigned to a team
 * stays assigned to it when the team is renamed.
 */
final readonly class TeamService
{
    private const MAX_NAME_LENGTH = 100;

    public function __construct(private TeamStore $store)
    {
    }

    /** @return list<Team> */
    public function all(): array
    {
        $teams = $this->store->all();
        usort($teams, static fn (Team $left, Team $right): int => strcasecmp($left->name, $right->name));

        return $teams;
    }

    public function find(string $key): ?Team
    {
        foreach ($this->store->all() as $team) {
            if ($team->key === $key) {
                return $team;
            }
        }

        return null;
    }

    /** @param list<int> $members */
    public function create(string $name, array $members): Team
    {
        $name = $this->validName($name);
        $teams = $this->store->all();
        $team = new Team($this->freeKey($name, $teams), $name, Team::memberIds($members));
        $this->store->replace([...$teams, $team]);

        return $team;
    }

    /** @param list<int> $members */
    public function update(string $key, string $name, array $members): Team
    {
        $name = $this->validName($name);
        $found = false;
        $teams = array_map(
            static function (Team $team) use ($key, $name, $members, &$found): Team {
                if ($team->key !== $key) {
                    return $team;
                }
                $found = true;

                return new Team($key, $name, Team::memberIds($members));
            },
            $this->store->all(),
        );
        if (! $found) {
            throw new DomainException(__('The team was not found.', 'corex'));
        }
        $this->store->replace($teams);

        return $this->find($key) ?? throw new DomainException(__('The team was not found.', 'corex'));
    }

    public function delete(string $key): void
    {
        $teams = $this->store->all();
        $kept = array_values(array_filter($teams, static fn (Team $team): bool => $team->key !== $key));
        if (count($kept) === count($teams)) {
            throw new DomainException(__('The team was not found.', 'corex'));
        }
        $this->store->replace($kept);
    }

    /** @return list<string> The keys of the teams the person is a member of. */
    public function teamsOf(int $userId): array
    {
        $keys = [];
        foreach ($this->store->all() as $team) {
            if ($team->has($userId)) {
                $keys[] = $team->key;
            }
        }

        return $keys;
    }

    private function validName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            throw new DomainException(__('A team needs a name.', 'corex'));
        }
        if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw new DomainException(__('A team name can be at most 100 characters.', 'corex'));
        }

        return $name;
    }

    /** @param list<Team> $teams */
    private function freeKey(string $name, array $teams): string
    {
        $base = sanitize_key(str_replace(' ', '-', remove_accents($name)));
        $base = $base !== '' ? substr($base, 0, 60) : 'team';
        $taken = array_map(static fn (Team $team): string => $team->key, $teams);
        $key = $base;
        for ($suffix = 2; in_array($key, $taken, true); $suffix++) {
            $key = $base . '-' . $suffix;
        }

        return $key;
    }
}
