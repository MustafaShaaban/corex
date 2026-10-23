<?php

/**
 * Provider settings say what is missing and when a test failed (spec 119, US-SET-03); a saved
 * secret can be removed (US-SET-15); the login screen's logo link is a Brand setting (US-SET-13).
 *
 * On real WordPress, with the providers' answers given through `pre_http_request`, so nothing
 * leaves the machine.
 *
 * @package Corex\Tests\Integration\Config
 */

declare(strict_types=1);

use Corex\Activity\ActivityService;
use Corex\Boot;
use Corex\Config\Addons\AddonsScreen;
use Corex\Config\Branding\AdminBranding;
use Corex\Config\Settings\AdminDashboard;
use Corex\Config\Settings\ProviderSetup;
use Corex\Config\Settings\ProviderTestLog;
use Corex\Config\Settings\SettingsStore;
use Corex\Tests\Support\SettingsScreen;

const COREX_TEST_SECRET = 'secret-value-that-must-never-show';

beforeEach(function () {
    $this->signedInAs = get_current_user_id();
    $administrators   = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    $this->admin      = (int) ($administrators[0] ?? 0);
    wp_set_current_user($this->admin);

    $this->store = Boot::app()->container()->make(SettingsStore::class);
    $this->keys  = [
        'captcha.driver', 'captcha.site_key', 'captcha.secret', 'insights.psi.key',
        'insights.cloudflare.token', 'insights.cloudflare.account_id', 'brand.login_url',
    ];
    $this->was = [];
    foreach ($this->keys as $key) {
        $this->was[$key] = get_option($this->store->optionName($key), null);
    }
    $this->tests = get_option(ProviderTestLog::OPTION, null);
    delete_option(ProviderTestLog::OPTION);
});

afterEach(function () {
    foreach ($this->was as $key => $value) {
        $value === null ? $this->store->delete($key) : update_option($this->store->optionName($key), $value);
    }
    $this->tests === null ? delete_option(ProviderTestLog::OPTION) : update_option(ProviderTestLog::OPTION, $this->tests);
    remove_all_filters('pre_http_request');
    wp_set_current_user($this->signedInAs);
});

/**
 * Answers every outbound request with a JSON body, and keeps what was asked.
 *
 * @param array<string,mixed> $body
 */
function providerAnswers(int $status, array $body): ArrayObject
{
    $asked = new ArrayObject();
    add_filter('pre_http_request', static function ($pre, $args, $url) use ($status, $body, $asked) {
        $asked[] = $url;

        return ['headers' => [], 'body' => (string) wp_json_encode($body), 'response' => ['code' => $status, 'message' => ''], 'cookies' => []];
    }, 10, 3);

    return $asked;
}

function testProvider(string $provider): WP_REST_Response
{
    $request = new WP_REST_Request('POST', '/corex/v1/settings/providers/' . $provider . '/test');
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));

    return rest_do_request($request);
}

it('names the missing captcha key and its effect, from the value in force', function () {
    $this->store->save('captcha.driver', 'recaptcha');
    $this->store->save('captcha.site_key', 'site');
    $this->store->delete('captcha.secret');

    expect(SettingsScreen::draw('captcha', null, SettingsScreen::ALL_ADDONS))
        ->toContain('The secret key is missing, so forms are protected by the honeypot only.');
});

it('names the missing half of the Cloudflare pair', function () {
    $this->store->save('insights.cloudflare.token', 'token');
    $this->store->delete('insights.cloudflare.account_id');

    expect(SettingsScreen::draw('insights'))->toContain('The Cloudflare account ID is missing');
});

it('remembers a failed PageSpeed test until the key changes, and never answers or keeps the key', function () {
    $this->store->save('insights.psi.key', COREX_TEST_SECRET);
    $asked = providerAnswers(400, ['error' => ['message' => 'API key not valid. Please pass a valid API key.']]);

    $response = testProvider('pagespeed');

    expect($response->get_status())->toBe(200)
        ->and($response->get_data()['data']['worked'])->toBeFalse()
        ->and($response->get_data()['data']['status'])->toContain('Last test failed on')
        ->and($response->get_data()['data']['status'])->toContain('Google refused the key')
        ->and(wp_json_encode($response->get_data()))->not->toContain(COREX_TEST_SECRET)
        ->and(wp_json_encode(get_option(ProviderTestLog::OPTION)))->not->toContain(COREX_TEST_SECRET)
        ->and($asked[0])->toContain('pagespeedonline')
        ->and(SettingsScreen::draw('insights'))->toContain('Google refused the key')
        ->not->toContain(COREX_TEST_SECRET);

    // A new key is untested.
    $this->store->save('insights.psi.key', 'another-key');
    expect(SettingsScreen::draw('insights'))->toContain('Not tested yet')
        ->not->toContain('Google refused the key');
});

it('says a key that works is working', function () {
    $this->store->save('insights.cloudflare.token', 'token');
    providerAnswers(200, ['success' => true, 'result' => ['status' => 'active']]);

    expect(testProvider('cloudflare')->get_data()['data']['status'])->toContain('working');
});

