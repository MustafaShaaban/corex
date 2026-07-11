<?php

/**
 * @package Corex\Kit
 */

declare(strict_types=1);

namespace Corex\Kit;

use Corex\Admin\AdminPage;
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
        private readonly AdminPage $page,
    ) {
    }

    private string $hook = '';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'maybeApply']);
        add_action('admin_enqueue_scripts', [$this, 'maybeEnqueue']);
    }

    public function menu(): void
    {
        $this->hook = (string) add_submenu_page(
            'corex-settings',
            __('Corex Setup', 'corex'),
            __('Setup Wizard', 'corex'),
            'manage_options',
            'corex-setup',
            [$this, 'render'],
            50,
        );
    }

    /**
     * Load the shared CoreX admin shell on the setup screen so the wizard inherits the
     * full-width frame, card rhythm, brass buttons, step badges, and light/dark appearance
     * (the kit owns its screen's assets — it does not rely on another plugin's allow-list).
     */
    public function maybeEnqueue(string $hook): void
    {
        if ($hook !== $this->hook || $this->hook === '') {
            return;
        }

        wp_enqueue_style('corex-admin-shell');
        wp_enqueue_style(
            'corex-setup-wizard',
            plugins_url('assets/setup-wizard.css', dirname(__DIR__) . '/corex-kit-company.php'),
            ['corex-admin-shell'],
            '1.0.0',
        );
        wp_enqueue_script(
            'corex-setup-wizard',
            plugins_url('assets/setup-wizard.js', dirname(__DIR__) . '/corex-kit-company.php'),
            ['corex-runtime'],
            '1.0.0',
            true,
        );
        wp_localize_script('corex-setup-wizard', 'corexSetup', [
            'restUrl'  => esc_url_raw(rest_url('corex/v1/setup')),
            'nonce'    => wp_create_nonce('wp_rest'),
            'adminUrl' => esc_url_raw(admin_url()),
        ]);
    }

    public function render(): void
    {
        if (! $this->guard->authorized()) {
            echo $this->page->permissionDenied('setup');

            return;
        }

        $kits     = $this->wizard->kits();
        $applied  = $this->requestedKit('corex_applied', $kits);
        $review   = $this->requestedKit('corex_kit', $kits);
        $step     = $applied !== '' ? 3 : ($review !== '' ? 2 : 1);

        echo $this->page->open(
            'setup',
            __('CoreX Setup Wizard', 'corex'),
            __('Choose a starter kit, review its modules, then apply the plan.', 'corex'),
        );

        // The nine-step JS wizard mounts here; the server-rendered flow below is the no-JS fallback.
        echo '<div id="corex-setup-app" class="corex-setup"></div>';
        echo '<div class="corex-setup-fallback">';
        echo $this->stepper($step);

        if ($kits === []) {
            echo $this->page->state(
                'empty',
                __('No starter kits available', 'corex'),
                __('Install and activate a CoreX kit package to continue.', 'corex'),
            );
        } elseif ($step === 3) {
            echo $this->renderApplied($applied);
        } elseif ($step === 2) {
            echo $this->renderReview($review);
        } else {
            echo $this->renderChoose($kits);
        }

        echo '</div>';
        echo $this->page->close();
    }

    /**
     * The three-step progress indicator with the current step marked.
     */
    private function stepper(int $step): string
    {
        $labels = [1 => __('Choose', 'corex'), 2 => __('Review', 'corex'), 3 => __('Apply', 'corex')];
        $items  = '';

        foreach ($labels as $index => $label) {
            $items .= sprintf(
                '<li class="%1$s" aria-current="%2$s"><span>%3$d</span>%4$s</li>',
                $index === $step ? 'is-current' : ($index < $step ? 'is-done' : ''),
                $index === $step ? 'step' : 'false',
                $index,
                esc_html($label),
            );
        }

        return '<ol class="corex-wizard__steps" aria-label="' . esc_attr__('Setup progress', 'corex') . '">'
            . $items . '</ol>';
    }

    /**
     * Step 1 — the choosable kits, each linking to its review step.
     *
     * @param list<array{name:string,required:list<string>,recommended:list<string>,flags:list<string>}> $kits
     */
    private function renderChoose(array $kits): string
    {
        $html = '<div class="corex-wizard__kits">';

        foreach ($kits as $kit) {
            $modules = implode(', ', array_merge($kit['required'], $kit['recommended']));
            $html   .= '<div class="corex-wizard__kit corex-surface">'
                . '<h2>' . esc_html(ucfirst($kit['name'])) . '</h2>'
                . '<p><strong>' . esc_html__('Modules:', 'corex') . '</strong> ' . esc_html($modules) . '</p>'
                . '<a class="button button-primary" href="' . esc_url($this->stepUrl(['corex_kit' => $kit['name']]))
                . '">' . esc_html__('Review kit', 'corex') . '</a></div>';
        }

        return $html . '</div>';
    }

    /**
     * Step 2 — review the chosen kit's plan, then apply (cap + nonce) or choose another.
     */
    private function renderReview(string $name): string
    {
        $plan    = $this->wizard->plan($name);
        $modules = implode(', ', $plan['modules']);
        $pages   = count($plan['pages']);

        $html = '<div class="corex-wizard__review corex-surface">'
            . '<h2>' . esc_html(ucfirst($name)) . '</h2>'
            . '<p class="corex-wizard__review-line"><strong>' . esc_html__('Modules to activate:', 'corex')
            . '</strong> ' . esc_html($modules !== '' ? $modules : __('none', 'corex')) . '</p>';

        if ($plan['flags'] !== []) {
            $html .= '<p class="corex-wizard__review-line"><strong>' . esc_html__('Feature flags:', 'corex')
                . '</strong> ' . esc_html(implode(', ', $plan['flags'])) . '</p>';
        }

        $html .= '<p class="corex-wizard__review-line"><strong>' . esc_html__('Starter pages:', 'corex')
            . '</strong> ' . esc_html((string) $pages) . '</p>';

        $html .= '<div class="corex-wizard__actions"><form method="post">'
            . wp_nonce_field('corex_setup', 'corex_setup_nonce', true, false)
            . '<input type="hidden" name="corex_kit" value="' . esc_attr($name) . '" />'
            . '<button type="submit" class="button button-primary">' . esc_html__('Apply this kit', 'corex')
            . '</button></form>'
            . '<a class="button" href="' . esc_url($this->stepUrl([])) . '">'
            . esc_html__('Choose another', 'corex') . '</a></div></div>';

        return $html;
    }

    /**
     * Step 3 — the truthful applied summary after the plan ran.
     */
    private function renderApplied(string $name): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only summary after a PRG redirect from the nonce-checked apply.
        $created = isset($_GET['created']) ? absint(wp_unslash($_GET['created'])) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only summary.
        $populated = isset($_GET['populated']) ? absint(wp_unslash($_GET['populated'])) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only summary.
        $skipped = isset($_GET['skipped']) ? absint(wp_unslash($_GET['skipped'])) : 0;

        $summary = sprintf(
            /* translators: 1: kit name, 2: pages created, 3: pages populated, 4: pages left unchanged */
            __('Applied the "%1$s" kit — %2$d page(s) created, %3$d populated, %4$d left unchanged.', 'corex'),
            ucfirst($name),
            $created,
            $populated,
            $skipped,
        );

        return $this->page->state('success', __('Kit applied', 'corex'), $summary)
            . '<div class="corex-wizard__actions"><a class="button button-primary" href="'
            . esc_url($this->stepUrl([])) . '">' . esc_html__('Back to wizard', 'corex') . '</a></div>';
    }

    /**
     * A setup-screen URL with extra query args (used for the Choose/Review navigation).
     *
     * @param array<string,string> $args
     */
    private function stepUrl(array $args): string
    {
        return add_query_arg(
            array_merge(['page' => 'corex-setup'], $args),
            admin_url('admin.php'),
        );
    }

    /**
     * A sanitized kit name from a GET param, validated against the registered kits.
     *
     * @param list<array{name:string,required:list<string>,recommended:list<string>,flags:list<string>}> $kits
     */
    private function requestedKit(string $param, array $kits): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only wizard navigation, not a state change.
        $name = isset($_GET[$param]) ? sanitize_key(wp_unslash($_GET[$param])) : '';

        foreach ($kits as $kit) {
            if ($kit['name'] === $name) {
                return $name;
            }
        }

        return '';
    }

    public function maybeApply(): void
    {
        if (! isset($_POST['corex_kit']) || ! $this->guard->verifiedPost('corex_setup_nonce', 'corex_setup')) {
            return;
        }

        $name    = sanitize_key(wp_unslash($_POST['corex_kit']));
        $outcome = $this->activator->apply($this->wizard->plan($name));

        // Post/Redirect/Get to the applied step so a refresh never re-applies the kit.
        wp_safe_redirect($this->stepUrl([
            'corex_applied' => $name,
            'created'       => (string) count($outcome->created()),
            'populated'     => (string) count($outcome->populated()),
            'skipped'       => (string) count($outcome->skipped()),
        ]));
        exit;
    }
}
