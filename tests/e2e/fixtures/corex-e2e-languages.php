<?php

/**
 * Plugin Name: CoreX E2E languages (test fixture)
 * Description: Test fixture. Gives one request two or three languages, through a language source bound in the CoreX container.
 * Version: 1.0.0
 *
 * @package Corex\Tests\E2E
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * The browser checks of the language switcher (spec 137, US-NAV-05, SC-004, SC-010:
 * `tests/e2e/language-switcher.spec.js`).
 *
 * WordPress has one language, so the shipped source makes the block print nothing. A request with the
 * cookie `corex_e2e_languages` binds another `Corex\Blocks\Languages\LanguageSource` for that request
 * only, as a site or spec 148 would (FR-029):
 *
 * - `two`: English and Arabic;
 * - `three`: English, Arabic and French.
 *
 * The current language follows the request's locale, so with the `corex_e2e_locale=ar` cookie of
 * `corex-e2e-arabic.php` Arabic is current. Each language's address is the home page with a query
 * naming it, so a test can tell which address it was sent to.
 *
 * Copied into `wp-content/mu-plugins/` with the other `corex-e2e-*.php` fixtures, by the CI jobs
 * and by `scripts/setup-wordpress.ps1`.
 */
$corexE2eLanguages = (string) ($_COOKIE['corex_e2e_languages'] ?? '');

if (! in_array($corexE2eLanguages, ['two', 'three'], true)) {
    return;
}

add_action('plugins_loaded', static function () use ($corexE2eLanguages): void {
    if (! class_exists(\Corex\Support\Facades\Corex::class)) {
        return;
    }

    \Corex\Support\Facades\Corex::onReady(static function ($app) use ($corexE2eLanguages): void {
        $app->container()->instance(
            \Corex\Blocks\Languages\LanguageSource::class,
            new class ($corexE2eLanguages === 'three') implements \Corex\Blocks\Languages\LanguageSource {
                public function __construct(private readonly bool $withFrench)
                {
                }

                public function languages(): array
                {
                    $current = str_starts_with(determine_locale(), 'ar') ? 'ar' : 'en';
                    $names = ['en' => ['English', 'ltr'], 'ar' => ['العربية', 'rtl']];
                    if ($this->withFrench) {
                        $names['fr'] = ['Français', 'ltr'];
                    }

                    $languages = [];
                    foreach ($names as $code => [$name, $direction]) {
                        $languages[] = new \Corex\Blocks\Languages\Language(
                            $code,
                            $name,
                            $direction,
                            home_url('/?corex_e2e_language=' . $code),
                            $code === $current,
                        );
                    }

                    return $languages;
                }
            },
        );
    });
}, 20);
