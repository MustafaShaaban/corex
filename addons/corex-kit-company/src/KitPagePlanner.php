<?php

/**
 * @package Corex\Kit
 */

declare(strict_types=1);

namespace Corex\Kit;

defined('ABSPATH') || exit;

/**
 * Decides which of a kit's declared pages to create — those whose slug does not already
 * exist — so applying a kit is idempotent (re-applying never duplicates). Pure: the caller
 * supplies the existing slugs; this never touches WordPress (spec 031).
 */
final class KitPagePlanner
{
    /**
     * @param list<array{title:string,slug:string,content:string,front?:bool}> $declared
     * @param list<string>                                                      $existingSlugs
     *
     * @return list<array{title:string,slug:string,content:string,front?:bool}>
     */
    public function toCreate(array $declared, array $existingSlugs): array
    {
        return array_values(array_filter(
            $declared,
            static fn (array $page): bool => ! in_array($page['slug'], $existingSlugs, true),
        ));
    }
}
