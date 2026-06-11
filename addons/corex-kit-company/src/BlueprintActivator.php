<?php

/**
 * @package Corex\Kit
 */

declare(strict_types=1);

namespace Corex\Kit;

defined('ABSPATH') || exit;

/**
 * Applies a planned kit: enables its feature flags, activates its module plugins, and
 * seeds a one-time demo front page. This is the side-effecting half of the setup wizard,
 * kept apart from the admin screen so the screen only renders + gates and this only acts
 * (one actor: site provisioning). All actions are idempotent.
 */
final class BlueprintActivator
{
    private const MODULE_FILES = [
        'corex-ui'          => 'corex-ui/corex-ui.php',
        'corex-forms'       => 'corex-forms/corex-forms.php',
        'corex-email'       => 'corex-email/corex-email.php',
        'corex-blocks'      => 'corex-blocks/corex-blocks.php',
        'corex-kit-company' => 'corex-kit-company/corex-kit-company.php',
    ];

    /**
     * @param array{flags:list<string>,modules:list<string>} $plan
     */
    public function apply(array $plan): void
    {
        $this->enableFlags($plan['flags']);
        $this->activateModules($plan['modules']);
        $this->seedDemoHome();
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

    /**
     * Create a demo "Home" page and set it as the static front page — once (idempotent).
     */
    private function seedDemoHome(): void
    {
        if (get_option('corex_setup_demo_seeded') === '1') {
            return;
        }

        $pageId = wp_insert_post([
            'post_title'   => __('Home', 'corex'),
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '<!-- wp:pattern {"slug":"corex/hero"} /-->',
        ]);

        if (is_int($pageId) && $pageId > 0) {
            update_option('show_on_front', 'page');
            update_option('page_on_front', $pageId);
            update_option('corex_setup_demo_seeded', '1');
        }
    }
}
