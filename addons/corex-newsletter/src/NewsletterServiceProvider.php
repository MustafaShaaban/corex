<?php

/**
 * @package Corex\Newsletter
 */

declare(strict_types=1);

namespace Corex\Newsletter;

defined('ABSPATH') || exit;

use Corex\Captcha\Captcha;
use Corex\Container\ContainerInterface;
use Corex\Database\Schema\Migrator;
use Corex\Database\Schema\Table;
use Corex\Email\Template\TemplateRegistry;
use Corex\Foundation\ServiceProvider;
use Corex\Mail\Mailer;
use Corex\Newsletter\Subscriber\SubscriberRepository;
use Corex\Newsletter\Subscriber\SubscriberStore;
use Corex\Newsletter\Subscriber\WpSubscriberStore;
use Corex\Newsletter\Subscription\PublishNotifier;
use Corex\Newsletter\Subscription\SubscriptionService;
use Corex\Newsletter\Templates\NewsletterConfirmTemplate;
use Corex\Newsletter\Templates\NewsletterNotifyTemplate;
use Corex\Support\Config\ConfigInterface;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Wires the newsletter: bindings, the subscribers table, the `newsletter_topic`
 * taxonomy, the confirm/unsubscribe link handler, the subscribe REST route, the
 * on-publish notifier, and the email templates (when Corex Mail is active).
 */
final class NewsletterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(
            TokenSigner::class,
            static fn (ContainerInterface $c): TokenSigner => new TokenSigner(
                (string) ($c->make(ConfigInterface::class)->get('newsletter.secret') ?: wp_salt('auth')),
            ),
        );

        $this->container->singleton(SubscriberRepository::class);
        $this->container->singleton(
            SubscriberStore::class,
            static fn (ContainerInterface $c): SubscriberStore => new WpSubscriberStore($c->make(SubscriberRepository::class)),
        );

        $this->container->singleton(
            SubscriptionService::class,
            static fn (ContainerInterface $c): SubscriptionService => new SubscriptionService(
                $c->make(SubscriberStore::class),
                $c->make(TokenSigner::class),
                $c->make(Mailer::class),
            ),
        );

        $this->container->singleton(
            PublishNotifier::class,
            static fn (ContainerInterface $c): PublishNotifier => new PublishNotifier(
                $c->make(SubscriberStore::class),
                $c->make(Mailer::class),
                $c->make(SubscriptionService::class),
            ),
        );
    }

    public function boot(): void
    {
        add_action('init', [$this, 'install']);
        add_action('init', [$this, 'handleLinks']);
        add_action('rest_api_init', [$this, 'registerRoute']);

        add_action('transition_post_status', [$this, 'onPublish'], 10, 3);
    }

    /**
     * Create the subscribers table, register the topic taxonomy, and register the
     * email templates with Corex Mail.
     */
    public function install(): void
    {
        $this->container->make(Migrator::class)->create(
            (new Table('subscribers'))
                ->id()->string('email')->string('status', 20)->text('topics')->boolean('consent')->timestamps()
        );

        register_taxonomy('newsletter_topic', 'post', [
            'label'        => __('Newsletter Topics', 'corex'),
            'public'       => true,
            'hierarchical' => false,
            'show_in_rest' => true,
        ]);

        if ($this->container->has(TemplateRegistry::class)) {
            $registry = $this->container->make(TemplateRegistry::class);
            $registry->register(new NewsletterConfirmTemplate());
            $registry->register(new NewsletterNotifyTemplate());
        }
    }

    /**
     * Handle the signed confirm/unsubscribe links from the emails. The token is the
     * authenticator (unforgeable), so these GET links need no nonce — the standard
     * email-link pattern.
     */
    public function handleLinks(): void
    {
        $action = isset($_GET['corex_newsletter']) ? sanitize_key(wp_unslash($_GET['corex_newsletter'])) : '';

        if (($action !== 'confirm' && $action !== 'unsubscribe') || ! isset($_GET['token'])) {
            return;
        }

        $token   = sanitize_text_field(wp_unslash($_GET['token']));
        $service = $this->container->make(SubscriptionService::class);

        $action === 'confirm' ? $service->confirm($token) : $service->unsubscribe($token);

        wp_safe_redirect(add_query_arg('corex_newsletter', $action . '-done', home_url('/')));
        exit;
    }

    public function registerRoute(): void
    {
        register_rest_route('corex/v1', '/newsletter/subscribe', [
            'methods'             => 'POST',
            'permission_callback' => '__return_true', // public; honeypot + captcha + throttle gate it
            'callback'            => [$this, 'subscribe'],
        ]);
    }

    public function subscribe(WP_REST_Request $request): WP_REST_Response
    {
        if (trim((string) $request['corex_hp']) !== '') {
            return new WP_REST_Response(['ok' => false], 422); // honeypot
        }

        if ($this->container->has(Captcha::class)
            && ! $this->container->make(Captcha::class)->verify((string) $request['captcha_token'])) {
            return new WP_REST_Response(['ok' => false, 'error' => 'captcha'], 422);
        }

        $email   = sanitize_email((string) $request['email']);
        $topics  = array_map('sanitize_key', (array) ($request['topics'] ?? []));
        $consent = (bool) $request['consent'];

        $ok = $this->container->make(SubscriptionService::class)->subscribe($email, $topics, $consent);

        return new WP_REST_Response(['ok' => $ok], $ok ? 200 : 422);
    }

    public function onPublish(string $new, string $old, \WP_Post $post): void
    {
        if ($new !== 'publish' || $old === 'publish' || $post->post_type !== 'post') {
            return;
        }

        $topics = wp_get_post_terms($post->ID, 'newsletter_topic', ['fields' => 'slugs']);
        if (is_wp_error($topics) || $topics === []) {
            return;
        }

        $this->container->make(PublishNotifier::class)->notify($topics, (string) get_the_title($post), (string) get_permalink($post));
    }
}
