<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email;

defined('ABSPATH') || exit;

use Corex\Container\ContainerInterface;
use Corex\Email\Driver\MailDriver;
use Corex\Email\Driver\WpMailDriver;
use Corex\Email\Log\EmailLogRepository;
use Corex\Email\Log\EmailLogStore;
use Corex\Email\Recipients\RecipientResolver;
use Corex\Email\Recipients\UserDirectory;
use Corex\Email\Recipients\WpUserDirectory;
use Corex\Email\Security\HeaderGuard;
use Corex\Email\Template\Layout;
use Corex\Email\Template\TemplateRegistry;
use Corex\Email\Template\TemplateRenderer;
use Corex\Email\Templates\ContactNotificationTemplate;
use Corex\Foundation\ServiceProvider;
use Corex\Mail\Mailer;
use Corex\Support\BootLogger;
use Corex\Support\Config\ConfigInterface;

/**
 * Boots the mail engine: binds the headless cores (renderer, header guard, recipient
 * resolver) and the boundary (driver, log repository, user directory + the
 * MailService), binds the corex-core Mailer seam to this engine, and on the boot pass
 * registers the email-log CPT and the default templates.
 */
final class MailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(TemplateRegistry::class);
        $this->container->singleton(HeaderGuard::class);

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

        $this->container->singleton(
            MailDriver::class,
            static fn (ContainerInterface $c): MailDriver => new WpMailDriver($c->make(ConfigInterface::class)),
        );

        // EmailLogRepository autowires its data-layer dependencies (field driver, hydrator, executor).
        $this->container->singleton(EmailLogStore::class, EmailLogRepository::class);

        $this->container->singleton(
            MailService::class,
            static fn (ContainerInterface $c): MailService => new MailService(
                $c->make(MailDriver::class),
                $c->make(EmailLogStore::class),
                $c->make(HeaderGuard::class),
                $c->make(BootLogger::class),
            ),
        );

        // The corex-core Mailer seam → this engine (detect-and-defer for Forms, etc.).
        $this->container->singleton(
            Mailer::class,
            static fn (ContainerInterface $c): Mailer => new RequestMailer(
                $c->make(TemplateRegistry::class),
                $c->make(TemplateRenderer::class),
                $c->make(RecipientResolver::class),
                $c->make(MailService::class),
            ),
        );
    }

    public function boot(): void
    {
        add_action('init', [$this, 'registerEmailLogPostType']);

        $this->container->make(TemplateRegistry::class)->register(
            $this->container->make(ContactNotificationTemplate::class),
        );
    }

    /**
     * The non-public audit store for email sends.
     */
    public function registerEmailLogPostType(): void
    {
        register_post_type('corex_email_log', [
            'label'           => __('Email Log', 'corex'),
            'public'          => false,
            'show_ui'         => false,
            'supports'        => ['title'],
            'capability_type' => 'post',
        ]);
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
