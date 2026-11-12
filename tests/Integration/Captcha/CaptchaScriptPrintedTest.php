<?php

/**
 * Integration test: the provider's script reaches the page a protected form is on (spec 115, US-AUT-02,
 * FR-029), on real ./wp with WordPress's own footer printer.
 *
 * The loader decides at the end of the page, once every form has declared itself. It used to decide on
 * `wp_footer` at priority 20, after WordPress had printed the footer's scripts at that same priority, so
 * what it queued was never printed. Here it must run before WordPress's footer printer, and what it
 * queues must be in what that printer prints.
 *
 * @package Corex\Tests\Integration\Captcha
 */

declare(strict_types=1);

use Corex\Captcha\CaptchaAssetController;
use Corex\Captcha\CaptchaChallenge;
use Corex\Captcha\NullCaptcha;
use Corex\Http\RemoteAddress;
use Corex\Security\ChallengeSettings;
use Corex\Support\Config\ConfigInterface;

function turnstileSite(): ConfigInterface
{
    return new class () implements ConfigInterface {
        public function get(string $key, mixed $default = null): mixed
        {
            return ['captcha.driver' => 'turnstile', 'captcha.secret' => 'a-secret', 'captcha.site_key' => 'a-key'][$key] ?? $default;
        }

        public function has(string $key): bool
        {
            return str_starts_with($key, 'captcha.');
        }
    };
}

/**
 * What the footer's scripts are once the loader has run in its place on `wp_footer`: before WordPress's
 * own printer, which must run after it.
 */
function footerWith(CaptchaAssetController $loader): string
{
    $scripts               = $GLOBALS['wp_scripts'] ?? null;
    $GLOBALS['wp_scripts'] = new WP_Scripts();
    $loader->register();
    try {
        expect(has_action('wp_footer', [$loader, 'enqueue']))
            ->toBeLessThan(has_action('wp_footer', 'wp_print_footer_scripts'));

        $loader->enqueue();
        ob_start();
        wp_print_footer_scripts();

        return (string) ob_get_clean();
    } finally {
        remove_action('wp_footer', [$loader, 'enqueue'], CaptchaAssetController::PRIORITY);
        $GLOBALS['wp_scripts'] = $scripts;
    }
}

it('prints the provider script on a page where a form printed the check', function () {
    $challenge = new CaptchaChallenge(new NullCaptcha(), new ChallengeSettings(turnstileSite()), new RemoteAddress());
    $loader    = new CaptchaAssetController(null, $challenge, turnstileSite());

    $challenge->fields('corex_register');
    $printed = footerWith($loader);

    expect($printed)->toContain('corex-captcha-widget.js')
        ->and($printed)->toContain('challenges.cloudflare.com/turnstile/v0/api.js');
});

it('prints nothing of the provider on a page where no form printed the check', function () {
    $challenge = new CaptchaChallenge(new NullCaptcha(), new ChallengeSettings(turnstileSite()), new RemoteAddress());

    $printed = footerWith(new CaptchaAssetController(null, $challenge, turnstileSite()));

    expect($printed)->not->toContain('corex-captcha-widget.js')
        ->and($printed)->not->toContain('challenges.cloudflare.com');
});
