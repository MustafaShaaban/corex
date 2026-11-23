<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Theme;

use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Keeps the templates and parts only the CoreX theme has out of the lists a child's editor reads,
 * when the child sets `hideTemplates` (spec 135, FR-022; DECISIONS #664).
 *
 * The lists only, never resolution. WordPress draws a page through `get_block_templates()` too, so
 * that function is filtered only while the Site Editor's two collection routes answer
 * (`GET /wp/v2/templates` and `GET /wp/v2/template-parts`); the page template choices are filtered at
 * `theme_templates`, keeping the template a post already uses so that saving it never resets it. A
 * template the child has itself, a copy saved in the database and a plugin's template stay listed.
 */
final class ParentTemplatesHider
{
    private const ROUTES = '#^/wp/v2/(templates|template-parts)/?$#';

    private bool $listing = false;

    public function __construct(private readonly ChildThemeSwitches $switches)
    {
    }

    /**
     * `rest_request_before_callbacks`: start filtering for one of the two collection routes.
     */
    public function beforeListing(mixed $response, mixed $handler, WP_REST_Request $request): mixed
    {
        if ($request->get_method() === 'GET' && preg_match(self::ROUTES, $request->get_route()) === 1
            && $this->switches->hides(ChildThemeSwitches::TEMPLATES)) {
            $this->listing = true;
            add_filter('get_block_templates', [$this, 'withoutParentOnly'], 10, 3);
        }

        return $response;
    }

    /**
     * `rest_request_after_callbacks`: stop filtering once the route has answered.
     */
    public function afterListing(mixed $response): mixed
    {
        if ($this->listing) {
            $this->listing = false;
            remove_filter('get_block_templates', [$this, 'withoutParentOnly'], 10);
        }

        return $response;
    }

    /**
     * The templates or parts without those only the CoreX theme's files provide.
     *
     * @param array<int, object> $templates
     * @param array<string, mixed> $query
     * @return array<int, object>
     */
    public function withoutParentOnly(array $templates, array $query = [], string $type = 'wp_template'): array
    {
        if (! $this->switches->hides(ChildThemeSwitches::TEMPLATES)) {
            return $templates;
        }

        return array_values(array_filter(
            $templates,
            fn (object $template): bool => ($template->source ?? '') !== 'theme'
                || ! $this->parentOnly((string) ($template->slug ?? ''), (string) ($template->type ?? $type)),
        ));
    }

    /**
     * `theme_templates`: the page template choices without those only the CoreX theme has.
     *
     * @param array<string, string> $templates Names, by template slug or file.
     * @return array<string, string>
     */
    public function pickerChoices(array $templates, ?object $theme = null, ?object $post = null): array
    {
        if (! $this->switches->hides(ChildThemeSwitches::TEMPLATES)
            || ($theme !== null && method_exists($theme, 'get_stylesheet') && $theme->get_stylesheet() !== get_stylesheet())) {
            return $templates;
        }

        $current = $post !== null ? (string) ($post->page_template ?? '') : '';

        return array_filter(
            $templates,
            fn (string $slug): bool => $slug === $current || ! $this->parentOnly($slug, 'wp_template'),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /** Whether the CoreX theme has a file for this slug and the child has none. */
    private function parentOnly(string $slug, string $type): bool
    {
        if ($slug === '') {
            return false;
        }

        $child = get_block_theme_folders(get_stylesheet())[$type] ?? null;
        $parent = get_block_theme_folders(get_template())[$type] ?? null;

        if ($child === null || $parent === null) {
            return false;
        }

        return $this->switches->parentHas($parent . '/' . $slug . '.html')
            && ! $this->switches->childHas($child . '/' . $slug . '.html');
    }
}
