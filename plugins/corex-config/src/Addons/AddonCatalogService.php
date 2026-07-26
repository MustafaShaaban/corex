<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Addons;

use Corex\Foundation\AddonStatus;

defined('ABSPATH') || exit;

/**
 * Pure add-on catalog projection (spec 068). It turns the already-resolved {@see AddonView}
 * list into the truthful summary counts shown on the Add-ons screen and the Overview, plus the
 * honest missing-package installation path. It never fabricates an update count or a remote
 * source — CoreX ships no update-checker, so updates are reported as untracked, and installing a
 * missing package is a local, manual action. WordPress-free, so it is unit-testable.
 */
final class AddonCatalogService
{
    public const FILTER_ALL           = 'all';
    public const FILTER_ACTIVE        = 'active';
    public const FILTER_INACTIVE      = 'inactive';
    public const FILTER_NOT_INSTALLED = 'not-installed';

    /**
     * Narrows the catalog to one state so the grid can be scanned. The buckets are mutually
     * exclusive and cover every view, and they are keyed on {@see AddonView::status()} — the same
     * resolution that prints the badge on each card, so filtering by "Active" returns exactly the
     * cards badged Active and nothing that merely looks like it.
     *
     * "Inactive" is deliberately *installed but not running*, which is a state an admin can act on
     * (toggle it, satisfy a dependency, turn on a flag). A package that is not on disk is a
     * different problem with a different fix, so it gets its own bucket instead of being blurred
     * into this one.
     *
     * @param list<AddonView> $views
     *
     * @return list<AddonView>
     */
    public function filter(array $views, string $filter): array
    {
        if ($filter === self::FILTER_ALL) {
            return array_values($views);
        }

        return array_values(array_filter($views, static function (AddonView $view) use ($filter): bool {
            $running = $view->status() === AddonStatus::Active;

            return match ($filter) {
                self::FILTER_ACTIVE        => $running,
                self::FILTER_INACTIVE      => $view->installed && ! $running,
                self::FILTER_NOT_INSTALLED => ! $view->installed,
                // An unknown filter must not silently hide add-ons; show everything.
                default => true,
            };
        }));
    }

    /**
     * How many add-ons each filter would show, so a filter control can carry a real number and an
     * admin can see a bucket is empty before clicking into it.
     *
     * @param list<AddonView> $views
     *
     * @return array<string,int> filter key => count
     */
    public function counts(array $views): array
    {
        $counts = [];

        foreach ([self::FILTER_ALL, self::FILTER_ACTIVE, self::FILTER_INACTIVE, self::FILTER_NOT_INSTALLED] as $filter) {
            $counts[$filter] = count($this->filter($views, $filter));
        }

        return $counts;
    }

    /**
     * @param list<AddonView> $views
     *
     * @return array{active:int,installed:int,total:int,siteKits:int,updatesTracked:bool}
     */
    public function summary(array $views): array
    {
        $active    = 0;
        $installed = 0;
        $siteKits  = 0;

        foreach ($views as $view) {
            if ($view->active) {
                $active++;
            }
            if ($view->installed) {
                $installed++;
            }
            if (str_starts_with($view->addon->slug, 'corex-kit-')) {
                $siteKits++;
            }
        }

        return [
            'active'    => $active,
            'installed' => $installed,
            'total'     => count($views),
            'siteKits'  => $siteKits,
            // No update-checker source exists; the count is honestly untracked, never faked.
            'updatesTracked' => false,
        ];
    }

    /**
     * Add-ons present in the catalog whose plugin package is not installed on disk, each with the
     * real resolution path. Empty when every catalogued add-on is installed.
     *
     * @param list<AddonView> $views
     *
     * @return list<array{slug:string,label:string,guidance:string}>
     */
    public function missingPackages(array $views): array
    {
        $missing = [];

        foreach ($views as $view) {
            if ($view->installed) {
                continue;
            }

            $missing[] = [
                'slug'  => $view->addon->slug,
                'label' => $view->addon->label,
                'guidance' => sprintf(
                    /* translators: %s: add-on plugin slug */
                    __('Add the %s plugin package to wp-content/plugins, then enable it here.', 'corex'),
                    $view->addon->slug,
                ),
            ];
        }

        return $missing;
    }
}
