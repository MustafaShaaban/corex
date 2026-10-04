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
 * - applying the mode already declared changes nothing and is reported as such.
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
    ) {
    }

    public function apply(ModeChangeRequest $request): ModeChangeResult
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
