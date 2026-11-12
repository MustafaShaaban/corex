<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Access;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * Pure guard against locked definitions, self-lockout, last-administrator removal, a Deny on a
 * role whose members administer the site, who always hold every CoreX ability (spec 117, ACC-D11),
 * and the changes that only a site administrator may make (DECISIONS #428): allowing a Critical
 * ability, and changing access that applies to the person making the change.
 */
final class AccessPolicy
{
    public const EFFECT_ALLOW   = 'allow';
    public const EFFECT_DENY    = 'deny';
    public const EFFECT_INHERIT = 'inherit';

    private const EFFECTS = [self::EFFECT_ALLOW, self::EFFECT_DENY, self::EFFECT_INHERIT];

    /** Blocker: a change to access that applies to the person making it, by someone who does not administer the site. */
    public const OWN_ACCESS = 'own_access_needs_site_administrator';

    /** Blocker: an Allow or Inherit of a Critical ability by someone who does not administer the site. */
    public const CRITICAL_ALLOW = 'critical_needs_site_administrator';

    public function __construct(private readonly CorexAbilityCatalog $catalog)
    {
    }

    /**
     * @param array<string,string> $changes
     * @param list<int>            $affectedUserIds
     * @param list<int>            $fullAccessAdminIds
     * @param bool                 $administersSite      Whether the role changed is one that administers the site.
     * @param bool                 $actorAdministersSite Whether the person making the change administers the
     *                                                   site. Defaults to no, so a caller that does not say
     *                                                   gets the narrower answer.
     */
    public function preview(
        int $actorId,
        array $changes,
        array $affectedUserIds,
        array $fullAccessAdminIds,
        bool $administersSite = false,
        bool $actorAdministersSite = false,
    ): AccessChangePreview {
        if ($actorId < 1) {
            throw new InvalidArgumentException('Access changes require an authenticated actor.');
        }

        $blockers = [];
        $ownAccess = ! $actorAdministersSite && in_array($actorId, $affectedUserIds, true);

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

            if ($ownAccess) {
                $blockers[] = ['code' => self::OWN_ACCESS, 'ability' => $abilityKey];
                continue;
            }

            // Inherit is included: it hands the ability back to whatever WordPress gives the role,
            // which a role plugin may have written. Only a Deny narrows for certain.
            if ($effect !== self::EFFECT_DENY && $ability->risk === CorexAbility::RISK_CRITICAL && ! $actorAdministersSite) {
                $blockers[] = ['code' => self::CRITICAL_ALLOW, 'ability' => $abilityKey];
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
