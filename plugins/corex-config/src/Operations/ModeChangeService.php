<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

use Corex\Operations\OperationResult;

/**
 * The rules for changing the operations mode, in one place (spec 101).
 *
 * They lived inside {@see OperationsModeController::handle()} while the Operations screen was the
 * only way to change the mode. A command line that can change it too must be held to the same
 * confirmations and write the same history, and two copies of a rule is how one of them stops
 * being enforced. So the controller and the command both hand a {@see ModeChangeRequest} to this
 * service and report what comes back; neither decides anything.
 *
 * The rules are the ones the controller already had, moved here unaltered:
 *
 * - an unknown mode is refused;
 * - production is a launch — it needs its typed phrase and goes through
 *   {@see ProductionLaunchService}, which evaluates readiness and records the override;
 * - a mode that needs an acknowledgement is not applied without one;
 * - applying the mode already declared changes nothing and is reported as such;
 * - leaving Coming soon, by any route, removes the preview link (spec 101, FR-012a).
 *
 * Capability and nonce are not here. They belong to whichever boundary received the request.
 */
final class ModeChangeService
{
    public function __construct(
        private readonly OperationsMode $modes,
        private readonly OperationsModeStore $store,
        private readonly ProductionReadinessSnapshotFactory $readiness,
        private readonly ProductionLaunchService $productionLaunch,
        private readonly PreviewAccess $preview,
    ) {
    }

    public function apply(ModeChangeRequest $request): ModeChangeResult
    {
        $before = $this->store->current();
        $result = $this->change($request);

        // Asked of the store, not inferred from the result: a launch reaches the store through
        // ProductionLaunchService and the other modes reach it directly, and what matters is
        // whether the site has in fact left the mode — by whichever route, and only if it has.
        // The link is not recorded as revoked: nobody revoked it. The mode change beside it in
        // the history is the record.
        if ($before === OperationsMode::COMING_SOON && $this->store->current() !== OperationsMode::COMING_SOON) {
            $this->preview->clear();
        }

        return $result;
    }

    private function change(ModeChangeRequest $request): ModeChangeResult
    {
        if (! $this->modes->isValid($request->mode)) {
            return ModeChangeResult::invalid();
        }

        if ($request->mode === OperationsMode::PRODUCTION) {
            return $this->launchProduction($request);
        }

        if ($this->modes->requiresConfirmation($request->mode) && ! $request->acknowledged) {
            return ModeChangeResult::needsAcknowledgement($request->mode);
        }

        // Asked before applying, because `set()` deliberately makes a no-change indistinguishable
        // from a change in its return value — both answer "what is the mode now". The caller
        // needs the other answer: whether anything happened.
        $wasAlreadyInForce = $this->store->current() === $request->mode && $this->store->isDeclared();

        $applied = $this->store->set($request->mode, $request->actorId);

        return $wasAlreadyInForce
            ? ModeChangeResult::unchanged($applied)
            : ModeChangeResult::saved($applied);
    }

    private function launchProduction(ModeChangeRequest $request): ModeChangeResult
    {
        // Readiness is evaluated before the phrase is checked, as the controller did it. The
        // evaluation announces itself, which is what lets the Notification Center reconcile
        // readiness warnings — so a submit that still owes the phrase refreshes them too.
        $snapshot = $this->readiness->fromCurrentSite($request->now);
        $preview  = $this->productionLaunch->preview($snapshot, $request->actorId, $request->now);

        if ($request->phrase !== ProductionLaunchService::REQUIRED_PHRASE) {
            return ModeChangeResult::needsPhrase(OperationsMode::PRODUCTION);
        }

        $result = $this->productionLaunch->apply(new ProductionLaunchRequest(
            snapshot: $snapshot,
            actorId: $request->actorId,
            now: $request->now,
            override: new ProductionLaunchOverride($preview->confirmation, $request->phrase),
        ));

        return $result->state === OperationResult::STATE_COMPLETED
            ? ModeChangeResult::saved(OperationsMode::PRODUCTION)
            : ModeChangeResult::blocked(OperationsMode::PRODUCTION);
    }
}
