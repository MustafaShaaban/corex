<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Routing;

defined('ABSPATH') || exit;

/**
 * Who a submission is assigned to: a user, a team or a role, or nobody (spec 122, FR-008).
 *
 * These are the owner types the inbox's access scope can match a person against, so a submission
 * assigned to one of them is seen by that person, that team's members or that role's holders.
 */
final readonly class Assignee
{
    public const USER = 'user';
    public const TEAM = 'team';
    public const ROLE = 'role';
    public const NONE = 'none';

    private function __construct(
        public string $type,
        public string $key,
    ) {
    }

    public static function user(int $userId): self
    {
        return new self(self::USER, (string) $userId);
    }

    public static function team(string $key): self
    {
        return new self(self::TEAM, $key);
    }

    public static function role(string $key): self
    {
        return new self(self::ROLE, $key);
    }

    public static function none(): self
    {
        return new self(self::NONE, '');
    }

    /**
     * An assignee from what a routing extension or a value-to-owner row returned: a user ID, an
     * `Assignee`, or `['type' => 'user'|'team'|'role', 'value' => …]`. Anything else is nobody.
     */
    public static function from(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value->isNone() ? null : $value;
        }
        if (is_int($value) && $value > 0) {
            return self::user($value);
        }
        if (! is_array($value)) {
            return null;
        }
        $key = trim((string) ($value['value'] ?? $value['key'] ?? ''));

        return match ((string) ($value['type'] ?? '')) {
            self::USER => (int) $key > 0 ? self::user((int) $key) : null,
            self::TEAM => $key !== '' ? self::team(sanitize_key($key)) : null,
            self::ROLE => $key !== '' ? self::role(sanitize_key($key)) : null,
            default => null,
        };
    }

    public function isNone(): bool
    {
        return $this->type === self::NONE;
    }

    /** @return array{type:string,key:string} */
    public function toArray(): array
    {
        return ['type' => $this->type, 'key' => $this->key];
    }
}
