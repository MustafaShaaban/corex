<?php

/**
 * Integration test: the add-ons' public routes with a captcha provider on, through WordPress's REST server
 * on real ./wp (spec 115, US-AUT-02 and US-VIS-11, FR-032). Registration, the newsletter sign-up, a call
 * request and a job application each refuse a request without the check, or with a refused one, with a
 * message that says which, and go no further: no account is made and nothing reaches the add-on's service.
 * A passed check goes on to the service, as with no provider.
 *
 * The provider is stood in for: it accepts one token and refuses every other, so nothing here calls out.
 *
 * @package Corex\Tests\Integration\Captcha
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Captcha\Captcha;
use Corex\Captcha\CaptchaChallenge;
use Corex\Http\ClientAddress;
use Corex\Security\ChallengeSettings;
use Corex\Security\FormChallenge;
use Corex\Support\Config\ConfigInterface;
use Corex\Tests\Support\CreatedPosts;
use Corex\Tests\Support\MailDelivery;

const PROTECTED_ROUTES = [
    'registration'      => ['/corex/v1/account/register', ['email' => 'corex-captcha-probe@example.test', 'password' => 'A long passphrase 42', 'password_confirm' => 'A long passphrase 42', 'consent' => '1']],
    'newsletter'        => ['/corex/v1/newsletter/subscribe', ['email' => 'corex-captcha-probe@example.test', 'consent' => true]],
    'a call request'    => ['/corex/v1/bookings/request', ['leader' => 'nobody', 'name' => 'Probe', 'email' => 'corex-captcha-probe@example.test']],
    'a job application' => ['/corex/v1/careers/apply', ['job_id' => '0', 'name' => 'Probe', 'email' => 'corex-captcha-probe@example.test']],
];

beforeEach(function () {
    // A passed check sends the add-on's email: CoreX's is captured, and WordPress's own (a new
    // account's) is stopped before it leaves, so nothing reaches anybody.
    $this->mail      = MailDelivery::answerAs(MailDelivery::CAPTURE);
    $this->emailLogs = CreatedPosts::watch('corex_email_log', 'corex_email_asset');
    add_filter('pre_wp_mail', '__return_true');

    $container       = Boot::app()->container();
    $this->challenge = $container->make(FormChallenge::class);
    $this->usersCanRegister = get_option('users_can_register');
    update_option('users_can_register', '1');

    $turnstile = new class () implements ConfigInterface {
        public function get(string $key, mixed $default = null): mixed
        {
            return ['captcha.driver' => 'turnstile', 'captcha.secret' => 'a-secret', 'captcha.site_key' => 'a-key'][$key] ?? $default;
        }

        public function has(string $key): bool
        {
            return str_starts_with($key, 'captcha.');
        }
    };
    $acceptsOneToken = new class () implements Captcha {
        public function verify(string $token, array $context = []): bool
        {
            return $token === 'passed-token';
        }
    };

    $container->instance(FormChallenge::class, new CaptchaChallenge(
        $acceptsOneToken,
        new ChallengeSettings($turnstile),
        $container->make(ClientAddress::class),
    ));
});

afterEach(function () {
    Boot::app()->container()->instance(FormChallenge::class, $this->challenge);
    update_option('users_can_register', $this->usersCanRegister);
    remove_filter('pre_wp_mail', '__return_true');
    MailDelivery::stop($this->mail);
    $this->emailLogs->delete();

    global $wpdb;
    $wpdb->delete($wpdb->prefix . 'corex_subscribers', ['email' => 'corex-captcha-probe@example.test']);

    $user = get_user_by('email', 'corex-captcha-probe@example.test');
    if ($user instanceof WP_User) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($user->ID);
    }
});

/** @param array<string,mixed> $body */
function postProtected(string $route, array $body): WP_REST_Response
{
    $request = new WP_REST_Request('POST', $route);
    $request->set_body_params($body);

    return rest_get_server()->dispatch($request);
}

/** Whether anything of the probe was kept: an account, a subscriber, an email. */
function probeLeftNothing(array $emailsWritten): bool
{
    global $wpdb;
    $subscribers = (int) $wpdb->get_var($wpdb->prepare(
        'SELECT COUNT(*) FROM %i WHERE email = %s',
        $wpdb->prefix . 'corex_subscribers',
        'corex-captcha-probe@example.test',
    ));

    return get_user_by('email', 'corex-captcha-probe@example.test') === false
        && $subscribers === 0
        && $emailsWritten === [];
}

/** What a route said, whichever envelope it answers in. */
function refusalOf(WP_REST_Response $response): string
{
    $data = (array) $response->get_data();

    return (string) ($data['message'] ?? '');
}

it('refuses a request that never passed the check, asking to complete it', function (string $route, array $body) {
    $response = postProtected($route, $body);

    expect($response->get_status())->toBe(422)
        ->and(refusalOf($response))->toBe('Please complete the check before sending.')
        ->and(probeLeftNothing($this->emailLogs->stillStored()))->toBeTrue();
})->with(PROTECTED_ROUTES);

it('refuses a token the provider refused, saying the check failed', function (string $route, array $body) {
    $response = postProtected($route, $body + ['captcha_token' => 'a-refused-token']);

    expect($response->get_status())->toBe(422)
        ->and(refusalOf($response))->toBe('The check failed. Please try again.')
        ->and(probeLeftNothing($this->emailLogs->stillStored()))->toBeTrue();
})->with(PROTECTED_ROUTES);

it('lets a passed check through to the add-on, which answers for itself', function (string $route, array $body) {
    $response = postProtected($route, $body + ['captcha_token' => 'passed-token']);

    expect(refusalOf($response))->not->toBe('Please complete the check before sending.')
        ->and(refusalOf($response))->not->toBe('The check failed. Please try again.')
        ->and(($response->get_data()['error'] ?? '') === 'captcha')->toBeFalse();
})->with(PROTECTED_ROUTES);

it('creates the account once the check is passed', function () {
    postProtected(PROTECTED_ROUTES['registration'][0], PROTECTED_ROUTES['registration'][1] + ['captcha_token' => 'passed-token']);

    expect(get_user_by('email', 'corex-captcha-probe@example.test'))->toBeInstanceOf(WP_User::class);
});
