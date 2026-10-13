<?php

/**
 * @package Corex\Guides
 */

declare(strict_types=1);

namespace Corex\Guides\Support;

defined('ABSPATH') || exit;

/**
 * What a support message can be about (spec 087).
 *
 * Keys, not free text, so the subject line is predictable and nothing the sender types can
 * shape the email's own structure.
 *
 * A key is what travels, and its label is made where it is read: the form's list is in the
 * language of whoever is choosing from it, and the email's line is in the language of the email.
 * A label made once, when the form was posted, was in the sender's language in both.
 */
final class SupportCategory
{
    /** What a message is about when it does not say, or says something that is not on the list. */
    public const DEFAULT = 'question';

    /** @var list<string> */
    private const KEYS = [self::DEFAULT, 'suggestion', 'missing', 'broken'];

    /** The key itself when it is on the list, the default when it is not. */
    public static function known(string $key): string
    {
        return in_array($key, self::KEYS, true) ? $key : self::DEFAULT;
    }

    /** @return array<string,string> Key => the label a reader chooses by. */
    public static function labels(): array
    {
        $labels = [];

        foreach (self::KEYS as $key) {
            $labels[$key] = self::label($key);
        }

        return $labels;
    }

    /**
     * Written as a `match` over literals rather than a lookup in a translated array, because the
     * string extractor reads `__()` calls in the source and cannot follow `__($array[$key])` — that
     * form compiles, ships, and is simply never translated.
     */
    public static function label(string $key): string
    {
        return match ($key) {
            'suggestion' => __('A suggestion', 'corex'),
            'missing'    => __('Something is missing from the guides', 'corex'),
            'broken'     => __('Something is not working', 'corex'),
            default      => __('A question', 'corex'),
        };
    }
}
