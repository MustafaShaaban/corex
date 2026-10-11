<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

/**
 * What the release installer may ask of the database for its table copies (spec 107, FR-034,
 * FR-041).
 *
 * A table can be copied and a table can be dropped. There is no rename and no restore here on
 * purpose: a copy is kept for a person to read from, and nothing in an installation or in
 * going back is able to put one over a live table.
 */
interface ReleaseTableCopier
{
    /**
     * @return list<string> The tables whose name begins with the given text.
     */
    public function tablesStartingWith(string $prefix): array;

    public function exists(string $table): bool;

    /**
     * Make a table like another and holding the same rows.
     *
     * @return bool False when the copy could not be made whole. Nothing of it is left then.
     */
    public function copy(string $from, string $to): bool;

    public function drop(string $table): void;
}
