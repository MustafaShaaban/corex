<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Access;

defined('ABSPATH') || exit;

use Corex\Access\AbilityNames;
use Corex\Access\AccessPolicy;
use Corex\Access\CorexAbility;
use Corex\Access\CorexAbilityCatalog;

/**
 * Pure model for Access & Abilities (spec 067 tabs; spec 117, US-ACC-02 and US-ACC-03): what each role
 * can really do with each CoreX ability, how many it holds, and how the signed-in person holds theirs.
 *
 * It resolves every cell the way {@see AbilityCompatibility} answers WordPress: a role that can
 * administer the site holds everything and is never denied (ACC-D11); the locked ability comes only
 * with site administration; otherwise the role matrix's stored Allow or Deny, or nothing. Roles arrive
 * with their WordPress capabilities, so this class needs no WordPress to be tested.
 */
final class AccessMatrix
{
    public const STATE_ALLOWED_SET   = 'allowed_set';
    public const STATE_DENIED_SET    = 'denied_set';
    public const STATE_ALLOWED_ADMIN = 'allowed_admin';
    public const STATE_NOT_GRANTED   = 'not_granted';
    public const STATE_LOCKED        = 'locked';

    /** How a person holds an ability, for "Your permissions". */
    public const HELD_SITE_ADMINISTRATION = 'site_administration';
    public const HELD_ROLE                = 'role';
    public const HELD_GRANT               = 'grant';
    public const HELD_ACCOUNT             = 'account';

    /**
     * The roles WordPress ships with; anything else on the site was added by a plugin or the site.
     */
    private const CORE_ROLES = ['administrator', 'editor', 'author', 'contributor', 'subscriber'];

    private const ADMINISTRATION_CAPABILITY = 'manage_options';

    /**
     * Whether a role's capabilities let it administer the site.
     *
     * @param array<string,bool> $capabilities
     */
    public function administers(array $capabilities): bool
    {
        return ! empty($capabilities[self::ADMINISTRATION_CAPABILITY]);
    }

    /** One cell's state, the way WordPress will answer for the role's members. */
    public function cellState(CorexAbility $ability, bool $administers, string $effect): string
    {
        return match (true) {
            $administers                           => self::STATE_ALLOWED_ADMIN,
            $ability->locked                       => self::STATE_LOCKED,
            $effect === AccessPolicy::EFFECT_ALLOW => self::STATE_ALLOWED_SET,
            $effect === AccessPolicy::EFFECT_DENY  => self::STATE_DENIED_SET,
            default                                => self::STATE_NOT_GRANTED,
        };
    }

    /**
     * Role summary cards: the real user count, whether WordPress ships the role, and how many CoreX
     * abilities it holds, counted as the matrix resolves them (US-ACC-03).
     *
     * @param list<array{key:string,name:string,caps:array<string,bool>}> $roles
     * @param array<string,int>                                           $userCounts    role key => users
     * @param array<string,array<string,string>>                          $effectsByRole role key => ability key => effect
     *
     * @return list<array{key:string,name:string,isCore:bool,users:int,granted:int,total:int}>
     */
    public function roleSummaries(array $roles, array $userCounts, array $effectsByRole = []): array
    {
        $abilities = $this->abilities();

        return array_map(function (array $role) use ($abilities, $userCounts, $effectsByRole): array {
            $administers = $this->administers($role['caps']);
            $granted     = 0;
            foreach ($abilities as $ability) {
                $effect = $effectsByRole[$role['key']][$ability->key] ?? AccessPolicy::EFFECT_INHERIT;
                $state  = $this->cellState($ability, $administers, $effect);
                if ($state === self::STATE_ALLOWED_ADMIN || $state === self::STATE_ALLOWED_SET) {
                    $granted++;
                }
            }

            return [
                'key'     => $role['key'],
                'name'    => $role['name'],
                'isCore'  => in_array($role['key'], self::CORE_ROLES, true),
                'users'   => max(0, (int) ($userCounts[$role['key']] ?? 0)),
                'granted' => $granted,
                'total'   => count($abilities),
            ];
        }, $roles);
    }

