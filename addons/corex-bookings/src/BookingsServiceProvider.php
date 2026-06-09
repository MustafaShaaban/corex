<?php

/**
 * @package Corex\Bookings
 */

declare(strict_types=1);

namespace Corex\Bookings;

defined('ABSPATH') || exit;

use Corex\Bookings\Templates\CallRequestConfirmTemplate;
use Corex\Bookings\Templates\CallRequestLeaderTemplate;
use Corex\Captcha\Captcha;
use Corex\Container\ContainerInterface;
use Corex\Database\Schema\Migrator;
use Corex\Database\Schema\Table;
use Corex\Email\Template\TemplateRegistry;
use Corex\Foundation\ServiceProvider;
use Corex\Mail\Mailer;
use Corex\Support\Config\ConfigInterface;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Wires the call-request flow: the bindings, the requests table, the request REST
 * route, and the email templates. Leaders come from `bookings.leaders` config.
 */
final class BookingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(CallRequestRepository::class);
        $this->container->singleton(
            CallRequestStore::class,
            static fn (ContainerInterface $c): CallRequestStore => new WpCallRequestStore($c->make(CallRequestRepository::class)),
        );

        $this->container->singleton(
            LeaderDirectory::class,
            static fn (ContainerInterface $c): LeaderDirectory => new LeaderDirectory(
                (array) $c->make(ConfigInterface::class)->get('bookings.leaders', []),
            ),
        );

        $this->container->singleton(
            CallRequestService::class,
            static fn (ContainerInterface $c): CallRequestService => new CallRequestService(
                $c->make(CallRequestStore::class),
                $c->make(LeaderDirectory::class),
                $c->make(Mailer::class),
            ),
        );
    }

    public function boot(): void
    {
        add_action('init', [$this, 'install']);
        add_action('rest_api_init', [$this, 'registerRoute']);
    }

    public function install(): void
    {
        $this->container->make(Migrator::class)->create(
            (new Table('call_requests'))
                ->id()->string('leader_id', 60)->string('name')->string('email')->string('phone', 60)
                ->string('preferred_time', 100)->text('message')->string('status', 20)->timestamps()
        );

        if ($this->container->has(TemplateRegistry::class)) {
            $registry = $this->container->make(TemplateRegistry::class);
            $registry->register(new CallRequestLeaderTemplate());
            $registry->register(new CallRequestConfirmTemplate());
        }
    }

    public function registerRoute(): void
    {
        register_rest_route('corex/v1', '/bookings/request', [
            'methods'             => 'POST',
            'permission_callback' => '__return_true', // public; honeypot + captcha gate it
            'callback'            => [$this, 'request'],
        ]);
    }

    public function request(WP_REST_Request $request): WP_REST_Response
    {
        if (trim((string) $request['corex_hp']) !== '') {
            return new WP_REST_Response(['ok' => false], 422);
        }

        if ($this->container->has(Captcha::class)
            && ! $this->container->make(Captcha::class)->verify((string) $request['captcha_token'])) {
            return new WP_REST_Response(['ok' => false, 'error' => 'captcha'], 422);
        }

        $data = [
            'name'           => sanitize_text_field((string) $request['name']),
            'email'          => sanitize_email((string) $request['email']),
            'phone'          => sanitize_text_field((string) $request['phone']),
            'preferred_time' => sanitize_text_field((string) $request['preferred_time']),
            'message'        => sanitize_textarea_field((string) $request['message']),
        ];

        $result = $this->container->make(CallRequestService::class)
            ->request(sanitize_key((string) $request['leader']), $data);

        return new WP_REST_Response(['ok' => $result->stored, 'error' => $result->reason], $result->stored ? 200 : 422);
    }
}
