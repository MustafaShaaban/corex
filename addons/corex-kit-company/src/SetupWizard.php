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
     * @param string $level minimal|standard|full — the demo-content level (FR-137)
     *
     * @return array{modules:list<string>,flags:list<string>,pages:list<array{title:string,slug:string,content:string,front?:bool}>}
     */
    public function plan(string $name, string $level = 'standard'): array
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
            'pages'   => $blueprint->pages($level),
        ];
    }

    /**
     * The demo-content levels the wizard offers (FR-137). Each drives a real, differentiated page
     * set via {@see \Corex\Kit\Company\CompanyBlueprint::pages()} — never a cosmetic toggle.
     *
     * @return list<array{id:string,label:string,description:string}>
     */
    public function demoLevels(): array
    {
        return [
            ['id' => 'minimal', 'label' => __('Minimal', 'corex'), 'description' => __('Only the essential and legal pages, ready for your own content.', 'corex')],
            ['id' => 'standard', 'label' => __('Standard', 'corex'), 'description' => __('Essential pages plus the representative marketing pages.', 'corex')],
            ['id' => 'full', 'label' => __('Full Demo', 'corex'), 'description' => __('The complete showcase with every designed page.', 'corex')],
        ];
    }

    /**
     * The choices offered for a page that already holds user content (FR-139). Each maps to a real
     * {@see \Corex\Kit\Setup\ConflictResolver} outcome; the default is always Keep Mine (FR-143).
     *
     * @return list<array{id:string,label:string,description:string}>
     */
    public function conflictChoices(): array
    {
        return [
            ['id' => 'keep', 'label' => __('Keep mine', 'corex'), 'description' => __('Leave your page untouched; the kit page is skipped.', 'corex')],
            ['id' => 'replace', 'label' => __('Replace', 'corex'), 'description' => __('Replace your page content with the kit page.', 'corex')],
            ['id' => 'suffix', 'label' => __('Create suffixed slug', 'corex'), 'description' => __('Keep your page and add the kit page under a new slug.', 'corex')],
        ];
    }

    /**
     * The brand fields the wizard's brand step captures (FR-135). Each `key` is a REAL Config
     * dot-key registered in the Settings brand section, so saving persists to the prefixed option
     * the framework reads — these are never placeholder inputs.
     *
     * @return list<array{key:string,label:string}>
     */
    public function brandFields(): array
    {
        return [
            ['key' => 'brand.company_name', 'label' => __('Company name', 'corex')],
            ['key' => 'brand.tagline', 'label' => __('Tagline', 'corex')],
            ['key' => 'brand.phone', 'label' => __('Contact phone', 'corex')],
            ['key' => 'brand.email', 'label' => __('Contact email', 'corex')],
            ['key' => 'brand.address', 'label' => __('Address', 'corex')],
            ['key' => 'brand.primary_action_label', 'label' => __('Primary action label', 'corex')],
            ['key' => 'brand.primary_action_link', 'label' => __('Primary action link', 'corex')],
            ['key' => 'brand.social_links', 'label' => __('Social links', 'corex')],
        ];
    }
}
