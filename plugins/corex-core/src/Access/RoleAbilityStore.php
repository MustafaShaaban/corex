<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Access;

defined('ABSPATH') || exit;

use DateTimeImmutable;

interface RoleAbilityStore
{
    /** @return array<string,string> */
    public function effectsForRole(string $roleKey): array;

    /** @param array<string,string> $changes */
    public function apply(string $roleKey, array $changes, int $actorId, DateTimeImmutable $updatedAt): void;
}
