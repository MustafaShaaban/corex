<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Catalog;

defined('ABSPATH') || exit;

/**
 * How many submissions each form has received, keyed by slug.
 *
 * Deliberately one call returning the whole map rather than a per-form lookup: the catalog renders
 * every form at once, and a per-form query would put an N+1 on a screen that lists them all. A
 * boundary that cannot answer cheaply should not be wired in at all — the catalog then reports the
 * count as unavailable, which is honest, instead of printing a zero it did not measure.
 */
interface SubmissionCounts
{
    /** @return array<string,int> */
    public function perFormSlug(): array;
}
