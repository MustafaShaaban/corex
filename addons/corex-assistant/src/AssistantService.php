<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant;

defined('ABSPATH') || exit;

use Corex\Assistant\Chain\ChatRouter;
use Corex\Assistant\Front\AssistantStrings;
use Corex\Assistant\Knowledge\KnowledgeProvider;
use Corex\Assistant\Reply\Instructions;
use Corex\Assistant\Reply\ReplyShaper;

/**
 * A visitor's conversation in, the assistant's reply out (spec 129, User Story 1).
 *
 * The knowledge for the visitor's language and the standing instructions go first; the chain answers;
 * the reply is shaped so it links only to the site's known pages. When nothing can answer, the reply
 * is "busy", which the chat shows with the hand-off form: never an error page.
 */
final class AssistantService
{
    public function __construct(
        private readonly AssistantSettings $settings,
        private readonly ChatRouter $router,
        private readonly KnowledgeProvider $knowledge,
        private readonly AssistantStrings $strings,
        private readonly string $siteHost,
    ) {
    }

    /**
     * @param list<array{role: string, content: string}> $messages the visitor's, made safe
     * @return array{reply: string, team: bool, links: list<array{url: string, label: string}>, busy: bool}
     */
    public function answer(array $messages, string $locale): array
    {
        $chain = $this->settings->chain();
        if ($chain === []) {
            return self::busy();
        }

        // The reply's language is the visitor's, not the page's, and the knowledge follows it, so its
        // pages and links are in the language the visitor reads.
        $replyIn = Instructions::replyLanguage($messages, $locale);
        $knowledge = $this->knowledge->for($replyIn);
        $name = (string) ($this->strings->for($replyIn)['name'] ?? '');
        $text = $this->router->answer($chain, [
            ['role' => 'system', 'content' => Instructions::system($knowledge, $name, $replyIn, $this->voice($replyIn))],
            ...$messages,
        ]);
        if ($text === null) {
            return self::busy();
        }

        $shaped = ReplyShaper::shape($text, $this->siteHost, $knowledge->links());

        return $shaped['reply'] === '' ? self::busy() : $shaped + ['busy' => false];
    }

    /**
     * The site's own rules for how replies read in a language (a dialect, words to avoid), from the
     * `corex_assistant_voice` filter: a list of short sentences.
     *
     * @return list<string>
     */
    private function voice(string $language): array
    {
        $voice = apply_filters('corex_assistant_voice', [], $language);

        return is_array($voice) ? array_values(array_filter($voice, 'is_string')) : [];
    }

    /** @return array{reply: string, team: bool, links: list<array{url: string, label: string}>, busy: bool} */
    public static function busy(): array
    {
        return ['reply' => '', 'team' => true, 'links' => [], 'busy' => true];
    }
}
