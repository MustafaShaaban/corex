<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Access;

defined('ABSPATH') || exit;

use Corex\Access\AccessPolicy;
use Corex\Access\CorexAbility;
use Corex\Access\CorexAbilityCatalog;
use Corex\Access\RoleAbilityStore;
use WP_User;

/**
 * Gives CoreX abilities to the people who hold them: everyone who can administer the site holds all of
 * them, and the role matrix's effects apply to everyone else.
 *
 * A site administrator is given every ability and no role effect is applied to them, so a Deny stored
 * on such a role before spec 117 is kept in the table and never takes anything away (ACC-D11). On a
 * network a super administrator passes every check before this filter runs, as WordPress decides.
 */
final class AbilityCompatibility
{
    /** @var array<string,array<string,string>> */
    private array $effects = [];

    public function __construct(
        private readonly CorexAbilityCatalog $catalog,
        private readonly RoleAbilityStore $roles,
    ) {
    }

    public function register(): void
    {
        add_filter('user_has_cap', [$this, 'filter'], 20, 4);
    }

    /**
     * @param array<string,bool> $allCaps
     * @param list<string>       $requiredCaps
     * @param array<mixed>       $args
     *
     * @return array<string,bool>
     */
    public function filter(array $allCaps, array $requiredCaps, array $args, WP_User $user): array
    {
        if (! empty($allCaps['manage_options'])) {
            foreach ($this->catalog->expanded([CorexAbility::MANAGE_ADMIN]) as $abilityKey) {
                $allCaps[$abilityKey] = true;
            }

            return $allCaps;
        }

        foreach ($user->roles as $roleKey) {
            foreach ($this->effectsForRole($roleKey) as $abilityKey => $effect) {
                if ($this->catalog->find($abilityKey) === null) {
                    continue;
                }

                if ($effect === AccessPolicy::EFFECT_ALLOW) {
                    $allCaps[$abilityKey] = true;
                } elseif ($effect === AccessPolicy::EFFECT_DENY) {
                    $allCaps[$abilityKey] = false;
                }
            }
        }

        return $allCaps;
    }

    /** @return array<string,string> */
    private function effectsForRole(string $roleKey): array
    {
        if (! array_key_exists($roleKey, $this->effects)) {
            $this->effects[$roleKey] = $this->roles->effectsForRole($roleKey);
        }

        return $this->effects[$roleKey];
    }
}
