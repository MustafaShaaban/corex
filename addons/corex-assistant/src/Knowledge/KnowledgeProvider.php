<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Knowledge;

defined('ABSPATH') || exit;

/**
 * Where the assistant's knowledge comes from (spec 129, FR-012).
 *
 * CoreX's own is {@see SiteKnowledge}. A site with better words about itself returns its own from the
 * `corex_assistant_knowledge_provider` filter:
 *
 *     add_filter('corex_assistant_knowledge_provider', fn () => new MySiteKnowledge());
 *
 * It is asked once per question, so a provider that reads much should keep what it builds.
 */
interface KnowledgeProvider
{
    /**
     * @param string $locale the visitor's language, as the page reported it (`en`, `ar`, `fr`, ...)
     */
    public function for(string $locale): Knowledge;
}
