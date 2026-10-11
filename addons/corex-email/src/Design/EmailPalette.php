<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Design;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * The colours an email is drawn in, for one colour scheme (spec 106, FR-012).
 *
 * **This class holds literals, on purpose.** Everywhere else in CoreX a colour is a custom
 * property. An email cannot read one: mail clients do not support them, so a token has to
 * arrive as a value somewhere. It arrives here, once, for every email CoreX sends, where spec
 * 093 had it inside one add-on's support message.
 *
 * `light()` and `dark()` are the CoreX admin's two palettes, the values of
 * `plugins/corex-core/assets/css/corex-admin-tokens.css`. A site that wants its own gives them
 * through {@see with()}; a value that is not `#rrggbb` is refused, because it is written into a
 * `style` attribute.
 */
final readonly class EmailPalette
{
    /**
     * @param string $canvas  The ground behind the message.
     * @param string $surface The card the message is on.
     * @param string $border  The card's edge, and rules.
     * @param string $text    The message.
     * @param string $muted   The footer, and what is said quietly.
     * @param string $accent  The mark of the brand: the card's top edge. Never text.
     * @param string $link    A link in the message: dark enough to read as text.
     */
    public function __construct(
        public string $canvas,
        public string $surface,
        public string $border,
        public string $text,
        public string $muted,
        public string $accent,
        public string $link,
    ) {
        foreach (get_object_vars($this) as $name => $colour) {
            if (preg_match('/^#[0-9a-f]{6}$/', $colour) !== 1) {
                throw new InvalidArgumentException(sprintf('The email colour "%s" must be written as #rrggbb.', $name));
            }
        }
    }

    public static function light(): self
    {
        return new self(
            canvas: '#f6f7f9',
            surface: '#ffffff',
            border: '#e2e5ea',
            text: '#14151a',
            muted: '#5b616d',
            accent: '#ad8643',
            link: '#76521d',
        );
    }

    public static function dark(): self
    {
        return new self(
            canvas: '#16181d',
            surface: '#1c1f26',
            border: '#262a32',
            text: '#f4f5f7',
            muted: '#9aa0ab',
            accent: '#c9a25e',
            link: '#e2c188',
        );
    }

    /**
     * This palette with some of its colours replaced.
     *
     * @param array<string,string> $colours Keyed by the constructor's names. A key that is not
     *                                      one of them is passed over.
     */
    public function with(array $colours): self
    {
        $own = get_object_vars($this);
        foreach (array_intersect_key($colours, $own) as $name => $colour) {
            $own[$name] = strtolower(trim((string) $colour));
        }

        return new self(...$own);
    }
}
