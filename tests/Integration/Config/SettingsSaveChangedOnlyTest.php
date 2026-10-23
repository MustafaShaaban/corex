<?php

/**
 * Saving Settings changes only what was changed (spec 114, US-SET-14, FR-028 to FR-030; spec 119,
 * US-SET-01 and US-SET-07: a page saves only itself).
 *
 * The save walked every field of every section and read a checkbox missing from the post as
 * "off". A section whose add-on is off draws its fields disabled, and a browser sends none of
 * them, so saving Brand with Corex Media off switched WebP conversion off.
 *
 * On real WordPress, through the screen's own saver, with the add-ons' activation chosen per test.
 *
 * @package Corex\Tests\Integration\Config
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Settings\SettingsRegistry;
use Corex\Config\Settings\SettingsStore;
use Corex\Tests\Support\SettingsScreen;

beforeEach(function () {
    $this->signedInAs = get_current_user_id();
    $administrators   = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));
    $this->settings = Boot::app()->container()->make(SettingsStore::class);
    $this->asItWas  = [];
    foreach (Boot::app()->container()->make(SettingsRegistry::class)->keys() as $key) {
        $this->asItWas[$key] = get_option($this->settings->optionName($key), null);
    }
});

afterEach(function () {
    foreach ($this->asItWas as $key => $value) {
        $value === null
            ? delete_option($this->settings->optionName($key))
            : update_option($this->settings->optionName($key), $value);
    }
    wp_set_current_user($this->signedInAs);
});

/** @return array<string,mixed> every stored setting, by key */
function storedSettings(): array
{
    $store = Boot::app()->container()->make(SettingsStore::class);

    return array_combine(
        Boot::app()->container()->make(SettingsRegistry::class)->keys(),
        array_map(
            static fn (string $key) => get_option($store->optionName($key), null),
            Boot::app()->container()->make(SettingsRegistry::class)->keys(),
        ),
    );
}

it('keeps the WebP switches as stored when General is saved while Corex Media is off', function () {
    $this->settings->save('media.webp.enabled', '1');
    $this->settings->save('media.webp.convert_jpeg', '1');
    $this->settings->save('media.webp.convert_png', '1');
    $mediaOff = ['corex-captcha/corex-captcha.php', 'corex-guides/corex-guides.php'];

    SettingsScreen::save('general', ['brand_company_name' => 'Acme Trading'], $mediaOff);

    expect($this->settings->get('brand.company_name'))->toBe('Acme Trading')
        ->and($this->settings->get('media.webp.enabled'))->toBe('1')
        ->and($this->settings->get('media.webp.convert_jpeg'))->toBe('1')
        ->and($this->settings->get('media.webp.convert_png'))->toBe('1');

    // And Corex Media turned back on draws them as they were.
    expect(SettingsScreen::draw('media', null, SettingsScreen::ALL_ADDONS))
        ->toMatch('/id="media_webp_enabled" name="media_webp_enabled" type="checkbox" value="1" checked/');
});

it('writes nothing to the Media page when it is posted while Corex Media is off', function () {
    $this->settings->save('media.webp.enabled', '1');
    $before = storedSettings();

    $result = SettingsScreen::save('media', ['media_webp_enabled' => '', 'media_webp_quality' => '40'], ['corex-captcha/corex-captcha.php']);

    expect($result?->page)->toBe('media')
        ->and(storedSettings())->toBe($before);
});

it('keeps the captcha settings as stored when Email is saved while Corex Captcha is off', function () {
    $this->settings->save('captcha.driver', 'recaptcha');
    $this->settings->save('captcha.site_key', 'site-key-kept');
    $this->settings->save('captcha.score_threshold', '0.5');

    SettingsScreen::save('email', ['mail_from_name' => 'Acme Sales'], ['corex-media/corex-media.php']);

    expect($this->settings->get('mail.from.name'))->toBe('Acme Sales')
        ->and($this->settings->get('captcha.driver'))->toBe('recaptcha')
        ->and($this->settings->get('captcha.site_key'))->toBe('site-key-kept')
        ->and($this->settings->get('captcha.score_threshold'))->toBe('0.5');
});

it('saves only the page that was posted, whatever else the post carries', function () {
    $this->settings->save('mail.from.name', 'Kept Sender');

    SettingsScreen::save('general', ['brand_tagline' => 'Built to last', 'mail_from_name' => 'Smuggled In'], SettingsScreen::ALL_ADDONS);

    expect($this->settings->get('brand.tagline'))->toBe('Built to last')
        ->and($this->settings->get('mail.from.name'))->toBe('Kept Sender');
});

it('writes nothing when a page is saved as it was drawn', function (string $page) {
    delete_option($this->settings->optionName('media.webp.enabled'));
    $before = storedSettings();

    SettingsScreen::save($page, [], SettingsScreen::ALL_ADDONS);

    // A Media switch drawn as on while nothing was stored stays unstored, so its default applies;
    // a select drawn at its first choice with nothing stored is not written either.
    expect(storedSettings())->toBe($before);
})->with(['general', 'email', 'captcha', 'media', 'insights', 'guides']);

it('saves a switch turned off on the page being saved as off', function () {
    $this->settings->save('media.webp.enabled', '1');

    SettingsScreen::save('media', ['media_webp_enabled' => ''], SettingsScreen::ALL_ADDONS);

    expect($this->settings->get('media.webp.enabled'))->toBe('0');
});

it('saves a switch turned on as "1", as the checkbox did', function () {
    delete_option($this->settings->optionName('dashboard.widgets.attention'));

    SettingsScreen::save('general', ['dashboard_widgets_attention' => '1'], SettingsScreen::ALL_ADDONS);

    expect(get_option($this->settings->optionName('dashboard.widgets.attention')))->toBe('1');
});

it('refuses a value its field does not take and changes nothing else', function () {
    $this->settings->save('mail.reply.from_address', 'before@example.com');
    $this->settings->save('media.webp.convert_png', '1');

    $result = SettingsScreen::save('email', ['mail_reply_from_address' => 'answers@'], ['corex-captcha/corex-captcha.php']);

    expect($this->settings->get('mail.reply.from_address'))->toBe('before@example.com')
        ->and($this->settings->get('media.webp.convert_png'))->toBe('1')
        ->and($result?->refused[0]['name'] ?? '')->toBe('mail_reply_from_address');
});

it('refuses a page that does not exist', function () {
    $before = storedSettings();

    $saved = Boot::app()->container()->make(\Corex\Config\Settings\SettingsSaver::class)
        ->save('nowhere', ['brand_tagline' => 'x'], get_current_user_id());

    expect($saved)->toBeNull()
        ->and(storedSettings())->toBe($before);
});
