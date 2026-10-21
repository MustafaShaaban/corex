<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Reset;

defined('ABSPATH') || exit;

use Corex\Provisioning\PageDisposition;

/**
 * Turns a request + the gathered Corex footprint into an ordered plan of actions — purely,
 * with no WordPress or database access, so the plan (and the safety property) is
 * unit-testable. A full reset collapses to a single DB-wipe action; a soft reset enumerates
 * the granular steps in a safe order (deactivate add-ons, then put back or remove the pages a kit
 * touched and the reading settings it changed, then delete options — so the records and markers read
 * during the earlier steps stay valid).
 */
final class ResetPlanner
{
    public function plan(ResetRequest $request, ResetInventory $inventory): ResetPlan
    {
        if ($request->isFull()) {
            return new ResetPlan([
                new ResetAction(
                    ResetAction::DB_WIPE,
                    'database',
                    'Wipe the database and restore a fresh Corex starter (theme only, no add-ons).',
                ),
            ]);
        }

        return new ResetPlan([
            ...$this->deactivations($inventory),
            ...$this->pageActions($inventory),
            ...$this->optionDeletions($inventory),
        ]);
    }

    /**
     * @return list<ResetAction>
     */
    private function deactivations(ResetInventory $inventory): array
    {
        return array_map(
            static fn (string $file): ResetAction => new ResetAction(
                ResetAction::DEACTIVATE_ADDON,
                $file,
                sprintf('Deactivate add-on: %s', $file),
            ),
            $inventory->addonPlugins,
        );
    }

    /**
     * One action per page a kit touched, then the legacy demo page when it is not one of them, then the
     * reading settings. A page the owner had is restored, emptied or kept, never removed (spec 115).
     *
     * @return list<ResetAction>
     */
    private function pageActions(ResetInventory $inventory): array
    {
        $actions = [];
        $seen    = [];

        foreach ($inventory->pages as $page) {
            if (isset($seen[$page->id])) {
                continue;
            }
            $seen[$page->id] = true;
            $actions[]       = $this->pageAction($page);
        }

        if ($inventory->demoPageId !== null && ! isset($seen[$inventory->demoPageId])) {
            $actions[] = new ResetAction(
                ResetAction::REMOVE_DEMO,
                (string) $inventory->demoPageId,
                sprintf('Page #%d: created by the setup demo, will be removed.', $inventory->demoPageId),
            );
        }

        if ($inventory->hasReadingRecord) {
            $actions[] = new ResetAction(
                ResetAction::RESTORE_READING,
                'reading',
                'Front-page settings: will be put back as they were before the kit.',
            );
        }

        return $actions;
    }

    private function pageAction(ResetPage $page): ResetAction
    {
        $name = sprintf('Page "%s" (#%d)', $page->title, $page->id);

        if (! $page->wasTheOwners()) {
            return new ResetAction(ResetAction::REMOVE_DEMO, (string) $page->id, $name . ': created, will be removed.');
        }

        $replaced = $page->persistedAs === PageDisposition::PERSISTED_REPLACED;

        if ($page->hasRecord) {
            return new ResetAction(
                ResetAction::RESTORE_PAGE,
                (string) $page->id,
                $name . ($replaced
                    ? ': replaced, will be restored to the version recorded before the kit.'
                    : ': adopted, will be emptied and restored as it was recorded before the kit.'),
            );
        }

        return $replaced
            ? new ResetAction(ResetAction::KEEP_PAGE, (string) $page->id, $name . ': replaced, kept: no earlier version recorded.')
            : new ResetAction(ResetAction::EMPTY_PAGE, (string) $page->id, $name . ': adopted, will be emptied.');
    }

    /**
     * @return list<ResetAction>
     */
    private function optionDeletions(ResetInventory $inventory): array
    {
        return array_map(
            static fn (string $key): ResetAction => new ResetAction(
                ResetAction::DELETE_OPTION,
                $key,
                sprintf('Delete option: %s', $key),
            ),
            $inventory->optionKeys,
        );
    }
}
