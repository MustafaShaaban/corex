<?php

/**
 * Integration test: applying a kit and then resetting it gives the owner's pages back (spec 115,
 * US-SAFE-07). On real ./wp: a page the owner had, replaced or adopted by the kit, comes back with
 * the same ID, title, content, template and status; the front page is the owner's again; a page the
 * kit created from nothing is removed; a replaced page with no record is kept.
 *
 * @package Corex\Tests\Integration\Kit
 */

declare(strict_types=1);

use Corex\Cli\Reset\KitPageReader;
use Corex\Cli\Reset\ResetAction;
use Corex\Cli\Reset\ResetExecutor;
use Corex\Cli\Reset\ResetInventory;
use Corex\Cli\Reset\ResetPlanner;
use Corex\Cli\Reset\ResetRequest;
use Corex\Kit\BlueprintActivator;
use Corex\Multisite\WpMultisiteContext;
use Corex\Multisite\WpPluginActivationInspector;
use Corex\Provisioning\PageDisposition;
use Corex\Provisioning\PageRecord;

const KIT_RESET_SLUGS = ['corex-reset-about', 'corex-reset-contact', 'corex-reset-new'];

function kitResetPlan(): array
{
    return [
        ['title' => 'Kit About', 'slug' => 'corex-reset-about', 'content' => '<!-- wp:paragraph --><p>KIT ABOUT</p><!-- /wp:paragraph -->'],
        ['title' => 'Kit Contact', 'slug' => 'corex-reset-contact', 'content' => '<!-- wp:paragraph --><p>KIT CONTACT</p><!-- /wp:paragraph -->'],
        ['title' => 'Kit New', 'slug' => 'corex-reset-new', 'content' => '<!-- wp:paragraph --><p>KIT NEW</p><!-- /wp:paragraph -->', 'front' => true],
    ];
}

/** Run a soft reset of the kit's pages and reading settings, exactly as `wp corex reset` plans them. */
function resetKitPages(): array
{
    $reader    = new KitPageReader();
    $inventory = new ResetInventory(pages: $reader->pages(), hasReadingRecord: $reader->hasReadingRecord());
    $plan      = (new ResetPlanner())->plan(new ResetRequest(ResetRequest::SOFT), $inventory);
    $executor  = new ResetExecutor();

    foreach ($plan->actions as $action) {
        $executor->apply($action);
    }

    return $plan->actions;
}

beforeEach(function () {
    $this->saved = [];
    foreach ([BlueprintActivator::SEEDED_OPTION, PageRecord::READING_OPTION, ...PageRecord::READING_KEYS] as $key) {
        $this->saved[$key] = get_option($key, null);
    }
    delete_option(BlueprintActivator::SEEDED_OPTION);
    delete_option(PageRecord::READING_OPTION);

    // The owner's page as an administrator saves it: unfiltered, so an embed stays.
    $this->made  = [];
    $made        = &$this->made;
    $this->owner = static function (string $slug, string $content, string $status = 'publish') use (&$made): int {
        $filtered = has_filter('content_save_pre', 'wp_filter_post_kses') !== false;
        kses_remove_filters();
        try {
            return $made[] = wp_insert_post([
                'post_title'   => 'Owner ' . $slug,
                'post_name'    => $slug,
                'post_status'  => $status,
                'post_type'    => 'page',
                'post_content' => $content,
            ]);
        } finally {
            if ($filtered) {
                kses_init_filters();
            }
        }
    };
});

afterEach(function () {
    foreach ($this->made as $id) {
        wp_delete_post($id, true);
    }
    foreach (KIT_RESET_SLUGS as $slug) {
        foreach (get_posts(['post_type' => 'page', 'name' => $slug, 'post_status' => 'any', 'numberposts' => -1]) as $post) {
            wp_delete_post($post->ID, true);
        }
    }

    foreach ($this->saved as $key => $value) {
        $value === null ? delete_option($key) : update_option($key, $value);
    }
});

