<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Access;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * Pure guard against locked definitions, self-lockout, last-administrator removal, and a Deny on a
 * role whose members administer the site, who always hold every CoreX ability (spec 117, ACC-D11).
 */
final class AccessPolicy
{
    public const EFFECT_ALLOW   = 'allow';
    public const EFFECT_DENY    = 'deny';
    public const EFFECT_INHERIT = 'inherit';

    private const EFFECTS = [self::EFFECT_ALLOW, self::EFFECT_DENY, self::EFFECT_INHERIT];

    public function __construct(private readonly CorexAbilityCatalog $catalog)
    {
    }

    /**
     * @param array<string,string> $changes
     * @param list<int>            $affectedUserIds
     * @param list<int>            $fullAccessAdminIds
     * @param bool                 $administersSite    Whether the role changed is one that administers the site.
     */
    public function preview(
        int $actorId,
        array $changes,
        array $affectedUserIds,
        array $fullAccessAdminIds,
        bool $administersSite = false,
    ): AccessChangePreview {
        if ($actorId < 1) {
            throw new InvalidArgumentException('Access changes require an authenticated actor.');
        }

        $blockers = [];

        foreach ($changes as $abilityKey => $effect) {
            // PHP keeps the key "123" of an array as the integer 123, and a list sent where a map
            // was meant has integers for keys. Neither is an ability, and both are refused as one
            // that is not known and not by the type of the catalog's argument.
            $ability = $this->catalog->find((string) $abilityKey);

            if ($ability === null) {
                throw new InvalidAccessInput('changes', InvalidAccessInput::ABILITY_UNKNOWN, 'A change names an unknown CoreX ability.');
            }

            if (! in_array($effect, self::EFFECTS, true)) {
                throw new InvalidAccessInput('changes', InvalidAccessInput::EFFECT_UNSUPPORTED, 'A change names an unsupported access effect.');
            }

            if ($ability->locked) {
                $blockers[] = ['code' => 'ability_locked', 'ability' => $abilityKey];
                continue;
            }

            if ($effect === self::EFFECT_DENY && $administersSite) {
                $blockers[] = ['code' => 'site_administrator_role', 'ability' => $abilityKey];
                continue;
            }

            if ($effect !== self::EFFECT_DENY || $ability->risk !== CorexAbility::RISK_CRITICAL) {
                continue;
            }

            if (in_array($actorId, $affectedUserIds, true)) {
                $blockers[] = ['code' => 'self_lockout', 'ability' => $abilityKey];
            }

            if (array_diff($fullAccessAdminIds, $affectedUserIds) === []) {
                $blockers[] = ['code' => 'last_full_access_admin', 'ability' => $abilityKey];
            }
        }

        return new AccessChangePreview($blockers === [], $blockers, $changes);
    }
}
