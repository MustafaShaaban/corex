<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Theme;

defined('ABSPATH') || exit;

/**
 * The line in a front-end page's head that says the site runs CoreX (spec 111).
 *
 * Technology scanners such as Wappalyzer recognise a platform by its generator tag, the way they
 * report "WordPress 7.1.3" from WordPress's. This one carries the name and no version: a version
 * tells anyone looking for a vulnerable site which release to try (FR-002, the owner's decision).
 *
 * It is a `wp_head` callback of its own rather than a filter on WordPress's tag, so a site that
 * removes WordPress's tag keeps this one (FR-004). A site that wants no mark answers
 * {@see self::FILTER} with false.
 */
final class GeneratorTag
{
    public const NAME = 'CoreX';

    /** Answered false, the page carries no mark. Read when the head is printed (FR-005). */
    public const FILTER = 'corex_generator_tag';

    public function print(): void
    {
        echo $this->markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup() escapes the one value it prints.
    }

    /**
     * The tag in the form WordPress prints its own, or nothing when the site turned it off.
     */
    public function markup(): string
    {
        if (! (bool) apply_filters(self::FILTER, true)) {
            return '';
        }

        return '<meta name="generator" content="' . esc_attr(self::NAME) . '" />' . "\n";
    }
}
