<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Addons;

defined('ABSPATH') || exit;

/**
 * The dependency-aware decisions behind the add-on screen — pure, so the safety property
 * (you can't break a dependency) is unit-testable with no WordPress. Disabling an add-on
 * an active add-on requires is refused; enabling one whose required dependency is inactive
 * is refused. The manager reasons only over the AddonState it is given.
 */
final class AddonManager
{
    public function __construct(private readonly AddonRegistry $registry)
    {
    }

    /**
     * Active add-ons that require $slug (so $slug cannot be disabled while they are on).
     *
     * @return list<string>
     */
    public function blockingDependents(string $slug, AddonState $state): array
    {
        $dependents = [];

        foreach ($this->registry->all() as $addon) {
            if ($state->isActive($addon->slug) && in_array($slug, $addon->requires, true)) {
                $dependents[] = $addon->slug;
            }
        }

        return $dependents;
    }

    public function canDisable(string $slug, AddonState $state): bool
    {
        return $this->blockingDependents($slug, $state) === [];
    }

    /**
     * Required dependencies of $slug that are not active (so $slug cannot be enabled yet).
     *
     * @return list<string>
     */
    public function missingDependencies(string $slug, AddonState $state): array
    {
        $addon = $this->registry->find($slug);

        if ($addon === null) {
            return [];
        }

        return array_values(array_filter(
            $addon->requires,
            static fn (string $dep): bool => ! $state->isActive($dep),
        ));
    }

    public function canEnable(string $slug, AddonState $state): bool
    {
        return $this->missingDependencies($slug, $state) === [];
    }

    /**
     * One view per registered add-on: its state plus, when the toggle currently available
     * (disable if active, enable if inactive) would break a dependency, the reason.
     *
     * @param callable(string):bool $installed whether the add-on's plugin file is present
     *
     * @return list<AddonView>
     */
    public function views(AddonState $state, callable $installed): array
    {
        $views = [];

        foreach ($this->registry->all() as $addon) {
            $isActive = $state->isActive($addon->slug);

            $views[] = new AddonView(
                addon: $addon,
                installed: (bool) $installed($addon->slug),
                active: $isActive,
                flagOn: $addon->hasFlag() && $state->flagOn((string) $addon->flag),
                blockedReason: $this->blockedReason($addon->slug, $isActive, $state),
                // An active add-on whose own required dependencies are inactive is in the
                // truthful "dependency missing" state (spec 060 / M6 US1).
                dependencyMissing: $isActive && $this->missingDependencies($addon->slug, $state) !== [],
            );
        }

        return $views;
    }

    private function blockedReason(string $slug, bool $isActive, AddonState $state): ?string
    {
        if ($isActive) {
            $dependents = $this->blockingDependents($slug, $state);

            return $dependents === []
                ? null
                : sprintf('Required by: %s', implode(', ', $dependents));
        }

        $missing = $this->missingDependencies($slug, $state);

        return $missing === []
            ? null
            : sprintf('Requires (inactive): %s', implode(', ', $missing));
    }
}