it('refuses a test without a key, and refuses one who may not change Settings', function () {
    $this->store->delete('insights.psi.key');
    expect(testProvider('pagespeed')->get_status())->toBe(400);

    $this->store->save('insights.psi.key', 'key');
    $editor = wp_insert_user(['user_login' => 'corex-provider-editor-' . uniqid(), 'user_pass' => wp_generate_password(), 'role' => 'editor']);
    wp_set_current_user((int) $editor);
    expect(testProvider('pagespeed')->get_status())->toBe(403);
    wp_set_current_user($this->admin);
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user((int) $editor);
});

it('remembers what the captcha add-on\'s own test found', function () {
    $this->store->save('captcha.driver', 'turnstile');
    $this->store->save('captcha.site_key', 'site');
    $this->store->save('captcha.secret', COREX_TEST_SECRET);

    do_action('corex_provider_tested', 'captcha', false, 'the provider refused the secret key');

    expect((string) apply_filters('corex_provider_test_status', '', 'captcha'))->toContain('Last test failed on')
        ->and(SettingsScreen::draw('captcha', null, SettingsScreen::ALL_ADDONS))->toContain('the provider refused the secret key')
        ->not->toContain(COREX_TEST_SECRET);
});

it('removes a saved secret when asked, says no value is saved, and records it without the value', function () {
    $this->store->save('captcha.driver', 'turnstile');
    $this->store->save('captcha.site_key', 'site');
    $this->store->save('captcha.secret', COREX_TEST_SECRET);

    $page = SettingsScreen::draw('captcha', null, SettingsScreen::ALL_ADDONS);
    expect($page)->toContain('A value is saved')
        ->and($page)->toContain('value="captcha.secret"')
        ->and($page)->not->toContain(COREX_TEST_SECRET);

    $result = SettingsScreen::save('captcha', ['corex_remove_secret' => ['captcha.secret']], SettingsScreen::ALL_ADDONS);

    $event = Boot::app()->container()->make(ActivityService::class)->query(['area' => 'settings'], 1, 1)[0] ?? null;
    expect(get_option($this->store->optionName('captcha.secret'), null))->toBeNull()
        ->and($result?->removed)->toBe(['captcha.secret'])
        ->and(SettingsScreen::draw('captcha', $result, SettingsScreen::ALL_ADDONS))->toContain('No value saved')
        ->toContain('Secret key was removed.')
        ->and(Boot::app()->container()->make(ProviderSetup::class)->captchaNeedsSetup())->toBeTrue()
        ->and($event?->kind)->toBe('settings.secret_removed')
        ->and($event?->targetId)->toBe('captcha.secret')
        ->and(wp_json_encode([$event?->summary, $event?->context, $event?->targetLabel]))->not->toContain(COREX_TEST_SECRET);
});

it('keeps a saved secret when its field is saved empty', function () {
    $this->store->save('insights.psi.key', COREX_TEST_SECRET);

    SettingsScreen::save('insights', ['insights_psi_key' => '']);

    expect($this->store->get('insights.psi.key'))->toBe(COREX_TEST_SECRET);
});

it('removes nothing for a person who may not change Settings', function () {
    $this->store->save('insights.psi.key', COREX_TEST_SECRET);
    $editor = wp_insert_user(['user_login' => 'corex-settings-editor-' . uniqid(), 'user_pass' => wp_generate_password(), 'role' => 'editor']);
    wp_set_current_user((int) $editor);
    $posted = $_POST;
    $_POST  = wp_slash([
        'corex_settings_nonce' => wp_create_nonce('corex_settings'),
        'corex_settings_page'  => 'insights',
        'corex_remove_secret'  => ['insights.psi.key'],
    ]);

    Boot::app()->container()->make(AdminDashboard::class)->maybeSave();

    $_POST = $posted;
    wp_set_current_user($this->admin);
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user((int) $editor);
    expect($this->store->get('insights.psi.key'))->toBe(COREX_TEST_SECRET);
});

it('says on the captcha card that a key is missing, as Settings says it', function () {
    $this->store->save('captcha.driver', 'recaptcha');
    $this->store->save('captcha.site_key', 'site');
    $this->store->delete('captcha.secret');

    $setup = Boot::app()->container()->make(ProviderSetup::class);
    $line  = (new ReflectionMethod(AddonsScreen::class, 'setupNeed'))->invoke(
        Boot::app()->container()->make(AddonsScreen::class),
        new \Corex\Config\Addons\AddonView(
            Boot::app()->container()->make(\Corex\Config\Addons\AddonRegistry::class)->find('corex-captcha'),
            true,
            true,
            true,
        ),
    );

    expect($line)->toBe($setup->captchaNeed())
        ->and($line)->toContain('secret key is missing');
});

it('links the login screen\'s logo where Brand says, and refuses what is not a web address', function () {
    $this->store->save('brand.login_url', 'https://example.org/');
    expect(Boot::app()->container()->make(AdminBranding::class)->loginUrl('http://site.test/'))->toBe('https://example.org/');

    $result = SettingsScreen::save('general', ['brand_login_url' => 'example']);
    expect($this->store->get('brand.login_url'))->toBe('https://example.org/')
        ->and($result?->refused[0]['label'] ?? '')->toBe('Login logo link');

    $this->store->delete('brand.login_url');
    expect(Boot::app()->container()->make(AdminBranding::class)->loginUrl('http://site.test/'))->toBe(home_url('/'));
});
