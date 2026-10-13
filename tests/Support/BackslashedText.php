<?php

/**
 * Text a store has to hand back as it was handed over.
 *
 * WordPress takes one level of slashes off what it is given to store as a post's field, a meta
 * value, a comment or a user's name. A store that hands text over as it is therefore keeps it
 * without its backslashes: `C:\offers\new` is read back as `C:offersnew`. These are the texts
 * every such store is asked to keep, in one place so that each store is asked for the same.
 *
 *     it('keeps a note as it was written', function (string $text) { ... })
 *         ->with(BackslashedText::cases());
 *
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

final class BackslashedText
{
    /** A path, a network path, a CSS escape and a backslash at the end, in one text. */
    public const ALL = 'C:\\offers\\new, \\\\server\\share, q::before{content:"\\201C"}, ends with \\';

    /**
     * @return array<string,array{0:string}>
     */
    public static function cases(): array
    {
        return [
            'a Windows path' => ['C:\\offers\\new'],
            'a network path' => ['\\\\server\\share'],
            'a CSS escape' => ['q::before{content:"\\201C"}'],
            'a backslash before each quote' => ['a \\"quoted\\" word and it\\\'s'],
            'a backslash at the end' => ['ends with \\'],
            // WordPress changed neither of these two: they are here so that a fix does not.
            'both quotes' => ['Jerry\'s "offer"'],
            'Arabic and an accent' => ['عرض "خاص" في café'],
        ];
    }
}
