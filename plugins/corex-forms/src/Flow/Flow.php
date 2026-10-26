<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Immutable identity and lifecycle projection for one persisted flow.
 */
final readonly class Flow
{
    public const STATE_DRAFT = 'draft';
    public const STATE_PUBLISHED = 'published';
    public const STATE_CLOSED = 'closed';
    public const STATE_EXPIRED = 'expired';

    public const PLACEMENT_NONE = 'none';
    public const PLACEMENT_PAGE = 'page';
    public const PLACEMENT_POST = 'post';
    public const PLACEMENT_BLOCK = 'block';
    public const PLACEMENT_REGISTERED = 'registered';

    /**
     * This constructor is an immutable domain record. Its named arguments map
     * directly to the persisted Flow schema; none are service collaborators.
     */
    public function __construct(
        public int $id,
        public string $uuid,
        public string $slug,
        public string $name,
        public string $description,
        public string $state,
        public int $ownerId,
        public string $placementType,
        public ?int $placementId,
        public int $currentDraftVersion,
        public int $publishedVersion,
        public bool $testMode,
        public int $createdBy,
        public int $updatedBy,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?DateTimeImmutable $publishedAt = null,
        public ?DateTimeImmutable $closedAt = null,
        public ?DateTimeImmutable $expiresAt = null,
        /** @var list<FlowPublication> Each time a version was made live, oldest first (spec 122). */
        public array $publications = [],
        public FlowAvailability $availability = new FlowAvailability(),
    ) {
        if ($id < 0
            || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid) !== 1
            || preg_match('/^[a-z][a-z0-9-]*$/', $slug) !== 1
            || trim($name) === ''
            || $ownerId < 1
            || $createdBy < 1
            || $updatedBy < 1
            || ($placementId !== null && $placementId < 1)
        ) {
            throw new InvalidArgumentException('Flow identity and ownership values are invalid.');
        }
        if (! in_array($state, self::states(), true)) {
            throw new InvalidArgumentException(sprintf('Unsupported flow state: "%s".', $state));
        }
        if (! in_array($placementType, self::placementTypes(), true)) {
            throw new InvalidArgumentException(sprintf('Unsupported flow placement: "%s".', $placementType));
        }
        if ($currentDraftVersion < 1 || $publishedVersion < 0 || $publishedVersion > $currentDraftVersion) {
            throw new InvalidArgumentException('Flow version pointers are invalid.');
        }
        if ($state === self::STATE_PUBLISHED && $publishedVersion < 1) {
            throw new InvalidArgumentException('A published flow requires a published version.');
        }
        foreach ($publications as $publication) {
            if (! $publication instanceof FlowPublication) {
                throw new InvalidArgumentException('Flow publications must be FlowPublication values.');
            }
        }
    }

    /** @return list<string> */
    public static function states(): array
    {
        return [self::STATE_DRAFT, self::STATE_PUBLISHED, self::STATE_CLOSED, self::STATE_EXPIRED];
    }

    /** @return list<string> */
    public static function placementTypes(): array
    {
        return [
            self::PLACEMENT_NONE,
            self::PLACEMENT_PAGE,
            self::PLACEMENT_POST,
            self::PLACEMENT_BLOCK,
            self::PLACEMENT_REGISTERED,
        ];
    }

    public function canTransitionTo(string $state): bool
    {
        if (! in_array($state, self::states(), true) || $state === $this->state) {
            return false;
        }

        return match ($this->state) {
            self::STATE_DRAFT     => $state === self::STATE_PUBLISHED,
            self::STATE_PUBLISHED => in_array($state, [self::STATE_DRAFT, self::STATE_CLOSED, self::STATE_EXPIRED], true),
            self::STATE_CLOSED,
            self::STATE_EXPIRED   => $state === self::STATE_DRAFT,
        };
    }

    public function withDraftVersion(int $version, int $actorId, DateTimeImmutable $occurredAt): self
    {
        return $this->copy(
            state: $this->state,
            currentDraftVersion: $version,
            publishedVersion: $this->publishedVersion,
            change: new FlowChange($actorId, $occurredAt),
        );
    }

    public function withState(
        string $state,
        int $publishedVersion,
        int $actorId,
        DateTimeImmutable $occurredAt,
    ): self {
        if (! $this->canTransitionTo($state)) {
            throw new InvalidArgumentException(sprintf('Flow cannot transition from "%s" to "%s".', $this->state, $state));
        }

        return $this->copy(
            $state,
            $this->currentDraftVersion,
            $publishedVersion,
            new FlowChange($actorId, $occurredAt),
        );
    }

    /**
     * Makes a saved version the live one (spec 122, FR-001).
     *
     * From a draft this is the first publication. From a published flow it is "Publish changes":
     * the flow stays published and visitors see the new version from the next page load, so the
     * form is never off the site. A closed or expired flow goes back to draft first, as before.
     */
    public function publish(int $version, int $actorId, DateTimeImmutable $occurredAt): self
    {
        $republish = $this->state === self::STATE_PUBLISHED;
        if (! $republish && ! $this->canTransitionTo(self::STATE_PUBLISHED)) {
            throw new InvalidArgumentException(sprintf('Flow cannot transition from "%s" to "%s".', $this->state, self::STATE_PUBLISHED));
        }
        if ($republish && $version === $this->publishedVersion) {
            throw new InvalidArgumentException('The live version is already up to date.');
        }

        return $this->copy(
            self::STATE_PUBLISHED,
            $this->currentDraftVersion,
            $version,
            new FlowChange($actorId, $occurredAt),
            [...$this->publications, new FlowPublication($version, $actorId, $occurredAt)],
        );
    }

    public function withAvailability(FlowAvailability $availability, int $actorId, DateTimeImmutable $occurredAt): self
    {
        return $this->copy(
            $this->state,
            $this->currentDraftVersion,
            $this->publishedVersion,
            new FlowChange($actorId, $occurredAt),
            availability: $availability,
        );
    }

    /**
     * The publications, with the live version of a flow published before they were recorded
     * listed from what the flow knows: its version and when, with no author.
     *
     * @return list<FlowPublication>
     */
    public function publicationHistory(): array
    {
        if ($this->publications !== [] || $this->publishedVersion < 1 || $this->publishedAt === null) {
            return $this->publications;
        }

        return [new FlowPublication($this->publishedVersion, 0, $this->publishedAt)];
    }

    /** @param list<FlowPublication>|null $publications */
    private function copy(
        string $state,
        int $currentDraftVersion,
        int $publishedVersion,
        FlowChange $change,
        ?array $publications = null,
        ?FlowAvailability $availability = null,
    ): self {
        return new self(
            id: $this->id,
            uuid: $this->uuid,
            slug: $this->slug,
            name: $this->name,
            description: $this->description,
            state: $state,
            ownerId: $this->ownerId,
            placementType: $this->placementType,
            placementId: $this->placementId,
            currentDraftVersion: $currentDraftVersion,
            publishedVersion: $publishedVersion,
            testMode: $this->testMode,
            createdBy: $this->createdBy,
            updatedBy: $change->actorId,
            createdAt: $this->createdAt,
            updatedAt: $change->occurredAt,
            // Only a publication moves it: a draft saved on a live flow is not one.
            publishedAt: $state === self::STATE_PUBLISHED
                && ($this->state !== self::STATE_PUBLISHED || $publishedVersion !== $this->publishedVersion)
                ? $change->occurredAt
                : $this->publishedAt,
            closedAt: $state === self::STATE_CLOSED ? $change->occurredAt : null,
            expiresAt: $this->expiresAt,
            publications: $publications ?? $this->publications,
            availability: $availability ?? $this->availability,
        );
    }
}
