<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

/**
 * Where the one preview-link record of a site is kept (spec 101).
 *
 * There is at most one: a keyed hash of the link's token, when it was issued and by whom. It is
 * replaced or removed, never accumulated. The rules are {@see PreviewAccess}'s; this is only the
 * shelf, so those rules can be tested against one held in memory.
 */
interface PreviewAccessStore
{
    /**
     * @return array{hash:string,issued:int,user:int}|null Null when no link exists.
     */
    public function read(): ?array;

    public function write(string $hash, int $issued, int $user): void;

    public function delete(): void;
}
