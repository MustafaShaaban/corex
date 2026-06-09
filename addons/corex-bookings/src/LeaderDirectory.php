<?php

/**
 * @package Corex\Bookings
 */

declare(strict_types=1);

namespace Corex\Bookings;

defined('ABSPATH') || exit;

/**
 * The configured company leaders a visitor can request a call with. Pure.
 */
final class LeaderDirectory
{
    /**
     * @param list<array{id:string,name:string,email:string}> $leaders
     */
    public function __construct(private readonly array $leaders)
    {
    }

    /**
     * @return array{id:string,name:string,email:string}|null
     */
    public function find(string $id): ?array
    {
        foreach ($this->leaders as $leader) {
            if ($leader['id'] === $id) {
                return $leader;
            }
        }

        return null;
    }

    /**
     * Public list (no emails) for the form.
     *
     * @return list<array{id:string,name:string}>
     */
    public function all(): array
    {
        return array_map(static fn (array $leader): array => ['id' => $leader['id'], 'name' => $leader['name']], $this->leaders);
    }
}
