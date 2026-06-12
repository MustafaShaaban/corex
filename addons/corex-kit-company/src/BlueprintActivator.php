<?php

/**
 * @package Corex\Kit
 */

declare(strict_types=1);

namespace Corex\Kit;

defined('ABSPATH') || exit;

/**
 * Applies a planned kit: enables its feature flags, activates its module plugins, and seeds
 * the kit's pages (composing its patterns; one becomes the front page). The side-effecting
 * half of the setup wizard, kept apart from the admin screen (one actor: site provisioning).
 * Page seeding is idempotent (the KitPagePlanner skips existing slugs) and tracked
 * (`_corex_kit_page` meta + the `corex_kit_seeded_pages` option) so a reset removes exactly
 * the kit pages (spec 031).
 */
final class BlueprintActivator
{
    public const SEEDED_OPTION = 'corex_kit_seeded_pages';

    private const MODULE_FILES = [
        'corex-ui'          => 'corex-ui/corex-ui.php',
        'corex-forms'       => 'corex-forms/corex-forms.php',
        'corex-email'       => 'corex-email/corex-email.php',
        'corex-blocks'      => 'corex-blocks/corex-blocks.php',
        'corex-kit-company' => 'corex-kit-company/corex-kit-company.php',
    ];

    public function __construct(private readonly KitPagePlanner $planner = new KitPagePlanner())
    {
    }

    /**
     * @param array{flags:list<string>,modules:list<string>,pages?:list<array{title:string,slug:string,content:string,front?:bool}>} $plan
     */
    public function apply(array $plan): void
    {
        $this->enableFlags($plan['flags']);
        $this->activateModules($plan['modules']);
        $this->seedPages($plan['pages'] ?? []);
    }

    /**
     * Create a kit's pages that don't already exist (idempotent), set the front page, and
     * record the created ids so a reset can remove exactly them.
     *
     * @param list<array{title:string,slug:string,content:string,front?:bool}> $pages
     */
    public function seedPages(array $pages): void
    {
        $toCreate = $this->planner->toCreate($pages, $this->existingSlugs($pages));

        if ($toCreate === []) {
            return;
        }

        $seeded = (array) get_option(self::SEEDED_OPTION, []);

        foreach ($toCreate as $page) {
            $id = wp_insert_post([
                'post_title'   => $page['title'],
                'post_name'    => $page['slug'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_content' => $page['content'],
            ]);

            if (! is_int($id) || $id <= 0) {
                continue;
            }

            update_post_meta($id, '_corex_kit_page', '1');
            $seeded[] = $id;

            if (($page['front'] ?? false) === true) {
                update_option('show_on_front', 'page');
                update_option('page_on_front', $id);
            }
        }

        update_option(self::SEEDED_OPTION, array_values(array_unique($seeded)));
    }

    /**
     * The slugs (from the declared set) that already exist as pages — fed to the planner.
     *
     * @param list<array{slug:string}> $pages
     *
     * @return list<string>
     */
    private function existingSlugs(array $pages): array
    {
        $existing = [];
        foreach ($pages as $page) {
            if (get_page_by_path($page['slug']) !== null) {
                $existing[] = $page['slug'];
            }
        }

        return $existing;
    }

    /**
     * @param list<string> $flags
     */
    private function enableFlags(array $flags): void
    {
        foreach ($flags as $flag) {
            update_option('corex_features_' . sanitize_key($flag), '1');
        }
    }

    /**
     * @param list<string> $modules
     */
    private function activateModules(array $modules): void
    {
        if (! function_exists('activate_plugin')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        foreach ($modules as $module) {
            $file = self::MODULE_FILES[$module] ?? null;

            if ($file !== null && ! is_plugin_active($file)) {
                activate_plugin($file); // returns WP_Error on failure; non-fatal here
            }
        }
    }

}