    /**
     * Every CoreX ability by area, in the site's language, with its risk in words and whether it is
     * locked (US-ACC-03).
     *
     * @return array<string,list<array{key:string,label:string,description:string,risk:string,riskLabel:string,locked:bool}>>
     */
    public function abilityGroups(): array
    {
        $groups = [];
        foreach ($this->abilities() as $ability) {
            $groups[AbilityNames::area($ability->group)][] = [
                'key'         => $ability->key,
                'label'       => AbilityNames::labelOf($ability),
                'description' => AbilityNames::descriptionOf($ability),
                'risk'        => $ability->risk,
                'riskLabel'   => AbilityNames::risk($ability->risk),
                'locked'      => $ability->locked,
            ];
        }

        return $groups;
    }

    /**
     * Which known role/capability-manager plugins are REALLY active (design: overview
     * compatibility notice). Returns their display names; empty when none is active.
     *
     * @param list<string> $activePlugins plugin basenames from the active_plugins option
     *
     * @return list<string>
     */
    public function conflicts(array $activePlugins): array
    {
        return (new RolePluginCompatibility())->detect($activePlugins);
    }

    /**
     * The role matrix grid (US-ACC-02): every CoreX ability, in area order, against every role, each
     * cell with its stored effect, whether it can be edited here and why not, and its resolved state.
     * A Deny stored for a role that administers the site is shown as the allowed state it resolves to
     * and flagged, so the screen can say it is ignored (ACC-D11).
     *
     * @param list<array{key:string,name:string,caps?:array<string,bool>}> $roles
     * @param array<string,array<string,string>>                           $effectsByRole role key => ability key => effect
     * @param list<string>                                                 $activePlugins
     *
     * @return array{
     *   roles:list<array{key:string,name:string,administers:bool}>,
     *   rows:list<array<string,mixed>>,
     *   conflicts:list<string>,
     *   nativeCapabilitiesEditable:bool
     * }
     */
    public function editableCorexMatrix(array $roles, array $effectsByRole, array $activePlugins): array
    {
        $conflicts = $this->conflicts($activePlugins);
        $heads     = array_map(fn (array $role): array => [
            'key'         => (string) $role['key'],
            'name'        => (string) $role['name'],
            'administers' => $this->administers($role['caps'] ?? []),
        ], $roles);

        $rows = [];
        foreach ($this->abilities() as $ability) {
            $rows[] = [
                'key'         => $ability->key,
                'label'       => AbilityNames::labelOf($ability),
                'description' => AbilityNames::descriptionOf($ability),
                'group'       => $ability->group,
                'groupLabel'  => AbilityNames::area($ability->group),
                'risk'        => $ability->risk,
                'riskLabel'   => AbilityNames::risk($ability->risk),
                'locked'      => $ability->locked,
                'cells'       => $this->cells($ability, $heads, $effectsByRole),
            ];
        }

        return [
            'roles'                      => $heads,
            'rows'                       => $rows,
            'conflicts'                  => $conflicts,
            'nativeCapabilitiesEditable' => $conflicts === [],
        ];
    }

    /**
     * Each Deny stored for a role that administers the site, which is kept and not applied (ACC-D11).
     *
     * @param list<array{key:string,name:string,caps:array<string,bool>}> $roles
     * @param array<string,array<string,string>>                          $effectsByRole
     *
     * @return list<array{role:string,ability:string}>
     */
    public function ignoredDenies(array $roles, array $effectsByRole): array
    {
        $ignored = [];
        foreach ($roles as $role) {
            if (! $this->administers($role['caps'])) {
                continue;
            }

            foreach ($this->abilities() as $ability) {
                if (($effectsByRole[$role['key']][$ability->key] ?? null) === AccessPolicy::EFFECT_DENY) {
                    $ignored[] = ['role' => $role['name'], 'ability' => AbilityNames::labelOf($ability)];
                }
            }
        }

        return $ignored;
    }

