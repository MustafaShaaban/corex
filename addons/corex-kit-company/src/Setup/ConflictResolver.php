<?php

/**
 * @package Corex\Kit
 */

declare(strict_types=1);

namespace Corex\Kit\Setup;

use Corex\Provisioning\PageDisposition;

defined('ABSPATH') || exit;

/**
 * Resolves operator conflict choices into final page dispositions for the setup apply (spec 068
 * FR-139, FR-143). Only pages that already hold user content (a SKIP with reason `user_content`)
 * are choosable. The critical safety rule: the DEFAULT for any conflicting page is Keep Mine, so
 * existing content is never overwritten silently — a Replace or a suffixed create only happens from
 * a deliberate, explicit choice. Pure and unit-testable; it performs no WordPress writes itself.
 */
final class ConflictResolver
{
    /**
     * @param list<PageDisposition> $dispositions the base classification from the planner
     * @param array<string,string>  $choices      slug => keep|replace|suffix (absent = keep)
     * @param array<string,bool>    $takenSlugs   slug => already-taken, for suffix collision avoidance
     *
     * @return list<PageDisposition>
     */
    public function resolve(array $dispositions, array $choices, array $takenSlugs = []): array
    {
        $resolved = [];

        foreach ($dispositions as $disposition) {
            if (! $this->isConflict($disposition)) {
                $resolved[] = $disposition;

                continue;
            }

            $resolved[] = match ($choices[$disposition->slug] ?? 'keep') {
                'replace' => new PageDisposition(
                    $disposition->slug,
                    $disposition->title,
                    PageDisposition::REPLACE,
                    'operator_replace',
                ),
                'suffix' => new PageDisposition(
                    $disposition->slug,
                    $disposition->title,
                    PageDisposition::SUFFIX,
                    'operator_suffix',
                    $this->suffixedSlug($disposition->slug, $takenSlugs),
                ),
                // Keep Mine (and any unknown choice): leave the operator's page untouched.
                default => $disposition,
            };
        }

        return $resolved;
    }

    /**
     * The pages an operator can make a conflict choice about: existing pages holding user content.
     *
     * @param list<PageDisposition> $dispositions
     *
     * @return list<array{slug:string,title:string}>
     */
    public function conflicts(array $dispositions): array
    {
        $conflicts = [];

        foreach ($dispositions as $disposition) {
            if ($this->isConflict($disposition)) {
                $conflicts[] = ['slug' => $disposition->slug, 'title' => $disposition->title];
            }
        }

        return $conflicts;
    }

    private function isConflict(PageDisposition $disposition): bool
    {
        return $disposition->action === PageDisposition::SKIP && $disposition->reason === 'user_content';
    }

    /**
     * @param array<string,bool> $takenSlugs
     */
    private function suffixedSlug(string $slug, array $takenSlugs): string
    {
        $suffix    = 2;
        $candidate = $slug . '-' . $suffix;

        while (! empty($takenSlugs[$candidate])) {
            $suffix++;
            $candidate = $slug . '-' . $suffix;
        }

        return $candidate;
    }
}
