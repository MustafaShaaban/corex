<?php

/**
 * Saving Settings changes only what was changed (spec 114, US-SET-14, FR-028 to FR-030).
 *
 * The save walked every field of every section and read a checkbox missing from the post as
 * "off". A section whose add-on is off draws its fields disabled, and a browser sends none of
 * them, so saving Brand with Corex Media off switched WebP conversion off.
 *
 * On real WordPress, through the screen's own save, with the add-ons' activation chosen per test.
 *
 * @package Corex\Tests\Integration\Config
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Settings\AdminDashboard;
use Corex\Config\Settings\SettingsRegistry;
use Corex\Config\Settings\SettingsStore;
use Corex\Multisite\ActivationScope;
use Corex\Multisite\PluginActivationInspector;

beforeEach(function () {
    $this->signedInAs = get_current_user_id();
    $administrators   = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));
    $this->settings = Boot::app()->container()->make(SettingsStore::class);
    $this->asItWas  = [];
    foreach (Boot::app()->container()->make(SettingsRegistry::class)->keys() as $key) {
        $this->asItWas[$key] = get_option($this->settings->optionName($key), null);
    }
    $this->posted = $_POST;
});

afterEach(function () {
    $_POST = $this->posted;
    foreach ($this->asItWas as $key => $value) {
        $value === null
            ? delete_option($this->settings->optionName($key))
            : update_option($this->settings->optionName($key), $value);
    }
    wp_set_current_user($this->signedInAs);
});

/**
 * The settings screen as it is built, with only the named add-on plugins active.
 *
 * @param list<string> $active plugin files
 */
function settingsScreenWith(array $active): AdminDashboard
{
    $container = Boot::app()->container();
    $inspector = new class ($active) implements PluginActivationInspector {
        /** @param list<string> $active */
        public function __construct(private readonly array $active)
        {
        }

        public function scopeOf(string $pluginFile): ActivationScope
        {
            return in_array($pluginFile, $this->active, true) ? ActivationScope::Site : ActivationScope::None;
        }

        public function isActive(string $pluginFile): bool
        {
            return $this->scopeOf($pluginFile)->isActive();
        }

        public function activePluginFiles(): array
        {
            return $this->active;
        }

        public function scopes(): array
        {
            return array_fill_keys($this->active, ActivationScope::Site);
        }
    };

    return new AdminDashboard(
        $container->make(SettingsRegistry::class),
        $container->make(\Corex\Config\Settings\SettingsForm::class),
        $container->make(SettingsStore::class),
        $container->make(\Corex\Security\Admin\AdminGuard::class),
        $container->make(\Corex\Admin\AdminPage::class),
        $container->make(\Corex\Config\Overview\OverviewRenderer::class),
        $inspector,
        $container->make(\Corex\Multisite\MultisiteContext::class),
        $container->make(\Corex\Multisite\NetworkContext::class),
    );
}

/**
 * What a browser posts for the form as drawn: every enabled field with the value it shows,
 * and the changes on top. Fields drawn disabled or not drawn are not posted.
 *
 * @param array<string,string> $changes field name => typed value; '' unticks a checkbox
 */
function postedForm(AdminDashboard $screen, array $changes): array
{
    ob_start();
    $screen->renderSettings();
    $html = (string) ob_get_clean();

    $posted = [];
    preg_match_all('/<input id="([a-z0-9_]+)" name="\1" type="(\w+)" value="([^"]*)"([^>]*)\/>/', $html, $inputs, PREG_SET_ORDER);
    foreach ($inputs as [, $name, $type, $value, $rest]) {
        if (str_contains($rest, 'disabled')) {
            continue;
        }
        if ($type === 'checkbox' && ! str_contains($rest, 'checked')) {
            continue;
        }
        $posted[$name] = html_entity_decode($value);
    }
    preg_match_all('/<select id="([a-z0-9_]+)" name="\1"[^>]*?(disabled)?>(.*?)<\/select>/s', $html, $selects, PREG_SET_ORDER);
    foreach ($selects as $select) {
        if (($select[2] ?? '') !== '') {
            continue;
        }
        preg_match('/<option value="([^"]*)" selected/', $select[3], $selected);
        $posted[$select[1]] = html_entity_decode($selected[1] ?? '');
    }

    foreach ($changes as $name => $value) {
        if ($value === '') {
            unset($posted[$name]);
        } else {
            $posted[$name] = $value;
        }
    }

    return $posted + ['corex_settings_nonce' => wp_create_nonce('corex_settings')];
}

