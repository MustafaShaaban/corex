<?php

/**
 * The company kit is a manifest that composes existing presentation. These tests keep
 * the manifest honest: every template/part it declares must exist in the theme, and
 * every pattern it composes must be one the UI library actually provides — so the kit
 * can't drift away from the files it points at.
 *
 * @package Corex\Tests\Unit\Kit
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Kit\Company\CompanyBlueprint;
use Corex\Ui\Patterns\PatternLibrary;

function kitThemeDir(): string
{
    return dirname(__DIR__, 3) . '/theme';
}

it('declares only templates that exist in the theme', function () {
    foreach ((new CompanyBlueprint())->templates() as $template) {
        expect(is_file(kitThemeDir() . "/templates/{$template}.html"))
            ->toBeTrue("template {$template}.html should exist");
    }
});

it('declares only parts that exist in the theme', function () {
    foreach ((new CompanyBlueprint())->parts() as $part) {
        expect(is_file(kitThemeDir() . "/parts/{$part}.html"))
            ->toBeTrue("part {$part}.html should exist");
    }
});

it('composes only patterns the UI library provides', function () {
    Functions\when('__')->returnArg();
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_attr__')->returnArg();
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_attr')->returnArg();
    Functions\when('esc_url')->returnArg();

    $available = array_column((new PatternLibrary())->patterns(), 'name');

    foreach ((new CompanyBlueprint())->patterns() as $pattern) {
        expect($available)->toContain($pattern);
    }
});
