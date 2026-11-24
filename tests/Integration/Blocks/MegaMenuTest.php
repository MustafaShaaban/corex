<?php

/**
 * The mega menu item inside a Navigation block, drawn by WordPress (spec 137 slice 3; US-NAV-02,
 * FR-009 to FR-012, FR-015, SC-002; DECISIONS #691).
 *
 * The panels are real template parts of the `mega-menu` area saved for the active theme, and the menu
 * is a Navigation block, so what is read is what a visitor's page holds: one navigation, one list, a
 * list item per item, a button and its panel in each mega item, ids that are never repeated.
 *
 * @package Corex\Tests\Integration\Blocks
 */

declare(strict_types=1);

use Corex\Tests\Support\CreatedPosts;
use Corex\Ui\Blocks\MegaMenuArea;

/** Saves a template part for the active theme in an area; its slug. */
function megaMenuTestPart(string $slug, string $content, string $area = MegaMenuArea::AREA, string $status = 'publish'): string
{
    $id = wp_insert_post([
        'post_type'    => 'wp_template_part',
        'post_name'    => $slug,
        'post_title'   => $slug,
        'post_status'  => $status,
        'post_content' => $content,
    ]);
    wp_set_post_terms($id, get_stylesheet(), 'wp_theme');
    wp_set_post_terms($id, $area, 'wp_template_part_area');

    return $slug;
}

/** A paragraph block holding a link, as a panel's content. */
function megaMenuTestParagraph(string $text): string
{
    return sprintf('<!-- wp:paragraph --><p><a href="https://example.test/%1$s">%1$s</a></p><!-- /wp:paragraph -->', $text);
}

function megaMenuTestItem(string $label, string $panel): string
{
    return sprintf('<!-- wp:corex/mega-menu %s /-->', wp_json_encode(['label' => $label, 'panel' => $panel]));
}

function megaMenuTestLink(string $label): string
{
    return sprintf('<!-- wp:navigation-link %s /-->', wp_json_encode(['label' => $label, 'url' => 'https://example.test/' . strtolower($label), 'kind' => 'custom']));
}

/** A Navigation block holding the given items inline, as a CoreX header holds one. */
function megaMenuTestNavigation(string ...$items): string
{
    return '<!-- wp:navigation {"overlayMenu":"never","className":"corex-header__nav","layout":{"type":"flex"}} -->'
        . implode('', $items)
        . '<!-- /wp:navigation -->';
}

/** @return DOMXPath The markup, read. */
function megaMenuTestRead(string $html): DOMXPath
{
    $document = new DOMDocument();
    libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>', LIBXML_NOERROR);
    libxml_clear_errors();

    return new DOMXPath($document);
}

beforeEach(function () {
    $this->posts = CreatedPosts::watch('wp_template_part', 'wp_navigation');
    $type = WP_Block_Type_Registry::get_instance()->get_registered('corex/mega-menu');
    foreach ($type?->view_script_handles ?? [] as $handle) {
        wp_dequeue_script($handle);
    }
    foreach ($type?->style_handles ?? [] as $handle) {
        wp_dequeue_style($handle);
    }
});

afterEach(function () {
    $this->posts->delete();
});

it('FR-009: registers "Mega menu item" with the Navigation block as its only parent', function () {
    $type = WP_Block_Type_Registry::get_instance()->get_registered('corex/mega-menu');

    expect($type)->not->toBeNull()
        ->and($type->title)->toBe('Mega menu item')
        ->and($type->parent)->toBe(['core/navigation'])
        ->and(array_keys($type->attributes))->toContain('label', 'panel')
        ->and($type->view_script_handles)->not->toBe([]);
});

it('FR-010: registers the template part area "Mega menu panels"', function () {
    $areas = array_column(get_allowed_block_template_part_areas(), 'label', 'area');

    expect($areas)->toHaveKey(MegaMenuArea::AREA)
        ->and($areas[MegaMenuArea::AREA])->toBe('Mega menu panels');
});

it('SC-002, FR-011: three mega items among six draw one navigation with one list of six, each with its own panel', function () {
    $services = megaMenuTestPart('e2e-mega-services', megaMenuTestParagraph('consulting'));
    $products = megaMenuTestPart('e2e-mega-products', megaMenuTestParagraph('features'));
    $resources = megaMenuTestPart('e2e-mega-resources', megaMenuTestParagraph('guides'));

    $html = do_blocks(megaMenuTestNavigation(
        megaMenuTestItem('Services', $services),
        megaMenuTestItem('Products', $products),
        megaMenuTestItem('Resources', $resources),
        megaMenuTestLink('Work'),
        megaMenuTestLink('About'),
        megaMenuTestLink('Blog'),
    ));
    $page = megaMenuTestRead($html);
    $lists = $page->query('//nav//ul[contains(@class, "wp-block-navigation__container")]');
    $items = $page->query('//nav//ul[contains(@class, "wp-block-navigation__container")]/li');
    $buttons = $page->query('//li[contains(@class, "corex-mega-menu")]/button');

    expect($page->query('//nav')->length)->toBe(1)
        ->and($lists->length)->toBe(1)
        ->and($items->length)->toBe(6)
        ->and($buttons->length)->toBe(3);

    $panels = [];
    foreach ($buttons as $button) {
        $panel = $page->query('//*[@id="' . $button->getAttribute('aria-controls') . '"]')->item(0);
        expect($panel)->not->toBeNull()
            ->and($panel->parentNode)->toBe($button->parentNode)
            ->and($panel->getAttribute('aria-labelledby'))->toBe($button->getAttribute('id'));
        $panels[] = trim($panel->textContent);
    }

    expect($panels)->toBe(['consulting', 'features', 'guides']);
});

