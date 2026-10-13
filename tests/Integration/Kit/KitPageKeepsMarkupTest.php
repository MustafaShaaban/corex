<?php

/**
 * A kit's page is stored with the markup the kit gave it.
 *
 * WordPress takes one level of slashes off a post's title and content on their way in, and a
 * kit's page was handed over as it was. A block's attributes are JSON, in which WordPress
 * writes `&` as `\u0026` and a quote as `\u0022`: stored without the backslash, the attribute
 * `"Tom \u0026 Jerry"` read `"Tom u0026 Jerry"` (measured 2026-10-13, DECISIONS #332). The
 * pages CoreX ships name a pattern each and hold no backslash, so none of them was affected;
 * a kit of a site's own with a block's attributes in a page was.
 *
 * On real WordPress, through each of the four ways a page is stored.
 *
 * @package Corex\Tests\Integration\Kit
 */

declare(strict_types=1);

use Corex\Kit\BlueprintActivator;
use Corex\Multisite\WpMultisiteContext;
use Corex\Multisite\WpPluginActivationInspector;
use Corex\Tests\Support\CreatedPosts;

const KEPT_KIT_SLUG = 'corex-kept-markup-fixture';

/** What `serialize_blocks()` writes for a heading of `Tom & Jerry's "offer"` and a path. */
const KEPT_KIT_CONTENT = '<!-- wp:corex/hero {"heading":"Tom \\u0026 Jerry\'s \\u0022offer\\u0022","path":"C:\\\\offers\\\\new"} /-->';

/** @return list<array{title:string,slug:string,content:string}> */
function keptKitPage(): array
{
    return [['title' => 'Research \\ Development', 'slug' => KEPT_KIT_SLUG, 'content' => KEPT_KIT_CONTENT]];
}

function keptKitExistingPage(string $content): int
{
    return (int) wp_insert_post([
        'post_title' => 'A page that was there',
        'post_name' => KEPT_KIT_SLUG,
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_content' => $content,
    ]);
}

beforeEach(function () {
    // `seedPages()` records every page it touches in this option, which a kit reset reads.
    $this->seededPagesBefore = get_option(BlueprintActivator::SEEDED_OPTION, null);
    $this->pages = CreatedPosts::watch('page');
    $this->activator = new BlueprintActivator(new WpPluginActivationInspector(new WpMultisiteContext()));
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));
});

afterEach(function () {
    $this->pages->delete();
    if ($this->seededPagesBefore === null) {
        delete_option(BlueprintActivator::SEEDED_OPTION);
    } else {
        update_option(BlueprintActivator::SEEDED_OPTION, $this->seededPagesBefore);
    }
});

it('creates a page with the title and the markup the kit gave it', function () {
    $this->activator->seedPages(keptKitPage());
    $page = get_page_by_path(KEPT_KIT_SLUG);

    expect($page->post_title)->toBe('Research \\ Development')
        ->and($page->post_content)->toBe(KEPT_KIT_CONTENT)
        // What the block is handed when the page is drawn.
        ->and(parse_blocks($page->post_content)[0]['attrs'])->toBe([
            'heading' => 'Tom & Jerry\'s "offer"',
            'path' => 'C:\\offers\\new',
        ]);
});

it('fills an empty page that was there with the markup the kit gave it', function () {
    $existing = keptKitExistingPage('');

    $this->activator->seedPages(keptKitPage());

    expect(get_post_field('post_content', $existing, 'raw'))->toBe(KEPT_KIT_CONTENT);
});

it('replaces a page somebody chose to replace with the markup the kit gave it', function () {
    $existing = keptKitExistingPage('What the site wrote.');

    $this->activator->seedPages(keptKitPage(), [], [], [KEPT_KIT_SLUG => 'replace']);

    expect(get_post_field('post_content', $existing, 'raw'))->toBe(KEPT_KIT_CONTENT);
});

it('creates a page beside one somebody chose to keep, with the markup the kit gave it', function () {
    $existing = keptKitExistingPage('What the site wrote.');

    $this->activator->seedPages(keptKitPage(), [], [], [KEPT_KIT_SLUG => 'suffix']);
    $beside = array_values(array_diff($this->pages->ids(), [$existing]));

    expect($beside)->toHaveCount(1)
        ->and(get_post_field('post_title', $beside[0], 'raw'))->toBe('Research \\ Development')
        ->and(get_post_field('post_content', $beside[0], 'raw'))->toBe(KEPT_KIT_CONTENT)
        ->and(get_post_field('post_content', $existing, 'raw'))->toBe('What the site wrote.');
});
