<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Blog;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Coordinates CoreX editorial metadata with native WordPress post statuses.
 */
final class EditorialWorkflowService
{
    public function __construct(private readonly EditorialWorkflowStore $store)
    {
    }

    public function item(int $postId): EditorialItem
    {
        return $this->store->find($postId);
    }

    public function transition(EditorialTransitionRequest $request): EditorialItem
    {
        $nativeStatus = $this->nativeStatus($request);
        $this->store->updateNativeStatus($request->postId, $nativeStatus, $request->scheduledAt);

        $existing = $this->store->find($request->postId);
        $notes = $existing->notes;
        $noteBody = trim($request->note);
        if ($noteBody !== '') {
            $notes[] = new EditorialNote(
                actorId: $request->actorId,
                body: $noteBody,
                createdAt: $request->occurredAt ?? new DateTimeImmutable('now', new DateTimeZone('UTC')),
            );
        }

        $item = new EditorialItem(
            postId: $request->postId,
            editorialState: $request->state,
            nativeStatus: $nativeStatus,
            assigneeId: $request->assigneeId,
            dueAt: $request->dueAt,
            scheduledAt: $request->scheduledAt,
            notes: $notes,
        );
        $this->store->save($item);

        return $item;
    }

    private function nativeStatus(EditorialTransitionRequest $request): string
    {
        return match ($request->state) {
            EditorialItem::STATE_DRAFT,
            EditorialItem::STATE_NEEDS_CHANGES => 'draft',
            EditorialItem::STATE_READY_FOR_REVIEW,
            EditorialItem::STATE_APPROVED => 'pending',
            EditorialItem::STATE_PUBLISHED => 'publish',
            EditorialItem::STATE_SCHEDULED => $this->scheduledStatus($request),
            default => throw new InvalidArgumentException('Editorial state is invalid.'),
        };
    }

    private function scheduledStatus(EditorialTransitionRequest $request): string
    {
        if ($request->scheduledAt === null) {
            throw new InvalidArgumentException('Scheduled posts require a schedule timestamp.');
        }

        return 'future';
    }
}
