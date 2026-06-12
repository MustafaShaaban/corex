<?php

/**
 * @package Corex\Kit
 */

declare(strict_types=1);

namespace Corex\Kit;

defined('ABSPATH') || exit;

/**
 * The pure planning core behind the setup wizard: it turns the registered blueprints
 * into a choosable list, and a chosen blueprint into an activation plan (which modules
 * to activate + which feature flags to enable). No WordPress — the admin screen runs
 * the plan (activate plugins, set options) behind a nonce + capability check.
 */
final class SetupWizard
{
    public function __construct(private readonly BlueprintRegistry $registry)
    {
    }

    /**
     * The kits a user can choose from, with what each needs.
     *
     * @return list<array{name:string,required:list<string>,recommended:list<string>,flags:list<string>}>
     */
    public function kits(): array
    {
        $kits = [];

        foreach ($this->registry->all() as $blueprint) {
            $kits[] = [
                'name'        => $blueprint->name(),
                'required'    => $blueprint->requiredModules(),
                'recommended' => $blueprint->recommendedModules(),
                'flags'       => $blueprint->featureFlags(),
            ];
        }

        return $kits;
    }

    /**
     * The activation plan for a kit by name: the modules to activate (required +
     * recommended, de-duped, order preserved) and the feature flags to enable. Returns
     * an empty plan for an unknown kit.
     *
     * @return array{modules:list<string>,flags:list<string>,pages:list<array{title:string,slug:string,content:string,front?:bool}>}
     */
    public function plan(string $name): array
    {
        $blueprint = $this->registry->find($name);

        if ($blueprint === null) {
            return ['modules' => [], 'flags' => [], 'pages' => []];
        }

        $modules = array_values(array_unique(
            array_merge($blueprint->requiredModules(), $blueprint->recommendedModules())
        ));

        return [
            'modules' => $modules,
            'flags'   => $blueprint->featureFlags(),
            'pages'   => $blueprint->pages(),
        ];
    }
}
