<?php

/**
 * Integration test: a form's rate limit counts visitors, not the proxy in front of them (#247).
 *
 * Reported 2026-10-06 from a client site behind a tunnel. The limit was keyed on `REMOTE_ADDR`,
 * which there is the tunnel: one visitor could use up the allowance for everybody.
 *
 * @package Corex\Tests\Integration\Forms
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Security\LoginProtection\ClientIpResolver;
use Corex\Config\Security\LoginProtection\LoginProtectionSettings;
use Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore;
use Corex\Config\Security\LoginProtection\TrustedProxyClientAddress;
use Corex\Forms\Submission\FormSubmissionService;
use Corex\Forms\Submission\SubmitController;
use Corex\Http\ClientAddress;
use Corex\Http\Middleware\MiddlewareResolver;
use Corex\Http\Middleware\Pipeline;
use Corex\Tests\Support\WatchedTransients;

const FORM_ADDRESS_TEST_PROXY = '10.20.30.40';

/**
 * The contact form's controller as a request would get it, with the proxy settings as they are
 * stored now. Built by hand because the container keeps one resolver for the life of a request,
 * and this process outlives the option being changed under it.
 */
function contactFormBehindStoredProxies(): SubmitController
{
    $container = Boot::app()->container();

    return new SubmitController(
        $container->make(FormSubmissionService::class),
        $container->make(Pipeline::class),
        $container->make(MiddlewareResolver::class),
        new TrustedProxyClientAddress(new ClientIpResolver($container->make(LoginProtectionSettings::class))),
    );
}

/** One submission that passes the nonce and fails validation, so nothing is stored or sent. */
function emptySubmissionFrom(SubmitController $controller, string $visitor): int
{
    $_SERVER['REMOTE_ADDR']          = FORM_ADDRESS_TEST_PROXY;
    $_SERVER['HTTP_X_FORWARDED_FOR'] = $visitor;

    $request = new WP_REST_Request('POST', '/corex/v1/forms/contact');
    $request->set_url_params(['slug' => 'contact']);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));

    return $controller->submit($request)->get_status();
}

function exhaustAllowanceOf(SubmitController $controller, string $visitor): void
{
    for ($attempt = 0; $attempt < 200; $attempt++) {
        if (emptySubmissionFrom($controller, $visitor) === 429) {
            return;
        }
    }

    throw new RuntimeException('The contact form never rate-limited the visitor.');
}

/** @param list<string> $proxies */
function trustProxies(array $proxies): void
{
    $stored = get_option(LoginProtectionSettingsStore::OPTION, []);

    update_option(LoginProtectionSettingsStore::OPTION, [
        'trusted_proxy_mode'   => $proxies !== [],
        'trusted_proxy_ranges' => $proxies,
    ] + (is_array($stored) ? $stored : []), false);
}

beforeEach(function () {
    $this->server         = $_SERVER;
    $this->storedSettings = get_option(LoginProtectionSettingsStore::OPTION, null);
    $this->transients     = WatchedTransients::watch('corex_throttle_');
});

afterEach(function () {
    $_SERVER = $this->server;

    if ($this->storedSettings === null) {
        delete_option(LoginProtectionSettingsStore::OPTION);
    } else {
        update_option(LoginProtectionSettingsStore::OPTION, $this->storedSettings, false);
    }

    $this->transients->restore();
});

it('gives forms the address corex-config resolves, not the connection’s', function () {
    expect(Boot::app()->container()->make(ClientAddress::class))->toBeInstanceOf(TrustedProxyClientAddress::class);
});

it('leaves a second visitor their own allowance behind a trusted proxy', function () {
    trustProxies([FORM_ADDRESS_TEST_PROXY]);
    $form = contactFormBehindStoredProxies();

    exhaustAllowanceOf($form, '198.51.100.7');

    expect(emptySubmissionFrom($form, '198.51.100.8'))->toBe(422)
        ->and(emptySubmissionFrom($form, '198.51.100.7'))->toBe(429);
});

it('counts everyone behind a proxy as one client until the proxy is trusted', function () {
    trustProxies([]);
    $form = contactFormBehindStoredProxies();

    exhaustAllowanceOf($form, '198.51.100.7');

    expect(emptySubmissionFrom($form, '198.51.100.8'))->toBe(429);
});
