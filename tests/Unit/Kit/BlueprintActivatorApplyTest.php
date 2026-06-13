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

    $outcome = (new BlueprintActivator())->seedPages(kitPages());

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

    $outcome = (new BlueprintActivator())->seedPages(kitPages());

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

    $outcome = (new BlueprintActivator())->seedPages(kitPages());

    expect($outcome->skipped())->toHaveCount(1)
        ->and($outcome->frontPageId)->toBeNull()
        ->and($frontSet)->toBeFalse();
});