it('FR-011: never repeats an id when the same menu is drawn twice on a page', function () {
    $services = megaMenuTestPart('e2e-mega-twice', megaMenuTestParagraph('consulting'));
    $menu = megaMenuTestNavigation(megaMenuTestItem('Services', $services), megaMenuTestLink('About'));

    $page = megaMenuTestRead(do_blocks($menu . $menu));
    $ids = [];
    foreach ($page->query('//*[@id]') as $element) {
        $ids[] = $element->getAttribute('id');
    }

    expect($page->query('//li[contains(@class, "corex-mega-menu")]')->length)->toBe(2)
        ->and($ids)->toBe(array_values(array_unique($ids)));
});

it('FR-012: a menu whose mega item has no panel, a missing one or another area\'s part shows the rest of its links', function () {
    $footer = megaMenuTestPart('e2e-mega-footer-part', megaMenuTestParagraph('footer'), 'footer');

    $html = do_blocks(megaMenuTestNavigation(
        megaMenuTestItem('No panel', ''),
        megaMenuTestItem('Deleted', 'e2e-mega-never-saved'),
        megaMenuTestItem('Footer', $footer),
        megaMenuTestLink('About'),
    ));
    $page = megaMenuTestRead($html);

    expect($page->query('//li[contains(@class, "corex-mega-menu")]')->length)->toBe(0)
        ->and($page->query('//nav//ul/li')->length)->toBe(1)
        ->and($html)->not->toContain('No panel')
        ->and($html)->not->toContain('Template part has been deleted');
});

it('FR-012: a panel that holds the menu it belongs to is drawn once, and the item inside it is skipped', function () {
    $slug = 'e2e-mega-self';
    megaMenuTestPart($slug, megaMenuTestParagraph('inside') . megaMenuTestNavigation(megaMenuTestItem('Again', $slug), megaMenuTestLink('Deep')));

    $page = megaMenuTestRead(do_blocks(megaMenuTestNavigation(megaMenuTestItem('Self', $slug))));

    expect($page->query('//li[contains(@class, "corex-mega-menu")]')->length)->toBe(1)
        ->and($page->query('//*[contains(@class, "corex-nav__panel")]')->length)->toBe(1)
        ->and($page->query('//*[contains(@class, "corex-nav__panel")]//a[normalize-space(.)="Deep"]')->length)->toBe(1);
});

it('FR-015, FR-044: loads the item\'s script and style only where a menu holds an item', function () {
    $type = WP_Block_Type_Registry::get_instance()->get_registered('corex/mega-menu');
    $handle = $type->view_script_handles[0];

    // Declared in block.json, so WordPress enqueues them where the block is (constitution VI).
    do_blocks(megaMenuTestNavigation(megaMenuTestLink('About')));
    $withoutItem = wp_script_is($handle, 'enqueued') || wp_style_is($type->style_handles[0], 'enqueued');

    do_blocks(megaMenuTestNavigation(megaMenuTestItem('Services', megaMenuTestPart('e2e-mega-script', megaMenuTestParagraph('x')))));

    expect($withoutItem)->toBeFalse()
        ->and(wp_script_is($handle, 'enqueued'))->toBeTrue()
        ->and(wp_style_is($type->style_handles[0], 'enqueued'))->toBeTrue();

    wp_dequeue_script($handle);
    wp_dequeue_style($type->style_handles[0]);
});

it('FR-012: an item whose panel is a draft, or draws an empty group, is not drawn, and no visitor sees a part message', function () {
    $draft = megaMenuTestPart('e2e-mega-draft', megaMenuTestParagraph('draft'), MegaMenuArea::AREA, 'draft');
    $empty = megaMenuTestPart('e2e-mega-empty', '<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group"></div><!-- /wp:group -->');
    wp_set_current_user(0);

    $html = do_blocks(megaMenuTestNavigation(
        megaMenuTestItem('Draft', $draft),
        megaMenuTestItem('Empty', $empty),
        megaMenuTestLink('About'),
    ));
    $page = megaMenuTestRead($html);

    expect($page->query('//li[contains(@class, "corex-mega-menu")]')->length)->toBe(0)
        ->and($page->query('//nav//ul/li')->length)->toBe(1)
        ->and($html)->not->toContain('Draft')
        ->and($html)->not->toContain('Template part');
});

it('FR-011: draws an item of a menu stored as a wp_navigation post, as the Site Editor stores menus', function () {
    $services = megaMenuTestPart('e2e-mega-ref', megaMenuTestParagraph('consulting'));
    $menu = wp_insert_post([
        'post_type'    => 'wp_navigation',
        'post_title'   => 'e2e mega ref menu',
        'post_status'  => 'publish',
        'post_content' => megaMenuTestItem('Services', $services) . megaMenuTestLink('About'),
    ]);

    $page = megaMenuTestRead(do_blocks(sprintf('<!-- wp:navigation {"ref":%d,"overlayMenu":"never"} /-->', $menu)));
    $button = $page->query('//nav//ul/li[contains(@class, "corex-mega-menu")]/button')->item(0);

    expect($page->query('//nav//ul/li')->length)->toBe(2)
        ->and($button)->not->toBeNull()
        ->and(trim($page->query('//*[@id="' . $button?->getAttribute('aria-controls') . '"]')->item(0)?->textContent ?? ''))->toBe('consulting');
});
