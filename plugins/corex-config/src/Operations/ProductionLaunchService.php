<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

use Corex\Operations\OperationResult;
use Corex\Support\Uuid;
use DateTimeImmutable;

/**
 * The Production launch gate (spec 068, spec 124 US-OPS-05).
 *
 * Going live with nothing blocking takes the phrase PRODUCTION. Going live past open blockers takes
 * a different phrase, GO LIVE ANYWAY, so that nobody overrides a blocker by reflex, and it is
 * recorded as an override: the history row names every blocker it went past and the activity log
 * gets a security event. The blockers are the ones found when the request was submitted, not when
 * the page was drawn. The phrases are never translated: they are typed as written in every language.
 */
final class ProductionLaunchService
{
    public const REQUIRED_PHRASE = 'PRODUCTION';
    public const OVERRIDE_PHRASE = 'GO LIVE ANYWAY';

    public function __construct(private readonly OperationsModeStore $store)
    {
    }

    /** The phrase going live needs with this readiness. */
    public static function phraseFor(ReadinessSnapshot $snapshot): string
    {
        return $snapshot->hasBlockingChecks() ? self::OVERRIDE_PHRASE : self::REQUIRED_PHRASE;
    }

    public function apply(ProductionLaunchRequest $request): OperationResult
    {
        if ($request->phrase !== self::phraseFor($request->snapshot)) {
            return $this->refused($request);
        }

        $overridden = $request->snapshot->blockingLabels();
        $this->store->set(OperationsMode::PRODUCTION, $request->actorId, $overridden);

        if ($overridden !== []) {
            /**
             * Fires after the site went live past open readiness blockers (spec 124, US-OPS-05).
             *
             * @param int          $actorId    Who went live.
             * @param list<string> $overridden The blockers it went past, by name.
             */
            do_action('corex_production_override', $request->actorId, $overridden);
        }

        return new OperationResult(
            operationId: Uuid::v4(),
            state: OperationResult::STATE_COMPLETED,
            message: __('Production mode enabled.', 'corex'),
            errors: [],
            affectedIds: ['operations-mode:production'],
            startedAt: $request->now,
            finishedAt: $request->now,
        );
    }

    private function refused(ProductionLaunchRequest $request): OperationResult
    {
        $blocked = $request->snapshot->hasBlockingChecks();

        return new OperationResult(
            operationId: Uuid::v4(),
            state: OperationResult::STATE_BLOCKED,
            message: $blocked
                ? __('Production mode is blocked by readiness checks.', 'corex')
                : __('Going live needs the typed confirmation.', 'corex'),
            errors: [[
                'code'    => $blocked ? 'readiness_blocking' : 'phrase_required',
                'message' => sprintf(
                    /* translators: %s: the phrase to type, e.g. GO LIVE ANYWAY. Never translated. */
                    __('Type %s to go live.', 'corex'),
                    self::phraseFor($request->snapshot),
                ),
            ]],
            affectedIds: [],
            startedAt: $request->now,
            finishedAt: $request->now,
        );
    }
}
