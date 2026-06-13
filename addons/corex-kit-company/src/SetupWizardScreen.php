<?php

/**
 * @package Corex\Kit
 */

declare(strict_types=1);

namespace Corex\Kit;

use Corex\Security\Admin\AdminGuard;

defined('ABSPATH') || exit;

/**
 * The server-rendered setup wizard: a submenu under the Corex menu that lists the
 * registered kits and, on apply (cap + nonce via the shared AdminGuard), hands the kit's
 * plan to the BlueprintActivator. The planning is the pure, tested SetupWizard; the side
 * effects are the BlueprintActivator; this screen only renders + gates. (A richer
 * React/stepped wizard is the deferred upgrade, like the settings screen.)
 */
final class SetupWizardScreen
{
    public function __construct(
        private readonly SetupWizard $wizard,
        private readonly BlueprintActivator $activator,
        private readonly AdminGuard $guard,
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'maybeApply']);
    }

    public function menu(): void
    {
        add_submenu_page(
            'corex-settings',
            __('Corex Setup', 'corex'),
            __('Setup Wizard', 'corex'),
            'manage_options',
            'corex-setup',
            [$this, 'render'],
        );
    }

    public function render(): void
    {
        if (! $this->guard->authorized()) {
            return;
        }

        echo '<div class="wrap"><h1>' . esc_html__('Corex Setup Wizard', 'corex') . '</h1>';
        echo '<p>' . esc_html__('Choose a starter kit to enable its modules and feature flags and seed a demo home page.', 'corex') . '</p>';

        foreach ($this->wizard->kits() as $kit) {
            $modules = implode(', ', array_map('sanitize_text_field', array_merge($kit['required'], $kit['recommended'])));

            echo '<form method="post" class="card">';
            echo wp_nonce_field('corex_setup', 'corex_setup_nonce', true, false);
            echo '<input type="hidden" name="corex_kit" value="' . esc_attr($kit['name']) . '" />';
            echo '<h2>' . esc_html(ucfirst($kit['name'])) . '</h2>';
            echo '<p><strong>' . esc_html__('Modules:', 'corex') . '</strong> ' . esc_html($modules) . '</p>';

            if ($kit['flags'] !== []) {
                echo '<p><strong>' . esc_html__('Flags:', 'corex') . '</strong> ' . esc_html(implode(', ', $kit['flags'])) . '</p>';
            }

            echo '<button type="submit" class="button button-primary">'
                . esc_html__('Apply this kit', 'corex') . '</button>';
            echo '</form>';
        }

        echo '</div>';
    }

    public function maybeApply(): void
    {
        if (! isset($_POST['corex_kit']) || ! $this->guard->verifiedPost('corex_setup_nonce', 'corex_setup')) {
            return;
        }

        $name    = sanitize_key(wp_unslash($_POST['corex_kit']));
        $outcome = $this->activator->apply($this->wizard->plan($name));

        $created   = count($outcome->created());
        $populated = count($outcome->populated());
        $skipped   = count($outcome->skipped());

        add_action('admin_notices', static function () use ($name, $created, $populated, $skipped): void {
            $summary = sprintf(
                /* translators: 1: kit name, 2: pages created, 3: pages populated, 4: pages left unchanged */
                __('Applied the "%1$s" kit — %2$d page(s) created, %3$d populated, %4$d left unchanged.', 'corex'),
                $name,
                $created,
                $populated,
                $skipped,
            );

            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($summary) . '</p></div>';
        });
    }
}
