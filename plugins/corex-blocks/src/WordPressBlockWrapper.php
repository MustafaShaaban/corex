<?php

/**
 * @package Corex\Blocks
 */

declare(strict_types=1);

namespace Corex\Blocks;

defined('ABSPATH') || exit;

use WP_Block_Supports;
use WP_HTML_Tag_Processor;
use WP_REST_Request;

/**
 * Merges what `get_block_wrapper_attributes()` returns for the block being rendered into the root
 * element of its markup, with WordPress's tag processor (spec 136, FR-001 to FR-003; DECISIONS #670).
 *
 * Each class is added to the root's classes, a style is appended to the root's style, and any other
 * attribute (the anchor's `id`, an `aria-label`) is set only when the root does not have it already.
 * Nothing else in the markup is touched: with nothing to merge it is returned byte for byte.
 *
 * The editor's preview (WordPress's block renderer route, which `ServerSideRender` asks) gets the
 * markup unmerged: the editor's own wrapper already carries the attributes (FR-006a).
 */
final class WordPressBlockWrapper implements BlockWrapper
{
    /** WordPress's block renderer route, the one `ServerSideRender` asks for the editor's preview. */
    private const PREVIEW_ROUTE = '/wp/v2/block-renderer/';

    /** @var list<string> The route of each REST request being dispatched, innermost last. */
    private array $routes = [];

    /** Follows which REST route is being answered, so the preview can be told apart. Once, on boot. */
    public function register(): void
    {
        add_filter('rest_request_before_callbacks', [$this, 'enterRoute'], 10, 3);
        add_filter('rest_request_after_callbacks', [$this, 'leaveRoute'], 10, 3);
    }

    /**
     * The `rest_request_before_callbacks` listener; hands the response on unchanged.
     *
     * @param mixed               $response
     * @param array<string,mixed> $handler
     */
    public function enterRoute(mixed $response, array $handler, WP_REST_Request $request): mixed
    {
        $this->routes[] = $request->get_route();

        return $response;
    }

    /**
     * The `rest_request_after_callbacks` listener, which WordPress fires after every
     * `rest_request_before_callbacks`; hands the response on unchanged.
     *
     * @param mixed               $response
     * @param array<string,mixed> $handler
     */
    public function leaveRoute(mixed $response, array $handler, WP_REST_Request $request): mixed
    {
        array_pop($this->routes);

        return $response;
    }

    /**
     * Called inside a block's render callback, where WordPress knows which block is being rendered.
     * Called with no block being rendered (a callback run directly), it has nothing to merge.
     */
    public function apply(string $markup, array $rootClasses = []): string
    {
        if ($markup === '' || WP_Block_Supports::$block_to_render === null || $this->isEditorPreview()) {
            return $markup;
        }

        return $this->merge($markup, get_block_wrapper_attributes(), $rootClasses);
    }

    /**
     * @param string       $markup      The renderer's markup.
     * @param string       $attributes  Wrapper attributes as WordPress prints them (`class="..." id="..."`).
     * @param list<string> $rootClasses The classes that mark the root: the first element, in document order,
     *                                  carrying any of them (whole class names). When none does, or the list
     *                                  is empty, the first element is the root.
     */
    public function merge(string $markup, string $attributes, array $rootClasses = []): string
    {
        $wanted = $this->parse($attributes);
        if ($wanted === []) {
            return $markup;
        }

        $root = $this->root($markup, $rootClasses);
        if ($root === null) {
            return $markup;
        }

        foreach ($wanted as $name => $value) {
            if ($name === 'class') {
                foreach (preg_split('/\s+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $class) {
                    $root->add_class($class);
                }
            } elseif ($name === 'style') {
                $root->set_attribute('style', $this->appendStyle($root->get_attribute('style'), (string) $value));
            } elseif ($root->get_attribute($name) === null) {
                $root->set_attribute($name, $value);
            }
        }

        return $root->get_updated_html();
    }

    /**
     * Whether the REST request being answered right now is the editor's preview of the block being
     * rendered. Only the previewed block is left unmerged: a CoreX block nested in what is previewed has
     * no editor wrapper of its own there. A page a visitor loads dispatches no REST request, so the list
     * is empty there and this is false.
     *
     * WordPress fires the two filters in pairs, but not when a route's callback throws: the route then
     * stays recorded until the process ends. Only another preview of the same block could be affected.
     */
    private function isEditorPreview(): bool
    {
        $route = end($this->routes);
        $block = WP_Block_Supports::$block_to_render['blockName'] ?? null;

        return is_string($route) && is_string($block) && $route === self::PREVIEW_ROUTE . $block;
    }

    /**
     * @return array<string,string|true> Each attribute by its name, decoded.
     */
    private function parse(string $attributes): array
    {
        if (trim($attributes) === '') {
            return [];
        }

        $tag = new WP_HTML_Tag_Processor('<div ' . $attributes . '>');
        if (! $tag->next_tag()) {
            return [];
        }

        $parsed = [];
        foreach ($tag->get_attribute_names_with_prefix('') ?? [] as $name) {
            $value = $tag->get_attribute($name);
            if (is_string($value) || $value === true) {
                $parsed[$name] = $value;
            }
        }

        return $parsed;
    }

    /**
     * The processor stopped on the root element, or null when the markup holds no element.
     *
     * @param list<string> $rootClasses
     */
    private function root(string $markup, array $rootClasses): ?WP_HTML_Tag_Processor
    {
        $named = new WP_HTML_Tag_Processor($markup);
        while ($rootClasses !== [] && $named->next_tag()) {
            foreach ($rootClasses as $class) {
                if ($named->has_class($class)) {
                    return $named;
                }
            }
        }

        $first = new WP_HTML_Tag_Processor($markup);

        return $first->next_tag() ? $first : null;
    }

    private function appendStyle(string|bool|null $current, string $style): string
    {
        $current = is_string($current) ? rtrim(trim($current), ';') : '';

        return $current === '' ? $style : $current . ';' . $style;
    }
}
