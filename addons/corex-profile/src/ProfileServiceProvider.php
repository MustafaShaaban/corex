<?php

/**
 * @package Corex\Profile
 */

declare(strict_types=1);

namespace Corex\Profile;

defined('ABSPATH') || exit;

use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;
use Corex\Captcha\Captcha;
use Corex\Container\ContainerInterface;
use Corex\Foundation\ServiceProvider;
use Corex\Profile\Account\AccountResult;
use Corex\Profile\Block\AccountRenderer;
use Corex\Profile\Account\AccountService;
use Corex\Profile\Account\AuthGateway;
use Corex\Profile\Account\RegistrationRequest;
use Corex\Profile\Account\WordPressAuthGateway;
use Corex\Profile\Notification\NotificationService;
use Corex\Profile\Session\SessionService;
use DateTimeImmutable;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Wires the front-office account add-on: the WordPress auth gateway, the account /
 * session / notification services, and the public + authenticated REST routes. It
 * contains no presentation — the theme renders the forms (Principle I) and every
 * mutation runs through a service (Principle III). Registration/login stay off
 * wp-admin (front-office), and recovery keeps working even when login is hardened.
 */
final class ProfileServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(AuthGateway::class, static fn (): AuthGateway => new WordPressAuthGateway());

        $this->container->singleton(
            AccountService::class,
            static fn (ContainerInterface $c): AccountService => new AccountService($c->make(AuthGateway::class)),
        );

        $this->container->singleton(SessionService::class, static fn (): SessionService => new SessionService());

        $this->container->singleton(
            NotificationService::class,
            static fn (ContainerInterface $c): NotificationService => new NotificationService($c->make(ActivityService::class)),
        );
    }

    public function boot(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        add_action('init', [$this, 'registerAssets']);
        add_action('init', [$this, 'registerBlock']);
    }

    public function registerAssets(): void
    {
        if (! function_exists('wp_register_script')) {
            return;
        }

        $main = dirname(__DIR__) . '/corex-profile.php';

        wp_register_script(
            'corex-account',
            plugins_url('assets/account.js', $main),
            [],
            defined('COREX_PROFILE_VERSION') ? COREX_PROFILE_VERSION : false,
            true,
        );
        wp_localize_script('corex-account', 'corexAccount', [
            'restUrl' => esc_url_raw(rest_url('corex/v1/')),
            'nonce'   => wp_create_nonce('wp_rest'),
            'i18n'    => [
                'session'    => __('Session', 'corex'),
                'thisDevice' => __('this device', 'corex'),
            ],
        ]);
        wp_register_style(
            'corex-account',
            plugins_url('assets/account.css', $main),
            [],
            defined('COREX_PROFILE_VERSION') ? COREX_PROFILE_VERSION : false,
        );
    }

    public function registerBlock(): void
    {
        if (! function_exists('register_block_type')) {
            return;
        }

        register_block_type('corex/account', [
            'api_version'     => 3,
            'render_callback' => [$this, 'renderBlock'],
        ]);
    }

    public function renderBlock(): string
    {
        wp_enqueue_script('corex-account');
        wp_enqueue_style('corex-account');

        $userId = get_current_user_id();
        $user   = $userId > 0 ? get_userdata($userId) : null;

        $ctx = [
            'loggedIn'         => $userId > 0,
            'registrationOpen' => (bool) get_option('users_can_register', false),
            'nonce'            => wp_create_nonce('wp_rest'),
            'logoutUrl'        => wp_logout_url(home_url('/')),
            'displayName'      => $user ? (string) $user->display_name : '',
            'firstName'        => $user ? (string) $user->first_name : '',
            'lastName'         => $user ? (string) $user->last_name : '',
            'email'            => $user ? (string) $user->user_email : '',
        ];

        return (new AccountRenderer())->render($ctx);
    }

    public function registerRoutes(): void
    {
        $public = ['methods' => 'POST', 'permission_callback' => '__return_true'];
        $authed = ['permission_callback' => static fn (): bool => is_user_logged_in()];

        register_rest_route('corex/v1', '/account/register', $public + ['callback' => [$this, 'register_account']]);
        register_rest_route('corex/v1', '/account/login', $public + ['callback' => [$this, 'login']]);
        register_rest_route('corex/v1', '/account/reset-request', $public + ['callback' => [$this, 'requestReset']]);
        register_rest_route('corex/v1', '/account/reset', $public + ['callback' => [$this, 'resetPassword']]);

        register_rest_route('corex/v1', '/account/profile', $authed + ['methods' => 'POST', 'callback' => [$this, 'updateProfile']]);
        register_rest_route('corex/v1', '/account/sessions', $authed + ['methods' => 'GET', 'callback' => [$this, 'sessions']]);
        register_rest_route('corex/v1', '/account/sessions/revoke-others', $authed + ['methods' => 'POST', 'callback' => [$this, 'revokeOthers']]);
        register_rest_route('corex/v1', '/account/sessions/revoke-all', $authed + ['methods' => 'POST', 'callback' => [$this, 'revokeAll']]);
        register_rest_route('corex/v1', '/account/notifications', $authed + ['methods' => 'GET', 'callback' => [$this, 'notifications']]);
    }

    public function register_account(WP_REST_Request $request): WP_REST_Response
    {
        if (trim((string) $request['corex_hp']) !== '') {
            return $this->respond(AccountResult::fail('spam', __('Request rejected.', 'corex')));
        }

        if ($this->container->has(Captcha::class)
            && ! $this->container->make(Captcha::class)->verify((string) $request['captcha_token'])) {
            return $this->respond(AccountResult::fail('captcha', __('Please complete the captcha.', 'corex')));
        }

        $result = $this->container->make(AccountService::class)->register(new RegistrationRequest(
            sanitize_email((string) $request['email']),
            (string) $request['password'],
            (string) $request['password_confirm'],
            sanitize_user((string) $request['username']),
            (bool) $request['consent'],
        ));

        return $this->respond($result);
    }

    public function login(WP_REST_Request $request): WP_REST_Response
    {
        $result = $this->container->make(AccountService::class)->login(
            sanitize_text_field((string) $request['login']),
            (string) $request['password'],
            (bool) $request['remember'],
        );

        return $this->respond($result);
    }

    public function requestReset(WP_REST_Request $request): WP_REST_Response
    {
        $result = $this->container->make(AccountService::class)
            ->requestPasswordReset(sanitize_text_field((string) $request['login']));

        return $this->respond($result);
    }

    public function resetPassword(WP_REST_Request $request): WP_REST_Response
    {
        $result = $this->container->make(AccountService::class)->resetPassword(
            sanitize_text_field((string) $request['key']),
            sanitize_text_field((string) $request['login']),
            (string) $request['password'],
            (string) $request['password_confirm'],
        );

        return $this->respond($result);
    }

    public function updateProfile(WP_REST_Request $request): WP_REST_Response
    {
        $userId = get_current_user_id();

        $fields = [];
        foreach (['display_name', 'first_name', 'last_name', 'email'] as $key) {
            if ($request[$key] !== null) {
                $fields[$key] = (string) $request[$key];
            }
        }

        $result = $this->container->make(AccountService::class)->updateProfile($userId, $fields);

        if ($result->success) {
            $this->recordAccountEvent($userId, 'profile.updated', __('Profile updated', 'corex'));
        }

        return $this->respond($result);
    }

    public function sessions(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response([
            'success'  => true,
            'sessions' => $this->container->make(SessionService::class)->active(get_current_user_id()),
        ], 200);
    }

    public function revokeOthers(WP_REST_Request $request): WP_REST_Response
    {
        $userId = get_current_user_id();
        $result = $this->container->make(SessionService::class)->signOutOthers($userId);

        if ($result->success) {
            $this->recordAccountEvent($userId, 'sessions.revoked_others', __('Signed out other sessions', 'corex'));
        }

        return $this->respond($result);
    }

    public function revokeAll(WP_REST_Request $request): WP_REST_Response
    {
        return $this->respond($this->container->make(SessionService::class)->signOutAll(get_current_user_id()));
    }

    public function notifications(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response([
            'success'       => true,
            'notifications' => $this->container->make(NotificationService::class)->forUser(get_current_user_id()),
        ], 200);
    }

    private function respond(AccountResult $result): WP_REST_Response
    {
        return new WP_REST_Response($result->toArray(), $result->success ? 200 : 422);
    }

    /**
     * Record a permission-safe account event on the shared activity stream so the
     * user's own notifications reflect real actions (never a fabricated feed).
     */
    private function recordAccountEvent(int $userId, string $kind, string $label): void
    {
        if (! $this->container->has(ActivityService::class)) {
            return;
        }

        $user = get_userdata($userId);
        $name = $user ? (string) $user->display_name : (string) $userId;

        $this->container->make(ActivityService::class)->record(
            actorId: $userId,
            actorKind: ActivityEvent::ACTOR_USER,
            actorLabel: $name,
            area: ActivityEvent::AREA_SECURITY,
            kind: $kind,
            targetType: 'user',
            targetId: (string) $userId,
            targetLabel: $name,
            outcome: ActivityEvent::OUTCOME_SUCCESS,
            summary: ['key' => $kind, 'args' => []],
            context: [],
            sensitivity: ActivityEvent::SENSITIVITY_PERSONAL,
            retentionUntil: (new DateTimeImmutable('now'))->modify('+1 year'),
        );
    }
}
