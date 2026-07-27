<?php

/**
 * Unit tests for the captcha drivers + resolver (spec 012 US2: FR-003, FR-004, FR-005, SC-003/4).
 *
 * @package Corex\Tests\Unit\Captcha
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Captcha\CaptchaResolver;
use Corex\Captcha\HoneypotCaptcha;
use Corex\Captcha\NullCaptcha;
use Corex\Captcha\RecaptchaV3Captcha;
use Corex\Captcha\RemoteCaptcha;
use Corex\Support\Config\ConfigInterface;

/**
 * @param array<string,mixed> $values
 */
function captchaConfig(array $values): ConfigInterface
{
    return new class($values) implements ConfigInterface {
        /** @param array<string,mixed> $values */
        public function __construct(private array $values)
        {
        }

        public function get(string $key, mixed $default = null): mixed
        {
            return $this->values[$key] ?? $default;
        }

        public function has(string $key): bool
        {
            return array_key_exists($key, $this->values);
        }
    };
}

it('the null driver always passes', function () {
    expect((new NullCaptcha())->verify('anything'))->toBeTrue();
});

it('the honeypot driver passes when empty and fails when filled', function () {
    expect((new HoneypotCaptcha())->verify(''))->toBeTrue()
        ->and((new HoneypotCaptcha())->verify('i-am-a-bot'))->toBeFalse();
});

it('the remote driver passes only on a confirmed success response', function () {
    Functions\when('is_wp_error')->justReturn(false);
    Functions\when('wp_remote_post')->justReturn(['body' => '']);

    Functions\when('wp_remote_retrieve_body')->justReturn('{"success":true}');
    expect((new RemoteCaptcha('https://verify', 'secret'))->verify('token'))->toBeTrue();

    Functions\when('wp_remote_retrieve_body')->justReturn('{"success":false}');
    expect((new RemoteCaptcha('https://verify', 'secret'))->verify('token'))->toBeFalse();
});

it('the remote driver fails closed with no secret or no token', function () {
    expect((new RemoteCaptcha('https://verify', ''))->verify('token'))->toBeFalse()
        ->and((new RemoteCaptcha('https://verify', 'secret'))->verify(''))->toBeFalse();
});

it('the resolver selects the configured driver', function () {
    expect((new CaptchaResolver(captchaConfig([])))->resolve())->toBeInstanceOf(NullCaptcha::class)
        ->and((new CaptchaResolver(captchaConfig(['captcha.driver' => 'honeypot'])))->resolve())->toBeInstanceOf(HoneypotCaptcha::class)
        ->and((new CaptchaResolver(captchaConfig(['captcha.driver' => 'turnstile', 'captcha.secret' => 's'])))->resolve())->toBeInstanceOf(RemoteCaptcha::class);
});

it('the resolver treats a key driver with no secret as not configured', function () {
    // Picking a provider in Settings and not pasting the keys used to build a live driver with an
    // empty secret, which rejects every verification — so every form on the site silently stopped
    // accepting submissions, reported only as "Submission protection rejected the request."
    // Half-configured is not configured: the honeypot still guards, and the diagnostic (which has
    // always classified this as `missing_keys`) is where the operator is told to finish the setup.
    foreach (['recaptcha', 'turnstile', 'hcaptcha'] as $driver) {
        expect((new CaptchaResolver(captchaConfig(['captcha.driver' => $driver])))->resolve())
            ->toBeInstanceOf(NullCaptcha::class)
            ->and((new CaptchaResolver(captchaConfig(['captcha.driver' => $driver, 'captcha.secret' => '   '])))->resolve())
            ->toBeInstanceOf(NullCaptcha::class);
    }
});

it('the resolver routes reCAPTCHA to the scored v3 driver, not the success-only remote driver', function () {
    $driver = (new CaptchaResolver(captchaConfig(['captcha.driver' => 'recaptcha', 'captcha.secret' => 's'])))->resolve();

    expect($driver)->toBeInstanceOf(RecaptchaV3Captcha::class)
        ->and($driver)->not->toBeInstanceOf(RemoteCaptcha::class);
});
