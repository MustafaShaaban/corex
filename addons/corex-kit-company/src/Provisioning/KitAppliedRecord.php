<?php

/**
 * @package Corex\Kit
 */

declare(strict_types=1);

namespace Corex\Kit\Provisioning;

use Corex\Kit\BlueprintActivator;
use Corex\Provisioning\ApplyOutcome;

defined('ABSPATH') || exit;

/**
 * Whether a kit has been applied, one rule for every way of applying it (spec 118, US-OVR-09): the
 * Add-ons prompt and the Setup Wizard both record an apply here, and only one that wrote every page
 * it was to create or fill. A site whose wizard applied the kit before this record was kept is
 * recognised from its pages: one of the kit's pages still carrying the kit's record means it was
 * applied. A reset removes the record from those pages and CoreX's options, so it reads as not
 * applied again.
 */
class KitAppliedRecord
{
    public const OPTION = 'corex_kit_applied';

    /**
     * Records an apply of `$kit`, when it completed. Returns whether it was recorded.
     */
    public function record(string $kit, ApplyOutcome $outcome): bool
    {
        if (! $outcome->isComplete()) {
            return false;
        }

        $applied = $this->names();
        if (! in_array($kit, $applied, true)) {
            $applied[] = $kit;
            update_option(self::OPTION, array_values($applied));
        }

        return true;
    }

    /**
     * @param list<string> $pageSlugs the slugs of the pages the kit declares
     */
    public function isApplied(string $kit, array $pageSlugs): bool
    {
        return in_array($kit, $this->names(), true) || $this->hasRecordedPage($pageSlugs);
    }

    /** @param list<string> $pageSlugs */
    private function hasRecordedPage(array $pageSlugs): bool
    {
        foreach ($pageSlugs as $slug) {
            $page = get_page_by_path($slug);
            if ($page instanceof \WP_Post && (string) get_post_meta($page->ID, BlueprintActivator::PAGE_META, true) !== '') {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function names(): array
    {
        return array_values(array_map('strval', (array) get_option(self::OPTION, [])));
    }
}
