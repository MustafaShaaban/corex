<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Knowledge;

defined('ABSPATH') || exit;

/**
 * CoreX's own knowledge for the assistant (spec 129, User Story 6): the site's name and tagline, and
 * its published pages, each with its address and the start of its text.
 *
 * It knows nothing a site did not publish, which is the point; it also knows nothing a site did not
 * write on a page, so a site with a profile of itself (an `llms.txt`, an "about" text) should give the
 * assistant that through its own {@see KnowledgeProvider}. The default does not sort pages by
 * language: a language plugin's pages all count, and the model answers in the visitor's language.
 *
 * Built once per language and kept for six hours. Saving a page moves the cache to a new generation
 * ({@see forget()}), so the next question builds afresh and the old entries expire on their own.
 */
final class SiteKnowledge implements KnowledgeProvider
{
    public const CACHE_PREFIX = 'corex_assistant_knowledge_';

    /** A number that changes whenever a page does; part of every cache key. */
    public const GENERATION_OPTION = 'corex_assistant_knowledge_generation';

    private const CACHE_SECONDS = 21600;

    /** Enough for a small site's pages; the model reads all of it with every question. */
    public const MAX_PAGES = 40;

    /** Characters kept from each page's text. */
    public const EXCERPT_CHARS = 400;

    /** Bytes of knowledge text at most: a question carries all of it. */
    public const MAX_BYTES = 24000;

    /**
     * @param \Closure(): array{name: string, tagline: string}                    $site
     * @param \Closure(int): list<array{title: string, url: string, text: string}> $pages      at most this many
     * @param \Closure(string): mixed                                               $cacheGet
     * @param \Closure(string, array<string, mixed>, int): void                     $cacheSet
     * @param \Closure(): int                                                       $generation
     */
    public function __construct(
        private readonly \Closure $site,
        private readonly \Closure $pages,
        private readonly \Closure $cacheGet,
        private readonly \Closure $cacheSet,
        private readonly \Closure $generation,
    ) {
    }

    public static function fromWordPress(): self
    {
        return new self(
            static fn (): array => [
                'name' => html_entity_decode((string) get_bloginfo('name'), ENT_QUOTES, 'UTF-8'),
                'tagline' => html_entity_decode((string) get_bloginfo('description'), ENT_QUOTES, 'UTF-8'),
            ],
            static function (int $limit): array {
                $pages = [];
                $posts = get_posts([
                    'post_type' => 'page',
                    'post_status' => 'publish',
                    'has_password' => false,
                    'numberposts' => $limit,
                    'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC'],
                    'no_found_rows' => true,
                ]);
                foreach ($posts as $post) {
                    if (! $post instanceof \WP_Post) {
                        continue;
                    }
                    $pages[] = [
                        'title' => html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8'),
                        'url' => (string) get_permalink($post),
                        'text' => wp_strip_all_tags(strip_shortcodes(excerpt_remove_blocks((string) $post->post_content))),
                    ];
                }

                return $pages;
            },
            static fn (string $key): mixed => get_transient($key),
            static function (string $key, array $value, int $seconds): void {
                set_transient($key, $value, $seconds);
            },
            static fn (): int => (int) get_option(self::GENERATION_OPTION, 0),
        );
    }

    /**
     * Starts a new generation of the cache: what the knowledge is made of changed, so every language is
     * built again. A site's own provider hears it on `corex_assistant_knowledge_forgotten` and can drop
     * its own cache.
     */
    public static function forget(): void
    {
        update_option(self::GENERATION_OPTION, (int) get_option(self::GENERATION_OPTION, 0) + 1, false);
        do_action('corex_assistant_knowledge_forgotten');
    }

    public function for(string $locale): Knowledge
    {
        $key = self::CACHE_PREFIX . ($this->generation)() . '_' . $locale;
        $cached = ($this->cacheGet)($key);
        if (is_array($cached) && isset($cached['site'], $cached['lines'], $cached['pages'])) {
            return new Knowledge($locale, (string) $cached['site'], (array) $cached['lines'], (array) $cached['pages']);
        }

        $built = $this->build();
        ($this->cacheSet)($key, $built, self::CACHE_SECONDS);

        return new Knowledge($locale, $built['site'], $built['lines'], $built['pages']);
    }

    /**
     * @return array{site: string, lines: list<string>, pages: list<array{label: string, url: string}>}
     */
    private function build(): array
    {
        $site = ($this->site)();
        $lines = $site['tagline'] !== '' ? [$site['tagline'], ''] : [];
        $pages = [];
        $bytes = strlen($site['name']) + strlen($site['tagline']);

        foreach (($this->pages)(self::MAX_PAGES) as $page) {
            $title = trim($page['title']);
            if ($title === '' || $page['url'] === '') {
                continue;
            }
            $block = ['## ' . $title . ' (' . $page['url'] . ')', '', self::excerpt($page['text']), ''];
            $size = strlen(implode("\n", $block));
            if ($bytes + $size > self::MAX_BYTES) {
                break;
            }
            $bytes += $size;
            $lines = [...$lines, ...$block];
            $pages[] = ['label' => $title, 'url' => $page['url']];
        }

        return ['site' => $site['name'], 'lines' => $lines, 'pages' => $pages];
    }

    private static function excerpt(string $text): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return mb_strlen($text) > self::EXCERPT_CHARS ? rtrim(mb_substr($text, 0, self::EXCERPT_CHARS)) . '…' : $text;
    }
}
