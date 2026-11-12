<?php

/**
 * Integration test: the corex-ui patterns as a visitor and an editor see them on real ./wp (spec 115,
 * US-SAFE-14 and US-SAFE-15). A kit page that still references the stats and testimonial patterns shows a
 * visitor neither; an editor sees both, labelled. The hero and call-to-action buttons link to the brand's
 * primary action, or the contact page, or are not drawn; an address given in the editor is kept.
 *
 * @package Corex\Tests\Integration\Theme
 */

declare(strict_types=1);

use Corex\Support\Config\ConfigInterface;
use Corex\Ui\Patterns\ActionLinks;
use Corex\Ui\Patterns\PatternLibrary;

const ACTION_LINK_CSS = __DIR__ . '/../../../addons/corex-ui/assets/action-link.css';

function brandConfig(string $primaryActionLink): ConfigInterface
{
    return new class ($primaryActionLink) implements ConfigInterface {
        public function __construct(private readonly string $link)
        {
        }

        public function get(string $key, mixed $default = null): mixed
        {
            return $key === 'brand.primary_action_link' ? $this->link : $default;
        }

        public function has(string $key): bool
        {
            return $key === 'brand.primary_action_link';
        }
    };
}

function heroButton(): array
{
    $hero = array_values(array_filter((new PatternLibrary())->patterns(), static fn (array $p): bool => $p['name'] === 'corex/hero'))[0];

    return parse_blocks($hero['content'])[0]['innerBlocks'][2]['innerBlocks'][0];
}

afterEach(function () {
    wp_set_current_user(0);
});

it('shows a visitor none of the sample figures and quote on a page that references the patterns', function () {
    wp_set_current_user(0);

    $html = do_blocks('<!-- wp:pattern {"slug":"corex/stats"} /--><!-- wp:pattern {"slug":"corex/testimonial"} /--><!-- wp:pattern {"slug":"corex/features"} /-->');

    expect($html)->not->toContain('98%')
        ->and($html)->not->toContain('Client satisfaction')
        ->and($html)->not->toContain('A happy client')
        ->and($html)->not->toContain('Sample: replace before publishing')
        ->and($html)->toContain('What we do');
});

it('shows an editor the samples, each labelled', function () {
    $editor = wp_insert_user(['user_login' => 'corex_claims_editor', 'user_pass' => wp_generate_password(), 'role' => 'editor']);
    wp_set_current_user($editor);

    try {
        $html = do_blocks('<!-- wp:pattern {"slug":"corex/stats"} /--><!-- wp:pattern {"slug":"corex/testimonial"} /-->');
    } finally {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_set_current_user(0);
        wp_delete_user($editor);
    }

    expect(substr_count($html, 'Sample: replace before publishing'))->toBe(2)
        ->and($html)->toContain('A happy client');
});

it('shows a visitor a stats section once someone has edited it, and still hides the quote nobody touched', function () {
    wp_set_current_user(0);
    $stats  = array_values(array_filter((new PatternLibrary())->patterns(), static fn (array $p): bool => $p['name'] === 'corex/stats'))[0];
    $edited = str_replace([' is-corex-sample', '"className":"is-corex-sample",', '"value":"100+"'], ['', '', '"value":"240"'], $stats['content']);

    $html = do_blocks($edited . '<!-- wp:pattern {"slug":"corex/testimonial"} /-->');

    expect($html)->toContain('wp-block-columns')
        ->and($html)->not->toContain('A happy client');
});

it('links the hero button to the brand primary action, or the contact page, or leaves it out', function () {
    if (get_page_by_path(ActionLinks::CONTACT_SLUG) instanceof WP_Post) {
        $this->markTestSkipped('The install already has a /contact/ page of its own.');
    }
    $block = heroButton();
    $html  = $block['innerHTML'];

    $branded = (new ActionLinks(brandConfig('https://acme.test/quote'), ACTION_LINK_CSS))->render($html, $block);
    expect($branded)->toContain('href="https://acme.test/quote"');

    $nothing = (new ActionLinks(brandConfig(''), ACTION_LINK_CSS))->render($html, $block);
    expect($nothing)->toBe('');

    $contact = wp_insert_post(['post_title' => 'Contact', 'post_name' => 'contact', 'post_status' => 'publish', 'post_type' => 'page']);

    try {
        $fallback = (new ActionLinks(brandConfig(''), ACTION_LINK_CSS))->render($html, $block);
        expect($fallback)->toContain('href="' . get_permalink($contact) . '"');
    } finally {
        wp_delete_post($contact, true);
    }
});

it('keeps an address someone gave the button in the editor', function () {
    $block = heroButton();
    $html  = str_replace('<a class="wp-block-button__link', '<a href="https://acme.test/own" class="wp-block-button__link', $block['innerHTML']);

    expect((new ActionLinks(brandConfig('https://acme.test/quote'), ACTION_LINK_CSS))->render($html, $block))->toContain('href="https://acme.test/own"');
});
