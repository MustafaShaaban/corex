<?php

/**
 * A setting fixed in the site's `.env` or by the network shows its real value and cannot be edited
 * (spec 119, US-SET-02).
 *
 * The screen read and wrote the site option while CoreX used `.env` first: a key there showed an
 * empty, editable field whose saves did nothing, and the captcha section reported keys from `.env`
 * as missing. On real WordPress, with a `.env` of the test's own.
 *
 * @package Corex\Tests\Integration\Config
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Settings\ProviderSetup;
use Corex\Config\Settings\SettingsDiagnostics;
use Corex\Config\Settings\SettingsFieldValues;
use Corex\Config\Settings\SettingsPageRenderer;
use Corex\Config\Settings\SettingsPages;
use Corex\Config\Settings\SettingsSaver;
use Corex\Config\Settings\SettingsSectionStates;
use Corex\Config\Settings\SettingsStore;
use Corex\Support\BootLogger;
use Corex\Support\Config\Repository;
use Corex\Support\Config\SettingLocks;
use Corex\Support\Config\Source;
use Corex\Support\Config\Sources\DotenvSource;
use Corex\Support\Config\Sources\OptionsSource;
use Corex\Tests\Support\SettingsScreen;

beforeEach(function () {
    $this->signedInAs = get_current_user_id();
    $administrators   = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));

    $this->store = Boot::app()->container()->make(SettingsStore::class);
    $this->keys  = ['brand.company_name', 'brand.tagline', 'captcha.driver', 'captcha.site_key', 'captcha.secret'];
    $this->was   = [];
    foreach ($this->keys as $key) {
        $this->was[$key] = get_option($this->store->optionName($key), null);
    }

    $this->dir = sys_get_temp_dir() . '/corex-env-' . uniqid();
    mkdir($this->dir);
});

afterEach(function () {
    foreach ($this->was as $key => $value) {
        $value === null ? $this->store->delete($key) : update_option($this->store->optionName($key), $value);
    }
    @unlink($this->dir . '/.env');
    @rmdir($this->dir);
    wp_set_current_user($this->signedInAs);
});

/** @param array<string,string> $values */
function networkLocking(array $values): Source
{
    return new class ($values) implements Source {
        /** @param array<string,string> $values */
        public function __construct(private readonly array $values)
        {
        }

        public function has(string $key): bool
        {
            return isset($this->values[$key]);
        }

        public function get(string $key): mixed
        {
            return $this->values[$key] ?? null;
        }
    };
}

/**
 * The page's renderer and saver, reading `.env` from the test's folder and the given network locks,
 * as the config engine reads them.
 *
 * @param array<string,string> $network
 *
 * @return array{renderer:SettingsPageRenderer,saver:SettingsSaver}
 */
function settingsWithEnv(string $dir, string $env, array $network = []): array
{
    file_put_contents($dir . '/.env', $env);
    $container = Boot::app()->container();
    $dotenv    = new DotenvSource($dir, $container->make(BootLogger::class));
    $locks     = new SettingLocks($dotenv, networkLocking($network));
    $config    = new Repository([$dotenv, networkLocking($network), new OptionsSource()]);
    $values    = new SettingsFieldValues(
        $container->make(SettingsStore::class),
        $locks,
        $container->make(SettingsDiagnostics::class),
    );
    $providers = new ProviderSetup($config);
    $states    = new SettingsSectionStates(
        $container->make(SettingsPages::class),
        SettingsScreen::inspector(SettingsScreen::ALL_ADDONS),
        $providers,
    );
    $services  = ['values' => $values, 'states' => $states];

    return [
        'renderer' => $container->make(SettingsPageRenderer::class, $services + ['providers' => $providers]),
        'saver'    => $container->make(SettingsSaver::class, $services),
    ];
}

function drawWith(SettingsPageRenderer $renderer, string $page): string
{
    $pages = Boot::app()->container()->make(SettingsPages::class);

    return $renderer->render($pages->find($page), $pages->url($page), '', null);
}

it('shows a value set in .env, read-only, and says where it is set', function () {
    $this->store->save('brand.company_name', 'Old');
    ['renderer' => $renderer] = settingsWithEnv($this->dir, "BRAND_COMPANY_NAME=Acme\n");

    $page = drawWith($renderer, 'general');

    expect($page)->toContain('name="brand_company_name" type="text" value="Acme"')
        ->and($page)->toMatch('/name="brand_company_name"[^>]*readonly/')
        ->and($page)->toContain('Set in the site&#039;s .env file');
});

it('leaves a field set in .env untouched when its page is saved, and does not report it', function () {
    $this->store->save('brand.company_name', 'Old');
    ['renderer' => $renderer, 'saver' => $saver] = settingsWithEnv($this->dir, "BRAND_COMPANY_NAME=Acme\n");

    $posted = SettingsScreen::posted(drawWith($renderer, 'general'), ['brand_company_name' => 'Typed anyway', 'brand_tagline' => 'Changed']);
    $result = $saver->save('general', $posted, get_current_user_id());

    expect($this->store->get('brand.company_name'))->toBe('Old')
        ->and($this->store->get('brand.tagline'))->toBe('Changed')
        ->and($result?->refused)->toBe([]);
});

it('counts captcha keys set in .env, and never draws the secret', function () {
    $this->store->save('captcha.driver', 'recaptcha');
    $this->store->delete('captcha.site_key');
    $this->store->delete('captcha.secret');
    ['renderer' => $renderer] = settingsWithEnv($this->dir, "CAPTCHA_SITE_KEY=env-site-key\nCAPTCHA_SECRET=env-secret-never-shown\n");

    $page = drawWith($renderer, 'captcha');

    expect($page)->not->toContain('is missing')
        ->and($page)->toContain('Set in .env')
        ->and($page)->not->toContain('env-secret-never-shown')
        ->and($page)->not->toContain('corex_remove_secret');
});

it('shows a value the network locks, read-only, and says who sets it', function () {
    $this->store->save('brand.tagline', 'Site');
    ['renderer' => $renderer] = settingsWithEnv($this->dir, '', ['brand.tagline' => 'Net']);

    $page = drawWith($renderer, 'general');

    expect($page)->toContain('name="brand_tagline" type="text" value="Net"')
        ->and($page)->toContain('Locked by the network administrator');
});

it('makes the field editable again, with the site\'s own value, once the key leaves .env', function () {
    $this->store->save('brand.company_name', 'Old');
    ['renderer' => $renderer] = settingsWithEnv($this->dir, "APP_NAME=Corex\n");

    $page = drawWith($renderer, 'general');

    expect($page)->toContain('name="brand_company_name" type="text" value="Old"')
        ->and($page)->not->toMatch('/name="brand_company_name"[^>]*readonly/');
});
