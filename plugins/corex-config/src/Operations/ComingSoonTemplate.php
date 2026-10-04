<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

use Corex\Admin\StandalonePage;

/**
 * The coming-soon page itself (spec 101, FR-008 and FR-009): which template it is, where the
 * default comes from, and what is served when the active theme has no block templates at all.
 *
 * The page is a block template named `coming-soon`. CoreX registers a default under that name, so
 * the mode works under any block theme whether or not it is related to the Corex theme; a theme
 * that ships `templates/coming-soon.html` replaces it, and a copy edited in the Site Editor
 * replaces both. That precedence is WordPress's, not this class's — it is pinned by
 * ComingSoonTemplateSeamTest so a core release that changes it is noticed.
 *
 * This class decides nothing about who is served the page. {@see ComingSoonGuard} does.
 */
final class ComingSoonTemplate
{
    /** The template's slug: the file name, without extension, a theme uses to replace the default. */
    public const SLUG = 'coming-soon';

    /** The registered default, namespaced by the plugin that supplies it. */
    public const NAME = 'corex-config//' . self::SLUG;

    private const DEFAULT_FILE = '/templates/coming-soon.php';

    public function __construct(private readonly StandalonePage $page)
    {
    }

    public function register(): void
    {
        // On `init`, never sooner. WordPress fixes a registered template's id when it is
        // registered, from the theme active at that moment, and before `init` a plugin can still
        // change which theme that is.
        add_action('init', [$this, 'registerDefault']);
    }

    public function registerDefault(): void
    {
        register_block_template(self::NAME, [
            'title'       => __('Coming soon', 'corex'),
            'description' => __('The page visitors see while the site is in Coming soon mode.', 'corex'),
            'content'     => $this->defaultContent(),
        ]);
    }

    /**
     * The default page's block markup, with its strings translated.
     */
    public function defaultContent(): string
    {
        ob_start();
        require dirname(__DIR__, 2) . self::DEFAULT_FILE;

        return trim((string) ob_get_clean());
    }

    /**
     * Resolve the page for this request and return the file WordPress should include to render it,
     * or an empty string when the active theme has no block templates to render with.
     *
     * Call this at the point of rendering, not before: resolving a block template leaves its
     * content in WordPress's globals for the canvas to print, and the template WordPress resolves
     * for the request's own query would overwrite it.
     */
    public function canvas(): string
    {
        return (string) locate_block_template('', self::SLUG, [self::SLUG . '.php']);
    }

    /**
     * The page as one self-contained document, for a theme with no block templates (FR-009).
     *
     * A whole document that never runs `wp_head`, so WordPress's "discourage search engines"
     * setting would not reach it. It is read here instead: the page is indexable unless that
     * setting says otherwise, which is the same rule the block template gets for free (FR-010).
     */
    public function standalone(): string
    {
        $siteName  = (string) get_bloginfo('name');
        $indexable = (string) get_option('blog_public') !== '0';

        $body = '<main class="corex-standalone__card" role="main">'
            . ($siteName !== '' ? '<p class="corex-standalone__eyebrow">' . esc_html($siteName) . '</p>' : '')
            . '<h1 class="corex-standalone__title">' . esc_html__('Coming soon', 'corex') . '</h1>'
            . '<p class="corex-standalone__text">'
            . esc_html__('We are getting things ready. Please check back soon.', 'corex')
            . '</p></main>';

        return $this->page->document(
            $siteName !== '' ? $siteName : __('Coming soon', 'corex'),
            $body,
            'coming-soon',
            $indexable,
        );
    }
}
