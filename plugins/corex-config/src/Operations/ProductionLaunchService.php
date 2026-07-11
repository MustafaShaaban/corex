<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

use Corex\Operations\Confirmation;
use Corex\Operations\OperationResult;
use Corex\Support\Uuid;
use DateTimeImmutable;
use DomainException;

/**
 * Applies the Production launch safety gate over a shared readiness snapshot.
 */
final class ProductionLaunchService
{
    public const OPERATION_KIND = 'operations.production.launch';
    public const REQUIRED_PHRASE = 'PRODUCTION';

    /** @var array<string,bool> */
    private array $usedConfirmations = [];

    public function __construct(private readonly OperationsModeStore $store)
    {
    }

    public function preview(
        ReadinessSnapshot $snapshot,
        int $actorId,
        DateTimeImmutable $now,
    ): ProductionLaunchPreview {
        return new ProductionLaunchPreview(
            snapshot: $snapshot,
            confirmation: new Confirmation(
                operationKind: self::OPERATION_KIND,
                targetHash: $snapshot->targetHash(),
                actorId: $actorId,
                expiresAt: $now->modify('+5 minutes'),
                requiredPhrase: self::REQUIRED_PHRASE,
            ),
        );
    }

    public function apply(ProductionLaunchRequest $request): OperationResult
    {
        if ($request->snapshot->hasBlockingChecks() && ! $this->hasValidOverride($request)) {
            return $this->blockedResult($request->now);
        }

        $this->consumeOverride($request);
        $this->store->set(OperationsMode::PRODUCTION, $request->actorId);

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

    private function hasValidOverride(ProductionLaunchRequest $request): bool
    {
        $override = $request->override;
        if ($override === null) {
            return false;
        }

        return $override->confirmation->verify(
            operationKind: self::OPERATION_KIND,
            targetHash: $request->snapshot->targetHash(),
            actorId: $request->actorId,
            phrase: $override->phrase,
            now: $request->now,
        );
    }

    private function consumeOverride(ProductionLaunchRequest $request): void
    {
        if ($request->override === null) {
            return;
        }

        $key = implode('|', [
            $request->override->confirmation->operationKind,
            (string) $request->override->confirmation->actorId,
            $request->override->confirmation->targetHash,
        ]);
        if (isset($this->usedConfirmations[$key])) {
            throw new DomainException('Production launch confirmation has already been used.');
        }

        $request->override->confirmation->use($request->now);
        $this->usedConfirmations[$key] = true;
    }

    private function blockedResult(DateTimeImmutable $now): OperationResult
    {
        return new OperationResult(
            operationId: Uuid::v4(),
            state: OperationResult::STATE_BLOCKED,
            message: __('Production mode is blocked by readiness checks.', 'corex'),
            errors: [
                [
                    'code'    => 'readiness_blocking',
                    'message' => __('Resolve blocking readiness checks or type PRODUCTION to override.', 'corex'),
                ],
            ],
            affectedIds: [],
            startedAt: $now,
            finishedAt: $now,
        );
    }
}
