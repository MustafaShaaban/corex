<?php

/**
 * Unit tests for the kit apply boundary (spec 041): adopt-and-populate an empty home + set the front page,
 * skip user content, record dispositions, return an ApplyOutcome. WP functions stubbed via Brain Monkey.
 *
 * @package Corex\Tests\Unit\Kit
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Kit\BlueprintActivator;
use Corex\Multisite\SingleSiteMultisiteContext;
use Corex\Multisite\WpPluginActivationInspector;

/** @return list<array{title:string,slug:string,content:string,front?:bool}> */
function kitPages(): array
{
    return [
        ['title' => 'Home', 'slug' => 'home', 'content' => '<!-- wp:corex/hero /-->', 'front' => true],
        ['title' => 'About', 'slug' => 'about', 'content' => '<!-- wp:corex/features /-->'],
    ];
}

beforeEach(function () {
    Functions\when('sanitize_key')->returnArg();
    Functions\when('get_option')->justReturn([]);
    Functions\when('update_post_meta')->justReturn(true);
    Functions\when('get_post_meta')->justReturn('');
});

it('creates absent pages and sets the front page to the home', function () {
    Functions\when('get_page_by_path')->justReturn(null); // nothing exists → all create
    Functions\when('get_post_field')->justReturn('');
    $ids = [101, 102];
    Functions\when('wp_insert_post')->alias(function () use (&$ids) {
        return array_shift($ids);
    });

    $front = null;
    Functions\when('update_option')->alias(function (string $key, $value) use (&$front): bool {
        if ($key === 'page_on_front') {
            $front = (int) $value;
        }

        return true;
    });

    $outcome = (new BlueprintActivator(
        new WpPluginActivationInspector(new SingleSiteMultisiteContext()),
    ))->seedPages(kitPages());

    expect($outcome->created())->toHaveCount(2)
        ->and($outcome->frontPageId)->toBe(101)
        ->and($front)->toBe(101);
});

it('adopts and populates a pre-existing empty home, and sets it as the front page', function () {
    Functions\when('get_page_by_path')->alias(fn (string $slug) => $slug === 'home'
        ? new WP_Post(['ID' => 2511])
        : null);
    Functions\when('get_post_field')->justReturn(''); // existing home is blank → adopt
    Functions\when('get_post_meta')->justReturn('');   // no kit meta → user's empty page

    $updated = [];
    Functions\when('wp_update_post')->alias(function (array $data) use (&$updated): int {
        $updated[] = $data;

        return (int) $data['ID'];
    });
    Functions\when('wp_insert_post')->justReturn(900); // for 'about'
    $front = null;
    Functions\when('update_option')->alias(function (string $key, $value) use (&$front): bool {
        if ($key === 'page_on_front') {
            $front = (int) $value;
        }

        return true;
    });

    $outcome = (new BlueprintActivator(
        new WpPluginActivationInspector(new SingleSiteMultisiteContext()),
    ))->seedPages(kitPages());

    expect($outcome->populated())->toHaveCount(1)
        ->and($outcome->frontPageId)->toBe(2511)
        ->and($front)->toBe(2511)
        ->and($updated[0]['ID'])->toBe(2511)
        ->and($updated[0]['post_content'])->toBe('<!-- wp:corex/hero /-->');
});

it('skips a home that already has user content and does not change the front page', function () {
    Functions\when('get_page_by_path')->alias(fn (string $slug) => $slug === 'home'
        ? new WP_Post(['ID' => 2511])
        : null);
    Functions\when('get_post_field')->justReturn('<!-- wp:paragraph --><p>My homepage</p><!-- /wp:paragraph -->');
    Functions\when('wp_insert_post')->justReturn(900);

    $frontSet = false;
    Functions\when('update_option')->alias(function (string $key) use (&$frontSet): bool {
        if ($key === 'page_on_front') {
            $frontSet = true;
        }

        return true;
    });
    Functions\when('wp_update_post')->justReturn(0);

    $outcome = (new BlueprintActivator(
        new WpPluginActivationInspector(new SingleSiteMultisiteContext()),
    ))->seedPages(kitPages());

    expect($outcome->skipped())->toHaveCount(1)
        ->and($outcome->frontPageId)->toBeNull()
        ->and($frontSet)->toBeFalse();
});

it('records what an adopted page and the reading settings were before changing them, once', function () {
    Functions\when('get_page_by_path')->alias(fn (string $slug) => $slug === 'home'
        ? new WP_Post(['ID' => 2511, 'post_title' => 'Welcome', 'post_content' => '', 'post_status' => 'publish'])
        : null);
    Functions\when('get_post_field')->justReturn('');
    Functions\when('wp_update_post')->justReturn(2511);
    Functions\when('wp_insert_post')->justReturn(900);
    Functions\when('wp_slash')->returnArg();
    Functions\when('get_option')->alias(fn (string $key, $default = false) => match ($key) {
        'show_on_front' => 'page',
        'page_on_front' => '77',
        'page_for_posts' => '0',
        default => $default,
    });
    Functions\when('get_post_meta')->alias(fn (int $id, string $key) => $key === '_wp_page_template' ? 'page-wide' : '');

    $meta = [];
    Functions\when('update_post_meta')->alias(function (int $id, string $key, $value) use (&$meta): bool {
        $meta[$key] = $value;

        return true;
    });
    $options = [];
    Functions\when('update_option')->alias(function (string $key, $value) use (&$options): bool {
        $options[$key] = $value;

        return true;
    });

    (new BlueprintActivator(new WpPluginActivationInspector(new SingleSiteMultisiteContext())))->seedPages(kitPages());

    expect($meta['_corex_kit_before'])->toBe(['title' => 'Welcome', 'content' => '', 'template' => 'page-wide', 'status' => 'publish'])
        ->and($options['corex_kit_reading_before'])->toBe(['show_on_front' => 'page', 'page_on_front' => '77', 'page_for_posts' => '0']);
});

it('does not record again what an earlier apply recorded', function () {
    $record = ['title' => 'Welcome', 'content' => '', 'template' => '', 'status' => 'publish'];
    Functions\when('get_page_by_path')->alias(fn (string $slug) => $slug === 'home'
        ? new WP_Post(['ID' => 2511, 'post_title' => 'Kit home', 'post_content' => '', 'post_status' => 'publish'])
        : null);
    Functions\when('get_post_field')->justReturn('');
    Functions\when('wp_update_post')->justReturn(2511);
    Functions\when('wp_insert_post')->justReturn(900);
    Functions\when('get_option')->alias(fn (string $key, $default = false) => $key === 'corex_kit_reading_before'
        ? ['show_on_front' => 'posts', 'page_on_front' => '0', 'page_for_posts' => '0']
        : $default);
    Functions\when('get_post_meta')->alias(fn (int $id, string $key) => $key === '_corex_kit_before' ? $record : '');

    $written = [];
    Functions\when('update_post_meta')->alias(function (int $id, string $key) use (&$written): bool {
        $written[] = $key;

        return true;
    });
    Functions\when('update_option')->alias(function (string $key) use (&$written): bool {
        $written[] = $key;

        return true;
    });

    (new BlueprintActivator(new WpPluginActivationInspector(new SingleSiteMultisiteContext())))->seedPages(kitPages());

    expect($written)->not->toContain('_corex_kit_before')
        ->and($written)->not->toContain('corex_kit_reading_before');
});
