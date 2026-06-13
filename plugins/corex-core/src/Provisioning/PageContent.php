<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Provisioning;

defined('ABSPATH') || exit;

/**
 * Pure predicate: is a page's content "blank" — i.e. adoptable by a kit apply rather than user-owned?
 * Blank means empty, whitespace-only, or a single empty paragraph (the shapes WordPress leaves behind for
 * a never-filled page). Any real block or any non-whitespace text marks it as user content (not blank), so
 * a kit apply can never overwrite real work (spec 041 FR-006). No WordPress — unit-testable headlessly.
 */
final class PageContent
{
    public function isBlank(string $content): bool
    {
        if (trim($content) === '') {
            return true;
        }

        // Strip empty-paragraph blocks (with or without the block comment wrapper); whatever remains decides.
        $stripped = preg_replace(
            [
                '/<!--\s*wp:paragraph\s*-->\s*<p>\s*<\/p>\s*<!--\s*\/wp:paragraph\s*-->/i',
                '/<p>\s*<\/p>/i',
            ],
            '',
            $content,
        );

        return trim((string) $stripped) === '';
    }
}