it('restores a replaced and an adopted page, removes what the kit created, and puts the front page back', function () {
    // A map embed: WordPress strips an iframe from a post saved by somebody without unfiltered HTML,
    // which a reset run from WP-CLI is.
    $owned   = '<!-- wp:paragraph --><p>OWNER ABOUT</p><!-- /wp:paragraph --><!-- wp:html --><iframe src="https://maps.example.org/embed" title="Map"></iframe><!-- /wp:html -->';
    $about   = ($this->owner)('corex-reset-about', $owned);
    $contact = ($this->owner)('corex-reset-contact', '', 'draft');
    update_post_meta($about, '_wp_page_template', 'page-wide');
    update_option('show_on_front', 'page');
    update_option('page_on_front', $about);

    (new BlueprintActivator(new WpPluginActivationInspector(new WpMultisiteContext())))
        ->seedPages(kitResetPlan(), [], [], ['corex-reset-about' => 'replace']);

    expect(get_post_meta($about, '_corex_kit_page', true))->toBe(PageDisposition::PERSISTED_REPLACED)
        ->and(get_post_meta($contact, '_corex_kit_page', true))->toBe(PageDisposition::PERSISTED_ADOPTED)
        ->and(get_post_field('post_content', $about))->toContain('KIT ABOUT');
    $created = get_page_by_path('corex-reset-new');
    expect((int) get_option('page_on_front'))->toBe($created->ID);

    // As for a person without unfiltered HTML: WordPress filters what is saved.
    $filteredBefore = has_filter('content_save_pre', 'wp_filter_post_kses') !== false;
    kses_init_filters();
    try {
        $actions = resetKitPages();
        $stillFiltered = has_filter('content_save_pre', 'wp_filter_post_kses') !== false;
    } finally {
        if (! $filteredBefore) {
            kses_remove_filters();
        }
    }

    expect($stillFiltered)->toBeTrue()
        ->and(array_map(static fn (ResetAction $a): string => $a->kind, $actions))->toBe([
        ResetAction::RESTORE_PAGE,
        ResetAction::RESTORE_PAGE,
        ResetAction::REMOVE_DEMO,
        ResetAction::RESTORE_READING,
    ]);

    $restored = get_post($about);
    expect($restored)->toBeInstanceOf(WP_Post::class)
        ->and($restored->post_title)->toBe('Owner corex-reset-about')
        ->and($restored->post_content)->toBe($owned)
        ->and($restored->post_name)->toBe('corex-reset-about')
        ->and(get_post_meta($about, '_wp_page_template', true))->toBe('page-wide')
        ->and(get_post_meta($about, '_corex_kit_page', true))->toBe('')
        ->and(get_post_meta($about, PageRecord::META, true))->toBe('')
        ->and(get_post_field('post_content', $contact))->toBe('')
        ->and(get_post_status($contact))->toBe('draft')
        ->and(get_post($created->ID))->toBeNull()
        ->and(get_option('show_on_front'))->toBe('page')
        ->and((int) get_option('page_on_front'))->toBe($about);
});

it('keeps the records of the first apply when a kit is applied twice', function () {
    $about = ($this->owner)('corex-reset-about', 'OWNER ABOUT');
    update_option('show_on_front', 'page');
    update_option('page_on_front', $about);
    $activator = new BlueprintActivator(new WpPluginActivationInspector(new WpMultisiteContext()));

    $activator->seedPages(kitResetPlan(), [], [], ['corex-reset-about' => 'replace']);
    // The kit's front page is the site's now; a second apply must not record it as the owner's.
    $activator->seedPages(kitResetPlan(), [], [], ['corex-reset-about' => 'replace']);

    expect(get_post_meta($about, PageRecord::META, true)['content'])->toBe('OWNER ABOUT')
        ->and((int) get_option(PageRecord::READING_OPTION)['page_on_front'])->toBe($about);
});

it('keeps a page the kit made its own when it is replaced, so a reset still removes it', function () {
    $activator = new BlueprintActivator(new WpPluginActivationInspector(new WpMultisiteContext()));
    $activator->seedPages(kitResetPlan());
    $made = get_page_by_path('corex-reset-about');
    wp_update_post(['ID' => $made->ID, 'post_content' => 'EDITED SINCE']);

    $activator->seedPages(kitResetPlan(), [], [], ['corex-reset-about' => 'replace']);

    expect(get_post_meta($made->ID, '_corex_kit_page', true))->toBe(PageDisposition::PERSISTED_CREATED)
        ->and(get_post_meta($made->ID, PageRecord::META, true))->toBe('');
});

it('keeps a replaced page that was applied before records existed and says so', function () {
    $about = ($this->owner)('corex-reset-about', 'KIT VERSION');
    update_post_meta($about, '_corex_kit_page', PageDisposition::PERSISTED_REPLACED);
    update_option(BlueprintActivator::SEEDED_OPTION, [$about]);

    $actions = resetKitPages();

    expect($actions)->toHaveCount(1)
        ->and($actions[0]->kind)->toBe(ResetAction::KEEP_PAGE)
        ->and($actions[0]->label)->toContain('kept: no earlier version recorded')
        ->and(get_post_field('post_content', $about))->toBe('KIT VERSION')
        ->and(get_post_meta($about, '_corex_kit_page', true))->toBe('');
});