function saveAs(AdminDashboard $screen, array $posted): void
{
    $_POST = wp_slash($posted);
    $screen->maybeSave();
}

it('keeps the WebP switches as stored when Brand is saved while Corex Media is off', function () {
    $this->settings->save('media.webp.enabled', '1');
    $this->settings->save('media.webp.convert_jpeg', '1');
    $this->settings->save('media.webp.convert_png', '1');
    $screen = settingsScreenWith(['corex-captcha/corex-captcha.php']);

    saveAs($screen, postedForm($screen, ['brand_company_name' => 'Acme Trading']));

    expect($this->settings->get('brand.company_name'))->toBe('Acme Trading')
        ->and($this->settings->get('media.webp.enabled'))->toBe('1')
        ->and($this->settings->get('media.webp.convert_jpeg'))->toBe('1')
        ->and($this->settings->get('media.webp.convert_png'))->toBe('1');

    // And Corex Media turned back on draws them as they were.
    ob_start();
    settingsScreenWith(['corex-media/corex-media.php', 'corex-captcha/corex-captcha.php'])->renderSettings();
    $html = (string) ob_get_clean();
    expect($html)->toMatch('/id="media_webp_enabled" name="media_webp_enabled" type="checkbox" value="1" checked/');
});

it('keeps the captcha settings as stored when Mail is saved while Corex Captcha is off', function () {
    $this->settings->save('captcha.driver', 'recaptcha');
    $this->settings->save('captcha.site_key', 'site-key-kept');
    $this->settings->save('captcha.score_threshold', '0.5');
    $screen = settingsScreenWith(['corex-media/corex-media.php']);

    saveAs($screen, postedForm($screen, ['mail_from_name' => 'Acme Sales']));

    expect($this->settings->get('mail.from.name'))->toBe('Acme Sales')
        ->and($this->settings->get('captcha.driver'))->toBe('recaptcha')
        ->and($this->settings->get('captcha.site_key'))->toBe('site-key-kept')
        ->and($this->settings->get('captcha.score_threshold'))->toBe('0.5');
});

it('writes nothing when the form is saved as it was drawn', function () {
    $screen  = settingsScreenWith(['corex-media/corex-media.php', 'corex-captcha/corex-captcha.php']);
    $options = static fn (): array => array_map(
        static fn (string $key) => get_option(Boot::app()->container()->make(SettingsStore::class)->optionName($key), null),
        Boot::app()->container()->make(SettingsRegistry::class)->keys(),
    );
    delete_option($this->settings->optionName('media.webp.enabled'));
    $before = $options();

    saveAs($screen, postedForm($screen, []));

    // The Media switch drawn as on while nothing was stored stays unstored, so its default applies.
    expect($options())->toBe($before);
});

it('saves a switch turned off on the tab being saved as off', function () {
    $this->settings->save('media.webp.enabled', '1');
    $screen = settingsScreenWith(['corex-media/corex-media.php', 'corex-captcha/corex-captcha.php']);

    saveAs($screen, postedForm($screen, ['media_webp_enabled' => '']));

    expect($this->settings->get('media.webp.enabled'))->toBe('0');
});

it('refuses a value its field does not take and changes nothing else', function () {
    $this->settings->save('mail.reply.from_address', 'before@example.com');
    $this->settings->save('media.webp.convert_png', '1');
    $screen = settingsScreenWith(['corex-captcha/corex-captcha.php']);

    saveAs($screen, postedForm($screen, ['mail_reply_from_address' => 'answers@']));
    ob_start();
    $screen->renderSettings();
    $page = (string) ob_get_clean();

    expect($this->settings->get('mail.reply.from_address'))->toBe('before@example.com')
        ->and($this->settings->get('media.webp.convert_png'))->toBe('1')
        ->and($page)->toContain('Reply sender address was not changed: ');
});
