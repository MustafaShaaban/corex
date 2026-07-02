<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Email;

use Corex\Admin\AdminPage;
use Corex\Config\Overview\EnvironmentMode;
use Corex\Container\ContainerInterface;
use Corex\Email\Template\TemplateRegistry;
use Corex\Security\Admin\AdminGuard;

defined('ABSPATH') || exit;

/**
 * The Email Studio admin screen (spec 063, Phase 2): a truthful overview of the transactional-email
 * engine. corex-email is an OPTIONAL add-on, so the screen gates on it (Principle IX) — when it is not
 * active it shows an honest "not active" state with a link to enable it, never a fake studio. When it
 * is active it lists the REAL registered templates (by name, via {@see TemplateRegistry::names()}) and
 * an honest environment-derived delivery-mode advisory. The full template editor / layout builder /
 * logs are future capabilities, labelled as such. No fabricated templates, sends, or delivery metrics.
 */
final class EmailStudioScreen
{
    private const EMAIL_PLUGIN = 'corex-email/corex-email.php';

    private string $hook = '';

    public function __construct(
        private readonly AdminGuard $guard,
        private readonly AdminPage $page,
        private readonly EmailStudio $studio,
        private readonly EnvironmentMode $mode,
        private readonly ContainerInterface $container,
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'maybeEnqueue']);
    }

    public function menu(): void
    {
        $this->hook = (string) add_submenu_page(
            'corex-settings',
            __('CoreX Email Studio', 'corex'),
            __('Email Studio', 'corex'),
            'manage_options',
            'corex-email-studio',
            [$this, 'render'],
            27,
        );
    }

    public function maybeEnqueue(string $hook): void
    {
        if ($hook !== $this->hook || $this->hook === '') {
            return;
        }

        wp_enqueue_style(
            'corex-email-studio',
            plugins_url('assets/email-studio.css', COREX_CONFIG_FILE),
            ['corex-admin-shell'],
            '1.0.0',
        );
    }

    public function render(): void
    {
        if (! $this->guard->authorized()) {
            echo $this->page->permissionDenied('email');

            return;
        }

        $active   = $this->emailActive();
        $overview = $this->studio->overview($active, $this->templateNames($active), $this->environment());

        echo $this->page->open(
            'email',
            __('CoreX Email Studio', 'corex'),
            __('Transactional email templates and delivery state for this site.', 'corex'),
        );

        if (! $overview['active']) {
            echo $this->page->state(
                'warning',
                __('CoreX Email is not active', 'corex'),
                __('Activate the CoreX Email add-on to manage transactional templates and delivery.', 'corex'),
            );
            echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=corex-addons')) . '">'
                . esc_html__('Open Add-ons', 'corex') . '</a></p>';
            echo $this->page->close();

            return;
        }

        echo $this->deliveryCard($overview['delivery']);
        echo $this->templatesCard($overview);
        echo $this->futureNote();
        echo $this->page->close();
    }

    /**
     * @param array{label:string,tone:string,detail:string} $delivery
     */
    private function deliveryCard(array $delivery): string
    {
        return '<section class="corex-surface corex-email-studio__delivery is-' . esc_attr($delivery['tone']) . '">'
            . '<p class="corex-admin__eyebrow">' . esc_html__('DELIVERY MODE', 'corex') . '</p>'
            . '<h2>' . esc_html($delivery['label']) . '</h2>'
            . '<p class="corex-email-studio__detail">' . esc_html($delivery['detail']) . '</p>'
            . '<p><a href="' . esc_url(admin_url('admin.php?page=corex-settings-config&corex_tab=mail')) . '">'
            . esc_html__('Configure mail settings', 'corex') . '</a></p></section>';
    }

    /**
     * @param array{templates:list<string>,templateCount:int,hasTemplates:bool} $overview
     */
    private function templatesCard(array $overview): string
    {
        $out = '<section class="corex-surface corex-email-studio__templates">'
            . '<header class="corex-email-studio__templates-head"><h2>' . esc_html__('Templates', 'corex') . '</h2>'
            . '<span class="corex-email-studio__count">' . sprintf(
                /* translators: %d: number of registered email templates */
                esc_html(_n('%d registered', '%d registered', $overview['templateCount'], 'corex')),
                (int) $overview['templateCount'],
            ) . '</span></header>';

        if (! $overview['hasTemplates']) {
            return $out . '<p class="corex-email-studio__empty">'
                . esc_html__('No templates are registered yet.', 'corex') . '</p></section>';
        }

        $out .= '<ul class="corex-email-studio__list">';
        foreach ($overview['templates'] as $name) {
            $out .= '<li><code>' . esc_html($name) . '</code></li>';
        }

        return $out . '</ul></section>';
    }

    private function futureNote(): string
    {
        return '<div class="corex-email-studio__note corex-surface">'
            . '<p class="corex-email-studio__note-title">' . esc_html__('Editing templates', 'corex') . '</p>'
            . '<p class="corex-email-studio__note-text">'
            . esc_html__(
                'Templates are registered in code today. A visual template editor, layout builder, and delivery logs are planned future capabilities — this screen is a truthful inventory of what is registered and how email is delivered now.',
                'corex',
            )
            . '</p></div>';
    }

    private function emailActive(): bool
    {
        return in_array(
            self::EMAIL_PLUGIN,
            array_map('strval', (array) get_option('active_plugins', [])),
            true,
        );
    }

    /**
     * @return list<string>
     */
    private function templateNames(bool $active): array
    {
        if (! $active) {
            return [];
        }

        try {
            /** @var TemplateRegistry $registry */
            $registry = $this->container->make(TemplateRegistry::class);

            return $registry->names();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array{mode:string,label:string}
     */
    private function environment(): array
    {
        $env = $this->mode->resolve(
            function_exists('wp_get_environment_type') ? (string) wp_get_environment_type() : 'production',
        );

        return ['mode' => $env['mode'], 'label' => $env['label']];
    }
}
