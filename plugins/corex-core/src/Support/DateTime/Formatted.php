<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Support\DateTime;

defined('ABSPATH') || exit;

/**
 * A date that has been prepared for a screen: the words a person reads, and the value a machine
 * reads, carried together.
 *
 * They travel as one object on purpose. FR-012 requires every visible date to be semantic — human
 * text inside a `<time>` element whose `datetime` holds a valid machine value — and the reliable
 * way to get that is to make it impossible for a caller to have one without the other. Before this
 * spec, the two were separate concerns at every call site, and the result was twelve surfaces
 * rendering the machine value *as* the human text.
 *
 * `isPresent` is the third state that a plain string cannot express. An absent date and a date that
 * failed to parse are both "no instant", but neither is an empty string: they render a truthful
 * phrase chosen by the calling field ("Never", "No expiry", "Not recorded"), and they must not
 * render a `datetime` attribute at all, because there is no machine value to put in it.
 */
final class Formatted
{
    private function __construct(
        public readonly string $human,
        public readonly string $machine,
        public readonly bool $isPresent,
    ) {
    }

    /**
     * @param string $human   What the operator reads, already localized.
     * @param string $machine ISO 8601, for the `datetime` attribute and for sorting.
     */
    public static function of(string $human, string $machine): self
    {
        return new self($human, $machine, true);
    }

    /**
     * No instant — missing, or unparseable.
     *
     * @param string $phrase The calling field's own truthful wording.
     */
    public static function absent(string $phrase): self
    {
        return new self($phrase, '', false);
    }

    /**
     * The `<time>` element for this date, or a plain `<span>` when there is no instant to point at.
     *
     * A `<time>` with an empty or invented `datetime` is worse than no `<time>`: it tells assistive
     * technology and any parser that a machine-readable date is present when it is not.
     *
     * @param string $class Optional class attribute for the element.
     */
    public function toHtml(string $class = ''): string
    {
        $attributes = $class !== '' ? ' class="' . esc_attr($class) . '"' : '';

        if (! $this->isPresent) {
            return '<span' . $attributes . '>' . esc_html($this->human) . '</span>';
        }

        return '<time datetime="' . esc_attr($this->machine) . '"' . $attributes . '>'
            . esc_html($this->human)
            . '</time>';
    }

    public function __toString(): string
    {
        return $this->human;
    }
}
