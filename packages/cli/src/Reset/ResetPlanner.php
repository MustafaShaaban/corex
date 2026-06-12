<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Reset;

defined('ABSPATH') || exit;

/**
 * Turns a request + the gathered Corex footprint into an ordered plan of actions — purely,
 * with no WordPress or database access, so the plan (and the safety property) is
 * unit-testable. A full reset collapses to a single DB-wipe action; a soft reset enumerates
 * the granular steps in a safe order (deactivate add-ons, then remove seeded demo content,
 * then delete options — so flag/marker reads during the earlier steps stay valid).
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
            ...$this->demoRemoval($inventory),
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
     * @return list<ResetAction>
     */
    private function demoRemoval(ResetInventory $inventory): array
    {
        $ids = $inventory->pageIds;
        if ($inventory->demoPageId !== null) {
            $ids[] = $inventory->demoPageId;
        }

        return array_map(
            static fn (int $id): ResetAction => new ResetAction(
                ResetAction::REMOVE_DEMO,
                (string) $id,
                sprintf('Remove the seeded kit page (#%d) and revert the front-page settings if needed.', $id),
            ),
            array_values(array_unique($ids)),
        );
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
