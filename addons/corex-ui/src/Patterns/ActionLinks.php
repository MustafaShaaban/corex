<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui\Patterns;

defined('ABSPATH') || exit;

use Corex\Support\Config\ConfigInterface;

/**
 * Gives the hero and call-to-action buttons a real address when they are drawn (spec 115, US-SAFE-15).
 * A button carrying {@see PatternLibrary::ACTION_LINK} with no address of its own links to the brand's
 * primary action link, or else the site's published contact page. With neither, the button is not drawn:
 * a button that goes nowhere cannot take keyboard focus and promises what the site does not do. An address
 * someone gave the button in the editor is left as it is.
 *
 * The address is looked up only when such a button is drawn, so the patterns cost no query on a request
 * that does not show one.
 */
final class ActionLinks
{
    public const CONTACT_SLUG = 'contact';

    public const STYLE_HANDLE = 'corex-action-link';

    /**
     * @param string $stylesheet The focus ring's stylesheet, as a file path; its address is derived from it.
     */
    public function __construct(private readonly ConfigInterface $config, private readonly string $stylesheet)
    {
    }

    public function register(): void
    {
        add_filter('render_block_core/button', [$this, 'render'], 10, 2);
        add_action('init', [$this, 'registerStyle']);
    }

    /**
     * The buttons' focus ring, loaded only on a page that draws a button block (Principle VI).
     */
    public function registerStyle(): void
    {
        wp_enqueue_block_style('core/button', [
            'handle' => self::STYLE_HANDLE,
            'src'    => plugins_url(basename($this->stylesheet), $this->stylesheet),
            'path'   => $this->stylesheet,
            'ver'    => (string) filemtime($this->stylesheet),
        ]);
    }

    /**
     * @param array<string,mixed> $block
     */
    public function render(string $content, array $block): string
    {
        $className = (string) ($block['attrs']['className'] ?? '');

        if (! in_array(PatternLibrary::ACTION_LINK, preg_split('/\s+/', trim($className)) ?: [], true)) {
            return $content;
        }

        $link = new \WP_HTML_Tag_Processor($content);
        if (! $link->next_tag(['tag_name' => 'a'])) {
            return $content;
        }

        if (trim((string) $link->get_attribute('href')) !== '') {
            return $content;
        }

        // The tag processor escapes the attribute it writes; an address esc_url_raw() refuses is none.
        $url = esc_url_raw($this->address());
        if ($url === '') {
            return '';
        }

        $link->set_attribute('href', $url);

        return $link->get_updated_html();
    }

    /** The brand's primary action link, else the published contact page, else nothing. */
    public function address(): string
    {
        $primary = trim((string) $this->config->get('brand.primary_action_link', ''));
        if ($primary !== '') {
            return $primary;
        }

        $contact = get_page_by_path(self::CONTACT_SLUG);

        return $contact instanceof \WP_Post && $contact->post_status === 'publish'
            ? (string) get_permalink($contact)
            : '';
    }
}
