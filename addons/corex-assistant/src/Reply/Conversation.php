<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Reply;

defined('ABSPATH') || exit;

/**
 * The conversation as the visitor's browser sent it, made safe (spec 129, User Story 5): only `user`
 * and `assistant` messages, plain text, each cut to {@see MAX_CHARS}, the last {@see MAX_MESSAGES}.
 * A visitor cannot write the system message, so cannot write the instructions.
 */
final class Conversation
{
    public const MAX_CHARS = 600;
    public const MAX_MESSAGES = 12;

    private const ROLES = ['user', 'assistant'];

    /**
     * @return list<array{role: string, content: string}>
     */
    public static function from(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $messages = [];
        // Cut before any work: only the newest messages count, and only their first characters.
        foreach (array_slice(array_values($raw), -self::MAX_MESSAGES * 2) as $message) {
            if (! is_array($message) || ! in_array($message['role'] ?? '', self::ROLES, true)) {
                continue;
            }
            $cut = mb_substr(is_scalar($message['content'] ?? null) ? (string) $message['content'] : '', 0, self::MAX_CHARS * 2);
            // Control characters only. Markup is harmless here (every reply is shown as text), and
            // stripping tags would cut "price < 500" at the "<".
            $content = trim(mb_substr((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $cut), 0, self::MAX_CHARS));
            if ($content !== '') {
                $messages[] = ['role' => (string) $message['role'], 'content' => $content];
            }
        }

        return array_slice($messages, -self::MAX_MESSAGES);
    }

    /**
     * Whether there is a question to answer: the newest message is the visitor's.
     *
     * @param list<array{role: string, content: string}> $messages
     */
    public static function asks(array $messages): bool
    {
        return $messages !== [] && $messages[array_key_last($messages)]['role'] === 'user';
    }

    /**
     * The language the page gave (`ar`, `pt` from `pt-BR`), else English. Only its first part: the
     * knowledge is kept per language, and a made-up region must not make another copy of it.
     */
    public static function locale(mixed $raw): string
    {
        $language = is_string($raw) ? strtolower((string) strtok(trim($raw), '-_')) : '';

        return preg_match('/^[a-z]{2,3}$/', $language) === 1 ? $language : 'en';
    }
}
