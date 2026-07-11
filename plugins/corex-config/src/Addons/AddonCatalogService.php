<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Addons;

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
