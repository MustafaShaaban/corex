<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Operations;

defined('ABSPATH') || exit;

/**
 * Where the messages for visitors of a closed mode are kept (spec 124): one option, one entry per
 * mode, each a message per language and a back-by time.
 *
 * It is read only while a closed mode is in force, never on an ordinary page, so it is not among the
 * options WordPress loads with every request. What may be stored (the length, a time in the future)
 * is decided by whoever saves it; this keeps what it is given and reads back only well-formed text.
 */
final class VisitorMessages
{
    public const OPTION = 'corex_visitor_messages';

    public const MAINTENANCE  = 'maintenance';
    public const FORMS_PAUSED = 'forms-paused';

    /** @var list<string> */
    public const MODES = [self::MAINTENANCE, self::FORMS_PAUSED];

    public function for(string $mode): VisitorMessage
    {
        $stored = get_option(self::OPTION, []);
        $entry  = is_array($stored) && is_array($stored[$mode] ?? null) ? $stored[$mode] : [];

        $messages = [];
        foreach ((array) ($entry['messages'] ?? []) as $locale => $text) {
            $text = trim((string) $text);
            if (is_string($locale) && $locale !== '' && $text !== '') {
                $messages[$locale] = $text;
            }
        }

        return new VisitorMessage($messages, max(0, (int) ($entry['back_by'] ?? 0)));
    }

    public function save(string $mode, VisitorMessage $message): void
    {
        if (! in_array($mode, self::MODES, true)) {
            return;
        }

        $stored = get_option(self::OPTION, []);
        $stored = is_array($stored) ? $stored : [];

        $stored[$mode] = ['messages' => $message->messages, 'back_by' => $message->backBy];

        update_option(self::OPTION, $stored, false);
    }
}
