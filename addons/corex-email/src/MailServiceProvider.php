<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email;

defined('ABSPATH') || exit;

use Corex\Container\ContainerInterface;
use Corex\Email\Recipients\RecipientResolver;
use Corex\Email\Recipients\UserDirectory;
use Corex\Email\Recipients\WpUserDirectory;
use Corex\Email\Template\Layout;
use Corex\Email\Template\TemplateRegistry;
use Corex\Email\Template\TemplateRenderer;
use Corex\Foundation\ServiceProvider;

/**
 * Boots the mail engine: binds the headless cores (renderer, header guard, recipient
 * resolver, message builder) and the boundary (driver, log repository, user
 * directory), binds the corex-core Mailer seam to this engine, and on the boot pass
 * registers the email-log CPT and the default templates.
 */
final class MailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(TemplateRegistry::class);

        $this->container->singleton(
            Layout::class,
            static fn (ContainerInterface $c): Layout => new Layout(self::brand()),
        );

        $this->container->singleton(
            TemplateRenderer::class,
            static fn (ContainerInterface $c): TemplateRenderer => new TemplateRenderer($c->make(Layout::class)),
        );

        $this->container->singleton(UserDirectory::class, WpUserDirectory::class);

        $this->container->singleton(
            RecipientResolver::class,
            static fn (ContainerInterface $c): RecipientResolver => new RecipientResolver($c->make(UserDirectory::class)),
        );
    }

    public function boot(): void
    {
        // Boundary wiring (CPT, templates, driver) is added per user story.
    }

    /**
     * The brand values for the email layout, read at runtime from the resolved
     * theme.json (which already includes any brand.json override via spec 006) — so
     * a rebrand flows into mail with no code change (FR-005).
     *
     * @return array{name:string,color:string,dir:string}
     */
    private static function brand(): array
    {
        $palette = (array) wp_get_global_settings(['color', 'palette']);
        $entries = $palette['theme'] ?? $palette['default'] ?? [];
        $color   = '';

        foreach ((array) $entries as $entry) {
            if (is_array($entry) && ($entry['slug'] ?? '') === 'primary') {
                $color = (string) ($entry['color'] ?? '');
                break;
            }
        }

        return [
            'name'  => (string) get_bloginfo('name'),
            'color' => $color,
            'dir'   => is_rtl() ? 'rtl' : 'ltr',
        ];
    }
}
