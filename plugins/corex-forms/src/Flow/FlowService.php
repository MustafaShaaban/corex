<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use DateInterval;
use DateTimeImmutable;
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
        private ?FlowNoticeText $noticeText = null,
        private ?FlowStatusResolver $statuses = null,
    ) {
    }

    /** How long a page keeps the version it was printed with: the life of its security token. */
    private const VISITOR_VERSION_GRACE = 'P1D';

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

    /**
     * The flows a list shows, each beside the draft it would open, narrowed by name or slug and by
     * state. A stored flow that cannot be read is not in it; the repository says which in the log.
     *
     * @return list<array{flow:Flow,version:FlowVersion}>
     */
    public function listing(string $query, string $state): array
    {
        return array_values(array_filter(
            $this->flows->allWithDraft(),
            function (array $entry) use ($query, $state): bool {
                $flow = $entry['flow'];
                $matchesState = $state === '' || $this->listState($flow) === $state;
                $matchesQuery = $query === ''
                    || stripos($flow->name, $query) !== false
                    || stripos($flow->slug, $query) !== false;

                return $matchesState && $matchesQuery;
            },
        ));
    }

    /**
     * The state the list files a flow under: a flow whose dates or cap closed it is "expired",
     * a scheduled one is "published", whatever is stored (spec 122, FR-031).
     */
    private function listState(Flow $flow): string
    {
        if ($this->statuses === null || $flow->state !== Flow::STATE_PUBLISHED) {
            return $flow->state;
        }

        return $this->statuses->at($flow, new DateTimeImmutable('now'))->state === FlowStatus::EXPIRED
            ? Flow::STATE_EXPIRED
            : Flow::STATE_PUBLISHED;
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

    /**
     * The version visitors last saw: the published one, kept when the flow is closed or expired so
     * a closed form can still name its heading and read a late submission's shape.
     */
    public function liveVersion(Flow $flow): FlowVersion
    {
        $version = $flow->publishedVersion > 0 && $flow->state !== Flow::STATE_DRAFT
            ? $this->flows->findVersion($flow->id, $flow->publishedVersion)
            : null;
        if ($version === null) {
            throw new DomainException(__('Flow is not published.', 'corex'));
        }

        return $version;
    }

    /**
     * The version a visitor's submission is judged by (spec 122, FR-007; DECISIONS #470).
     *
     * The version the page was printed with, when that version was live at some moment in the last
     * day: the visitor answered its questions. Its spam protection is replaced by the live one's, so
     * an old page cannot bring back a weaker check. Any other number means the live version.
     */
    public function versionForVisitor(Flow $flow, ?int $seen, DateTimeImmutable $now): FlowVersion
    {
        $live = $this->publishedVersion($flow);
        $since = $now->sub(new DateInterval(self::VISITOR_VERSION_GRACE));
        if ($seen === null || $seen === $live->versionNumber || ! $this->wasLiveSince($flow, $seen, $since)) {
            return $live;
        }
        $version = $this->flows->findVersion($flow->id, $seen);

        return $version === null ? $live : $version->withProtection($live->configuration->protection);
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
            throw new FlowConflictException(self::conflict());
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

    /**
     * Makes the saved draft the live version: a first publication, or "Publish changes" on a flow
     * that is already live, which stays on the site throughout (spec 122, FR-001).
     */
    public function publish(FlowTransition $transition): Flow
    {
        $flow    = $this->requireFlow($transition->flowId);
        $version = $this->requireVersion($flow, $transition->expectedDraftVersion);
        if ($flow->state === Flow::STATE_PUBLISHED && $version->versionNumber === $flow->publishedVersion) {
            throw new DomainException(__('Live version is up to date.', 'corex'));
        }
        $this->validator->validate($version->configuration);

        return $this->flows->save($flow->publish($version->versionNumber, $transition->actorId, $transition->occurredAt));
    }

    /**
     * Makes an earlier version the saved draft, as a new version. It never publishes: what is live
     * stays live until someone presses Publish changes (spec 122, FR-005).
     */
    public function copyIntoDraft(int $versionNumber, FlowDraftUpdate $update): FlowVersion
    {
        $source = $this->flows->findVersion($this->requireFlow($update->flowId)->id, $versionNumber);
        if ($source === null) {
            throw new DomainException(__('Flow version was not found.', 'corex'));
        }

        return $this->saveDraft(new FlowDraftUpdate(
            $update->flowId,
            $source->configuration,
            $update->expectedVersion,
            $update->expectedChecksum,
            $update->actorId,
            $update->occurredAt,
        ));
    }

    /** Saves when the flow takes answers and what it says when it does not. Takes effect at once. */
    public function saveAvailability(int $flowId, FlowAvailability $availability, int $actorId, DateTimeImmutable $now): Flow
    {
        $availability->assertValid();
        $flow = $this->flows->save($this->requireFlow($flowId)->withAvailability($availability, $actorId, $now));
        $this->noticeText?->register($flow);

        return $flow;
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
            throw new FlowConflictException(self::conflict());
        }
    }

    /** What a person whose copy of the draft is out of date reads (spec 122, FR-003). */
    public static function conflict(): string
    {
        return __('This flow changed since you opened it. Reload to see the latest draft.', 'corex');
    }

    private function wasLiveSince(Flow $flow, int $versionNumber, DateTimeImmutable $since): bool
    {
        $history = $flow->publicationHistory();
        foreach ($history as $index => $publication) {
            $replacedAt = isset($history[$index + 1]) ? $history[$index + 1]->publishedAt : null;
            if ($publication->versionNumber === $versionNumber && ($replacedAt === null || $replacedAt >= $since)) {
                return true;
            }
        }

        return false;
    }

}