    /**
     * The CoreX abilities a person holds and how (US-ACC-03): site administration, one of their roles,
     * a grant to them (with its end, when it has one), or their account otherwise.
     *
     * @param array<string,bool>                                          $held          ability key => whether WordPress says they hold it
     * @param list<array{key:string,name:string,caps:array<string,bool>}> $roles         their roles
     * @param array<string,array<string,string>>                          $effectsByRole
     * @param array<string,string|null>                                   $grants        ability key => end of the grant (ISO 8601) or null
     *
     * @return list<array{key:string,label:string,how:string,role:string|null,until:string|null}>
     */
    public function forUser(array $held, array $roles, array $effectsByRole, array $grants): array
    {
        $administers = array_filter($roles, fn (array $role): bool => $this->administers($role['caps'])) !== [];
        $out         = [];

        foreach ($this->abilities() as $ability) {
            if (empty($held[$ability->key])) {
                continue;
            }

            $role = $this->allowingRole($ability->key, $roles, $effectsByRole);
            $how  = match (true) {
                $administers                             => self::HELD_SITE_ADMINISTRATION,
                $role !== null                           => self::HELD_ROLE,
                array_key_exists($ability->key, $grants) => self::HELD_GRANT,
                default                                  => self::HELD_ACCOUNT,
            };

            $out[] = [
                'key'   => $ability->key,
                'label' => AbilityNames::labelOf($ability),
                'how'   => $how,
                'role'  => $how === self::HELD_ROLE ? $role : null,
                'until' => $how === self::HELD_GRANT ? $grants[$ability->key] : null,
            ];
        }

        return $out;
    }

    /**
     * @param list<array{key:string,name:string,administers:bool}> $heads
     * @param array<string,array<string,string>>                  $effectsByRole
     *
     * @return array<string,array{effect:string,editable:bool,reason:string|null,state:string,ignoredDeny:bool}>
     */
    private function cells(CorexAbility $ability, array $heads, array $effectsByRole): array
    {
        $cells = [];
        foreach ($heads as $role) {
            $effect = $this->effect($effectsByRole[$role['key']][$ability->key] ?? AccessPolicy::EFFECT_INHERIT);
            $reason = match (true) {
                $role['administers'] => 'site_administrator',
                $ability->locked     => 'locked_definition',
                default              => null,
            };

            $cells[$role['key']] = [
                'effect'      => $effect,
                'editable'    => $reason === null,
                'reason'      => $reason,
                'state'       => $this->cellState($ability, $role['administers'], $effect),
                'ignoredDeny' => $role['administers'] && $effect === AccessPolicy::EFFECT_DENY,
            ];
        }

        return $cells;
    }

    /**
     * The first of these roles the matrix allows the ability for, by name.
     *
     * @param list<array{key:string,name:string,caps:array<string,bool>}> $roles
     * @param array<string,array<string,string>>                          $effectsByRole
     */
    private function allowingRole(string $abilityKey, array $roles, array $effectsByRole): ?string
    {
        foreach ($roles as $role) {
            if (($effectsByRole[$role['key']][$abilityKey] ?? null) === AccessPolicy::EFFECT_ALLOW) {
                return $role['name'];
            }
        }

        return null;
    }

    /** @return list<CorexAbility> */
    private function abilities(): array
    {
        return CorexAbilityCatalog::defaults()->all();
    }

    private function effect(string $effect): string
    {
        return in_array($effect, [AccessPolicy::EFFECT_ALLOW, AccessPolicy::EFFECT_DENY, AccessPolicy::EFFECT_INHERIT], true)
            ? $effect
            : AccessPolicy::EFFECT_INHERIT;
    }
}
