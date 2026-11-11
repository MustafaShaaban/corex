<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Routing\Teams;

defined('ABSPATH') || exit;

/**
 * A named group of people a submission can be routed to (spec 122, FR-012).
 */
final readonly class Team
{
    /** @param list<int> $members User IDs. */
    public function __construct(
        public string $key,
        public string $name,
        public array $members = [],
    ) {
    }

    /** @param array<string,mixed> $stored */
    public static function fromArray(array $stored): ?self
    {
        $key = sanitize_key((string) ($stored['key'] ?? ''));
        $name = trim((string) ($stored['name'] ?? ''));
        if ($key === '' || $name === '') {
            return null;
        }

        return new self($key, $name, self::memberIds($stored['members'] ?? []));
    }

    /** @return list<int> */
    public static function memberIds(mixed $members): array
    {
        $ids = array_filter(array_map('absint', is_array($members) ? $members : []));

        return array_values(array_unique($ids));
    }

    public function has(int $userId): bool
    {
        return in_array($userId, $this->members, true);
    }

    /** @return array{key:string,name:string,members:list<int>} */
    public function toArray(): array
    {
        return ['key' => $this->key, 'name' => $this->name, 'members' => $this->members];
    }
}
