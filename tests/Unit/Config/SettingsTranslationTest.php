<?php

/**
 * Every word on the Settings screen can be translated (spec 119, US-SET-08, FR-030).
 *
 * `__()` is stubbed to mark what it is given, so a title, label, help line, link text, choice or
 * page name that did not go through it arrives unmarked and is named here. Brand names stay as
 * they are in every language.
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Settings\SettingsPages;
use Corex\Config\Settings\SettingsRegistry;

/** Names no catalogue translates. */
const COREX_SETTINGS_BRAND_NAMES = ['reCAPTCHA', 'Cloudflare Turnstile', 'hCaptcha'];

beforeEach(function () {
    Functions\when('__')->alias(static fn (string $text): string => '⟦' . $text . '⟧');
});

/** @return list<string> every text a person reads that is not marked */
function untranslatedSettingsText(): array
{
    $marked    = static fn (string $text): bool => str_starts_with($text, '⟦') && str_ends_with($text, '⟧');
    $unmarked  = [];
    $check     = static function (string $where, mixed $text) use (&$unmarked, $marked): void {
        if (is_string($text) && ! $marked($text) && ! in_array($text, COREX_SETTINGS_BRAND_NAMES, true)) {
            $unmarked[] = $where . ': ' . $text;
        }
    };

    foreach ((new SettingsRegistry())->sections() as $sectionKey => $section) {
        $check($sectionKey . ' title', $section['title']);

        foreach ($section['fields'] as $key => $field) {
            foreach (['label', 'help', 'help_link', 'remove_effect'] as $part) {
                if (isset($field[$part])) {
                    $check($key . ' ' . $part, $field[$part]);
                }
            }
            foreach ($field['options'] ?? [] as $value => $choice) {
                $check($key . ' choice ' . $value, $choice);
            }
            foreach ($field['help_variants'] ?? [] as $variant => $help) {
                foreach (['help', 'help_link'] as $part) {
                    if (isset($help[$part])) {
                        $check($key . ' ' . $variant . ' ' . $part, $help[$part]);
                    }
                }
            }
        }
    }

    foreach ((new SettingsPages())->all() as $page) {
        $check($page->key . ' page title', $page->title);
        $check($page->key . ' page description', $page->description);
        if ($page->addon !== null) {
            $check($page->key . ' add-on name', $page->addon->name);
        }
    }

    return $unmarked;
}

it('sends every Settings title, label, help line, link text, choice and page name through translation', function () {
    // Reported as one list, so whoever adds a label without `__()` sees every one at once.
    expect(implode("\n", untranslatedSettingsText()))->toBe('');
});

it('would fail on a label added without translation', function () {
    // The check above is only as good as its ability to fail: an unmarked label is reported.
    Functions\when('__')->returnArg();

    expect(untranslatedSettingsText())->toContain('brand.company_name label: Company name');
});
