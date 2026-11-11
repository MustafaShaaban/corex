<?php

/**
 * What a provider still needs, read from the value in force (spec 119, US-SET-03, FR-012 to FR-014).
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Settings\ProviderSetup;
use Corex\Config\Settings\ProviderTestLog;
use Corex\Support\Config\ConfigInterface;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

/** @param array<string,string> $values the values in force, wherever they are set */
function setupWith(array $values): ProviderSetup
{
    return new ProviderSetup(new class ($values) implements ConfigInterface {
        /** @param array<string,string> $values */
        public function __construct(private readonly array $values)
        {
        }

        public function get(string $key, mixed $default = null): mixed
        {
            return $this->values[$key] ?? $default;
        }

        public function has(string $key): bool
        {
            return isset($this->values[$key]);
        }
    });
}

it('names the missing captcha key and what it means for forms', function (array $values, string $need) {
    expect(setupWith($values)->captchaNeed())->toBe($need)
        ->and(setupWith($values)->captchaNeedsSetup())->toBe($need !== '');
})->with([
    'the secret missing' => [
        ['captcha.driver' => 'recaptcha', 'captcha.site_key' => 'site'],
        'The secret key is missing, so forms are protected by the honeypot only.',
    ],
    'the site key missing' => [
        ['captcha.driver' => 'turnstile', 'captcha.secret' => 'secret'],
        'The site key is missing, so the provider\'s check cannot load on your forms, and protected forms refuse what visitors send.',
    ],
    'both missing' => [
        ['captcha.driver' => 'hcaptcha'],
        'The site key and the secret key are missing, so forms are protected by the honeypot only.',
    ],
    'both set' => [['captcha.driver' => 'recaptcha', 'captcha.site_key' => 'site', 'captcha.secret' => 'secret'], ''],
    'the honeypot, which takes no keys' => [['captcha.driver' => 'honeypot'], ''],
    'no driver chosen' => [[], ''],
]);

it('says what Insights does without a PageSpeed key, and nothing with one', function () {
    expect(setupWith([])->pageSpeedNeed())->toContain('keyless quota')
        ->and(setupWith(['insights.psi.key' => 'key'])->pageSpeedNeed())->toBe('');
});

it('names the half of the Cloudflare pair that is missing', function () {
    expect(setupWith(['insights.cloudflare.token' => 't'])->cloudflareNeed())->toContain('account ID is missing')
        ->and(setupWith(['insights.cloudflare.account_id' => 'a'])->cloudflareNeed())->toContain('API token is missing')
        ->and(setupWith([])->cloudflareNeed())->toBe('')
        ->and(setupWith(['insights.cloudflare.token' => 't', 'insights.cloudflare.account_id' => 'a'])->cloudflareNeed())->toBe('');
});

it('offers a test only for a provider with a key to test', function () {
    $keyed = setupWith(['insights.psi.key' => 'k', 'insights.cloudflare.token' => 't', 'captcha.driver' => 'recaptcha', 'captcha.site_key' => 's', 'captcha.secret' => 'x']);

    expect($keyed->hasKeyFor(ProviderTestLog::PAGESPEED))->toBeTrue()
        ->and($keyed->hasKeyFor(ProviderTestLog::CLOUDFLARE))->toBeTrue()
        ->and($keyed->hasKeyFor(ProviderTestLog::CAPTCHA))->toBeTrue()
        ->and(setupWith([])->hasKeyFor(ProviderTestLog::PAGESPEED))->toBeFalse()
        ->and(setupWith(['captcha.driver' => 'recaptcha', 'captcha.site_key' => 's'])->hasKeyFor(ProviderTestLog::CAPTCHA))->toBeFalse();
});
