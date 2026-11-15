<?php

/**
 * Integration test: WordPress's own font sizes and shadows come off the CoreX theme only (spec 121,
 * US-BRD-03, FR-007, DECISIONS #462).
 *
 * A client's own theme is never touched, whatever its theme.json says: its content may use
 * WordPress's sizes. The active theme is changed for one call through WordPress's `template`
 * filter, so the site's own theme is never switched.
 *
 * @package Corex\Tests\Integration\Theme
 */

declare(strict_types=1);

use Corex\Theme\ThemeDefaultPresets;

/** WordPress's default layer as `wp_theme_json_data_default` hands it over. */
function theme_default_presets_layer(): WP_Theme_JSON_Data
{
    return new WP_Theme_JSON_Data([
        'version' => 3,
        'settings' => [
            'typography' => ['fontSizes' => [['slug' => 'large', 'size' => '36px', 'name' => 'Large']]],
            'shadow' => ['presets' => [['slug' => 'natural', 'shadow' => '6px 6px 9px rgba(0, 0, 0, 0.2)', 'name' => 'Natural']]],
        ],
    ], 'default');
}

/** The filter's answer while `get_template()` names this theme. */
function theme_default_presets_for(string $template): array
{
    $as = static fn (): string => $template;
    add_filter('template', $as, 99);

    try {
        $layer = theme_default_presets_layer();
        $answer = (new ThemeDefaultPresets())->filter($layer);

        return ['same' => $answer === $layer, 'settings' => $answer->get_data()['settings'] ?? []];
    } finally {
        remove_filter('template', $as, 99);
    }
}

it('leaves a client theme\'s default font sizes and shadows untouched', function () {
    $answer = theme_default_presets_for('client-theme');

    expect($answer['same'])->toBeTrue()
        ->and($answer['settings']['typography']['fontSizes'] ?? [])->not->toBe([])
        ->and($answer['settings']['shadow']['presets'] ?? [])->not->toBe([]);
});

it('removes WordPress\'s default font sizes and shadows while the CoreX theme draws the page', function () {
    $answer = theme_default_presets_for(ThemeDefaultPresets::THEME);

    expect($answer['same'])->toBeFalse()
        ->and($answer['settings']['typography']['fontSizes'] ?? null)->toBeNull()
        ->and($answer['settings']['shadow']['presets'] ?? null)->toBeNull();
});
