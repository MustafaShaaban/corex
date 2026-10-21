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

    /**
     * Takes back an ability given to this person, not to their role. Nothing happens for a person
     * who no longer exists (spec 112: an expiry still ends the grant).
     */
    public function revokeUserAbility(int $userId, string $abilityKey): void;

    /**
     * Whether this person was given the ability themself: their own capabilities, not what their
     * role or a filter adds. A grant whose ability was taken away elsewhere answers false.
     */
    public function holdsOwnAbility(int $userId, string $abilityKey): bool;

    public function displayName(int $userId): string;

    /** Whether this account still exists. */
    public function exists(int $userId): bool;

    public function emailAddress(int $userId): string;
}
