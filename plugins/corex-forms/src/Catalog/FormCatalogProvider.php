<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Catalog;

defined('ABSPATH') || exit;

/**
 * The seam for a form that is neither a visual flow nor a `FormRegistry` class — a form owned by
 * some other module or plugin that still wants to be findable in CoreX's screens.
 *
 * Entries are returned as plain arrays, not value objects, precisely because the implementer is
 * outside our control: the catalog normalises what comes back and drops what it cannot use, so a
 * careless provider cannot put a broken row on somebody's screen.
 */
interface FormCatalogProvider
{
    /**
     * Raw catalog entries. Recognised keys: `slug` (required), `label`, `field_count`, `active`,
     * `submission_count`, `capability`, `fields`.
     *
     * @return list<array<string,mixed>>
     */
    public function formCatalogEntries(): array;
}
