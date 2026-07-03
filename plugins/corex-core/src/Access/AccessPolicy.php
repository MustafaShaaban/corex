<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Access;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * Pure guard against locked definitions, self-lockout, and last-administrator removal.
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
     */
    public function preview(
        int $actorId,
        array $changes,
        array $affectedUserIds,
        array $fullAccessAdminIds,
    ): AccessChangePreview {
        if ($actorId < 1) {
            throw new InvalidArgumentException('Access changes require an authenticated actor.');
        }

        $blockers = [];

        foreach ($changes as $abilityKey => $effect) {
            $ability = $this->catalog->find($abilityKey);

            if ($ability === null) {
                throw new InvalidArgumentException(sprintf('Unknown CoreX ability "%s".', $abilityKey));
            }

            if (! in_array($effect, self::EFFECTS, true)) {
                throw new InvalidArgumentException(sprintf('Unsupported access effect "%s".', $effect));
            }

            if ($ability->locked) {
                $blockers[] = ['code' => 'ability_locked', 'ability' => $abilityKey];
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
