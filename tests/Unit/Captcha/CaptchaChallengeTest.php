<?php

/**
 * Unit tests for the provider's check on the add-ons' public forms (spec 115, US-AUT-02 and US-VIS-11,
 * FR-028 to FR-034): what a form prints for each provider, that printing it declares the page needs the
 * provider's script, which message a refused token gets, and that a reCAPTCHA v3 token is judged against
 * this site's own hostname and the form's action, so a good token passes.
 *
 * @package Corex\Tests\Unit\Captcha
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Captcha\Captcha;
use Corex\Captcha\CaptchaChallenge;
use Corex\Captcha\HoneypotCaptcha;
use Corex\Captcha\NullCaptcha;
use Corex\Http\ClientAddress;
use Corex\Security\ChallengeContext;
use Corex\Security\ChallengeSettings;
use Corex\Security\ChallengeVerification;
use Corex\Security\VerifyingChallenge;
use Corex\Support\Config\ConfigInterface;

/** @param array<string,mixed> $values */
function challengeSettingsConfig(array $values): ConfigInterface
{
    return new class ($values) implements ConfigInterface {
        /** @param array<string,mixed> $values */
        public function __construct(private readonly array $values)
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

function challengeFor(Captcha $captcha, array $config): CaptchaChallenge
{
    $client = new class () implements ClientAddress {
        public function current(): string
        {
            return '203.0.113.9';
        }
    };

    return new CaptchaChallenge($captcha, new ChallengeSettings(challengeSettingsConfig($config)), $client);
}

/** A provider that answers as told. */
function answeringCaptcha(bool $passes): Captcha
{
    return new class ($passes) implements Captcha {
        public function __construct(private readonly bool $passes)
        {
        }

        public function verify(string $token, array $context = []): bool
        {
            return $this->passes && $token !== '';
        }
    };
}

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('esc_attr')->returnArg();
    Functions\when('home_url')->justReturn('https://acme.test');
    Functions\when('wp_parse_url')->alias(static fn (string $url, int $part) => parse_url($url, $part));
});

it('prints the token field and the widget place for a provider that shows one', function (string $driver) {
    $challenge = challengeFor(answeringCaptcha(true), ['captcha.driver' => $driver, 'captcha.secret' => 's', 'captcha.site_key' => 'site-key']);

    $html = $challenge->fields('corex_register');

    expect($html)->toContain('name="captcha_token"')
        ->and($html)->toContain('data-corex-captcha-action="corex_register"')
        ->and($html)->toContain('data-corex-challenge="' . $driver . '" data-corex-sitekey="site-key"')
        ->and($challenge->formAttribute())->toBe(CaptchaChallenge::FORM_ATTRIBUTE)
        ->and($challenge->declared())->toBeTrue();
})->with(['turnstile', 'hcaptcha']);

it('prints only the token field for reCAPTCHA v3, which shows nothing', function () {
    $html = challengeFor(answeringCaptcha(true), ['captcha.driver' => 'recaptcha', 'captcha.secret' => 's'])->fields('corex_newsletter');

    expect($html)->toContain('name="captcha_token"')
        ->and($html)->not->toContain('corex-form__challenge');
});

it('prints nothing, and declares nothing, without a provider and its secret', function (array $config) {
    $challenge = challengeFor(new NullCaptcha(), $config);

    expect($challenge->fields('corex_register'))->toBe('')
        ->and($challenge->formAttribute())->toBe('')
        ->and($challenge->declared())->toBeFalse();
})->with([
    'no provider'          => [[]],
    'honeypot only'        => [['captcha.driver' => 'honeypot']],
    'a provider, no secret' => [['captcha.driver' => 'turnstile', 'captcha.site_key' => 'k']],
]);

it('lets every request through with no provider, or the honeypot, as before', function (Captcha $captcha) {
    expect(challengeFor($captcha, [])->refusal('', 'corex_register'))->toBeNull();
})->with([
    'no provider' => [new NullCaptcha()],
    'honeypot'    => [new HoneypotCaptcha()],
]);

it('asks to complete the check when no token came, and says it failed when the provider refused one', function () {
    $challenge = challengeFor(answeringCaptcha(false), ['captcha.driver' => 'turnstile', 'captcha.secret' => 's']);

    expect($challenge->refusal('', 'corex_register'))->toBe('Please complete the check before sending.')
        ->and($challenge->refusal('tok', 'corex_register'))->toBe('The check failed. Please try again.')
        ->and(challengeFor(answeringCaptcha(true), ['captcha.driver' => 'turnstile', 'captcha.secret' => 's'])->refusal(' tok ', 'corex_register'))->toBeNull();
});

it('judges a reCAPTCHA v3 token against this site, the form action and the configured score', function () {
    $v3 = new class () implements Captcha, VerifyingChallenge {
        public ?ChallengeContext $asked = null;

        public function verify(string $token, array $context = []): bool
        {
            return false; // the boolean contract cannot judge a v3 token: it must not be used
        }

        public function challenge(string $token, ChallengeContext $context): ChallengeVerification
        {
            $this->asked = $context;

            return ChallengeVerification::pass(0.9, $context->threshold, $context->expectedAction, 'acme.test');
        }
    };

    $refusal = challengeFor($v3, ['captcha.driver' => 'recaptcha', 'captcha.secret' => 's', 'captcha.score_threshold' => '0.5'])
        ->refusal('tok', 'corex_job_application');

    expect($refusal)->toBeNull()
        ->and($v3->asked->expectedAction)->toBe('corex_job_application')
        ->and($v3->asked->threshold)->toBe(0.5)
        ->and($v3->asked->allowedHostnames)->toBe(['acme.test'])
        ->and($v3->asked->remoteIp)->toBe('203.0.113.9');
});
