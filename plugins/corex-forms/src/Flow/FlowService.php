<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use DomainException;
use Corex\Support\Uuid;

/**
 * Orchestrates optimistic draft snapshots and flow lifecycle transitions.
 */
final readonly class FlowService
{
    public function __construct(
        private FlowRepository $flows,
        private FlowConfigurationValidator $validator,
    ) {
    }

    /** @return array{flow:Flow,version:FlowVersion} */
    public function create(NewFlowDraft $draft): array
    {
        $flow = $this->flows->save(new Flow(
            id: 0,
            uuid: Uuid::v4(),
            slug: $draft->slug,
            name: $draft->name,
            description: $draft->description,
            state: Flow::STATE_DRAFT,
            ownerId: $draft->ownerId,
            placementType: $draft->placementType,
            placementId: $draft->placementId,
            currentDraftVersion: 1,
            publishedVersion: 0,
            testMode: $draft->testMode,
            createdBy: $draft->actorId,
            updatedBy: $draft->actorId,
            createdAt: $draft->occurredAt,
            updatedAt: $draft->occurredAt,
        ));
        $version = $this->flows->appendVersion(new FlowVersion(
            id: 0,
            flowId: $flow->id,
            versionNumber: 1,
            configuration: $draft->configuration,
            createdBy: $draft->actorId,
            createdAt: $draft->occurredAt,
        ));

        return ['flow' => $flow, 'version' => $version];
    }

    /** @return list<Flow> */
    public function all(): array
    {
        return $this->flows->all();
    }

    /** @return list<Flow> */
    public function search(string $query, string $state): array
    {
        return array_values(array_filter(
            $this->flows->all(),
            static function (Flow $flow) use ($query, $state): bool {
                $matchesState = $state === '' || $flow->state === $state;
                $matchesQuery = $query === ''
                    || stripos($flow->name, $query) !== false
                    || stripos($flow->slug, $query) !== false;

                return $matchesState && $matchesQuery;
            },
        ));
    }

    public function get(int $flowId): Flow
    {
        return $this->requireFlow($flowId);
    }

    /** @return list<FlowVersion> */
    public function versions(int $flowId): array
    {
        $this->requireFlow($flowId);

        return $this->flows->versions($flowId);
    }

    public function currentVersion(Flow $flow): FlowVersion
    {
        return $this->requireVersion($flow, $flow->currentDraftVersion);
    }

    public function publishedVersion(Flow $flow): FlowVersion
    {
        if ($flow->state !== Flow::STATE_PUBLISHED || $flow->publishedVersion < 1) {
            throw new DomainException(__('Flow is not published.', 'corex'));
        }
        $version = $this->flows->findVersion($flow->id, $flow->publishedVersion);
        if ($version === null) {
            throw new DomainException(__('Published flow version was not found.', 'corex'));
        }

        return $version;
    }

    public function saveDraft(FlowDraftUpdate $update): FlowVersion
    {
        $flow    = $this->requireFlow($update->flowId);
        $current = $this->requireVersion($flow, $update->expectedVersion);
        if ($current->checksum !== $update->expectedChecksum) {
            throw new FlowConflictException(__('The flow draft changed before this save.', 'corex'));
        }

        $version = $this->flows->appendVersion(new FlowVersion(
            id: 0,
            flowId: $flow->id,
            versionNumber: $flow->currentDraftVersion + 1,
            configuration: $update->configuration,
            createdBy: $update->actorId,
            createdAt: $update->occurredAt,
        ));
        $this->flows->save($flow->withDraftVersion($version->versionNumber, $update->actorId, $update->occurredAt));

        return $version;
    }

    public function publish(FlowTransition $transition): Flow
    {
        $flow    = $this->requireFlow($transition->flowId);
        $version = $this->requireVersion($flow, $transition->expectedDraftVersion);
        $this->validator->validate($version->configuration);

        return $this->flows->save($flow->withState(
            Flow::STATE_PUBLISHED,
            $version->versionNumber,
            $transition->actorId,
            $transition->occurredAt,
        ));
    }

    public function unpublish(FlowTransition $transition): Flow
    {
        return $this->changeState($transition, Flow::STATE_DRAFT);
    }

    public function close(FlowTransition $transition): Flow
    {
        return $this->changeState($transition, Flow::STATE_CLOSED);
    }

    public function expire(FlowTransition $transition): Flow
    {
        return $this->changeState($transition, Flow::STATE_EXPIRED);
    }

    public function preview(int $flowId): FlowVersion
    {
        $flow = $this->requireFlow($flowId);

        return $this->requireVersion($flow, $flow->currentDraftVersion);
    }

    private function changeState(FlowTransition $transition, string $state): Flow
    {
        $flow = $this->requireFlow($transition->flowId);
        $this->assertCurrentVersion($flow, $transition->expectedDraftVersion);

        return $this->flows->save($flow->withState(
            $state,
            $flow->publishedVersion,
            $transition->actorId,
            $transition->occurredAt,
        ));
    }

    private function requireFlow(int $flowId): Flow
    {
        $flow = $this->flows->find($flowId);
        if ($flow === null) {
            throw new DomainException(__('Flow was not found.', 'corex'));
        }

        return $flow;
    }

    private function requireVersion(Flow $flow, int $expectedVersion): FlowVersion
    {
        $this->assertCurrentVersion($flow, $expectedVersion);
        $version = $this->flows->findVersion($flow->id, $expectedVersion);
        if ($version === null) {
            throw new DomainException(__('Flow draft version was not found.', 'corex'));
        }

        return $version;
    }

    private function assertCurrentVersion(Flow $flow, int $expectedVersion): void
    {
        if ($flow->currentDraftVersion !== $expectedVersion) {
            throw new FlowConflictException(__('The flow draft version is stale.', 'corex'));
        }
    }

}
