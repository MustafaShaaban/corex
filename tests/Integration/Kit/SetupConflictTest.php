<?php

/**
 * Integration test: the setup conflict resolution on real ./wp (spec 068: T194, FR-139/140/143).
 * A page holding user content is only ever overwritten from an explicit Replace choice; a Suffix
 * choice leaves the original untouched and creates the kit page under a fresh slug.
 *
 * @package Corex\Tests\Integration\Kit
 */

declare(strict_types=1);

use Corex\Kit\BlueprintActivator;
use Corex\Multisite\WpMultisiteContext;
use Corex\Multisite\WpPluginActivationInspector;
use Corex\Provisioning\PageDisposition;

function conflictPageInput(string $slug): array
{
    return [[
        'title'   => 'Conflict Fixture',
        'slug'    => $slug,
        'content' => '<!-- wp:paragraph --><p>KIT CONTENT</p><!-- /wp:paragraph -->',
    ]];
}

beforeEach(function () {
    // `seedPages()` records every page it touches in this option, which is what a kit reset reads
    // to decide what to remove. The raw value, so "it did not exist" can be put back as well.
    $this->seededPagesBefore = get_option(BlueprintActivator::SEEDED_OPTION, null);
});

afterEach(function () {
    foreach (['corex-conflict-fixture', 'corex-conflict-fixture-2'] as $slug) {
        $existing = get_page_by_path($slug);
        if ($existing instanceof WP_Post) {
            wp_delete_post($existing->ID, true);
        }
    }

    // The pages are gone; their ids must not stay on the install's list of kit-seeded pages. Each
    // run used to add two, naming pages that no longer existed.
    if ($this->seededPagesBefore === null) {
        delete_option(BlueprintActivator::SEEDED_OPTION);
    } else {
        update_option(BlueprintActivator::SEEDED_OPTION, $this->seededPagesBefore);
    }
});

it('replaces existing content only from an explicit choice and records it (FR-139/143)', function () {
    $slug = 'corex-conflict-fixture';
    $original = wp_insert_post([
        'post_title'   => 'My Page',
        'post_name'    => $slug,
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_content' => 'ORIGINAL USER CONTENT',
    ]);

    $activator = new BlueprintActivator(new WpPluginActivationInspector(new WpMultisiteContext()));

    // With no choice, the conflicting page is kept untouched (never silently overwritten).
    $activator->seedPages(conflictPageInput($slug));
    expect(get_post_field('post_content', $original))->toBe('ORIGINAL USER CONTENT');

    // With an explicit Replace choice, the content is replaced and marked replaced.
    $activator->seedPages(conflictPageInput($slug), [], [], [$slug => 'replace']);
    expect(get_post_field('post_content', $original))->toContain('KIT CONTENT')
        ->and(get_post_meta($original, '_corex_kit_page', true))->toBe(PageDisposition::PERSISTED_REPLACED);
});

it('creates a suffixed page and leaves the original untouched on a Suffix choice (FR-139)', function () {
    $slug = 'corex-conflict-fixture';
    $original = wp_insert_post([
        'post_title'   => 'My Page',
        'post_name'    => $slug,
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_content' => 'ORIGINAL USER CONTENT',
    ]);

    (new BlueprintActivator(
        new WpPluginActivationInspector(new WpMultisiteContext()),
    ))->seedPages(conflictPageInput($slug), [], [], [$slug => 'suffix']);

    $suffixed = get_page_by_path($slug . '-2');

    expect(get_post_field('post_content', $original))->toBe('ORIGINAL USER CONTENT')
        ->and($suffixed)->toBeInstanceOf(WP_Post::class)
        ->and(get_post_field('post_content', $suffixed->ID))->toContain('KIT CONTENT')
        ->and(get_post_meta($suffixed->ID, '_corex_kit_page', true))->toBe(PageDisposition::PERSISTED_SUFFIXED);
});
