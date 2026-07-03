<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Access;

defined('ABSPATH') || exit;

interface AccessUserDirectory
{
    /** @return list<int> */
    public function userIdsForRole(string $roleKey): array;

    /** @return list<int> */
    public function fullAccessAdministratorIds(): array;

    public function grantUserAbility(int $userId, string $abilityKey): void;

    public function displayName(int $userId): string;
}
