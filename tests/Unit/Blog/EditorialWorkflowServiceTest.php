<?php

/**
 * Unit tests for Blog Pro editorial workflow and native post-status synchronization.
 *
 * @package Corex\Tests\Unit\Blog
 */

declare(strict_types=1);

use Corex\Config\Blog\EditorialItem;
use Corex\Config\Blog\EditorialNote;
use Corex\Config\Blog\EditorialTransitionRequest;
use Corex\Config\Blog\EditorialWorkflowService;
use Corex\Config\Blog\EditorialWorkflowStore;

it('keeps editorial states synchronized with native WordPress post status', function () {
    $store = new CorexEditorialWorkflowStoreFake();
    $service = new EditorialWorkflowService($store);
    $now = new DateTimeImmutable('2026-07-08T09:00:00+00:00');

    foreach ([
        EditorialItem::STATE_DRAFT => 'draft',
        EditorialItem::STATE_READY_FOR_REVIEW => 'pending',
        EditorialItem::STATE_NEEDS_CHANGES => 'draft',
        EditorialItem::STATE_APPROVED => 'pending',
        EditorialItem::STATE_SCHEDULED => 'future',
        EditorialItem::STATE_PUBLISHED => 'publish',
    ] as $editorialState => $nativeStatus) {
        $scheduledAt = $editorialState === EditorialItem::STATE_SCHEDULED
            ? $now->modify('+2 days')
            : null;

        $item = $service->transition(new EditorialTransitionRequest(
            postId: 42,
            state: $editorialState,
            actorId: 7,
            note: 'Move to ' . $editorialState,
            assigneeId: 12,
            dueAt: $now->modify('+1 day'),
            scheduledAt: $scheduledAt,
            occurredAt: $now,
        ));

        expect($item->editorialState)->toBe($editorialState)
            ->and($item->nativeStatus)->toBe($nativeStatus);
    }

    expect(array_column($store->nativeStatusUpdates, 'status'))->toBe([
        'draft',
        'pending',
        'draft',
        'pending',
        'future',
        'publish',
    ]);
});

it('persists assignee due date and review notes with actor context', function () {
    $store = new CorexEditorialWorkflowStoreFake();
    $service = new EditorialWorkflowService($store);
    $now = new DateTimeImmutable('2026-07-08T09:00:00+00:00');
    $dueAt = new DateTimeImmutable('2026-07-11T17:00:00+00:00');

    $item = $service->transition(new EditorialTransitionRequest(
        postId: 42,
        state: EditorialItem::STATE_READY_FOR_REVIEW,
        actorId: 7,
        note: 'Please review the introduction and meta description.',
        assigneeId: 12,
        dueAt: $dueAt,
        occurredAt: $now,
    ));

    expect($item->assigneeId)->toBe(12)
        ->and($item->dueAt?->format(DATE_ATOM))->toBe('2026-07-11T17:00:00+00:00')
        ->and($item->notes)->toHaveCount(1)
        ->and($item->notes[0]->actorId)->toBe(7)
        ->and($item->notes[0]->body)->toBe('Please review the introduction and meta description.')
        ->and($item->notes[0]->createdAt->format(DATE_ATOM))->toBe('2026-07-08T09:00:00+00:00');
});

it('requires a native schedule timestamp before scheduling a post', function () {
    $store = new CorexEditorialWorkflowStoreFake();
    $service = new EditorialWorkflowService($store);

    expect(fn () => $service->transition(new EditorialTransitionRequest(
        postId: 42,
        state: EditorialItem::STATE_SCHEDULED,
        actorId: 7,
        note: 'Schedule this article.',
        occurredAt: new DateTimeImmutable('2026-07-08T09:00:00+00:00'),
    )))->toThrow(InvalidArgumentException::class, 'Scheduled posts require a schedule timestamp.');

    expect($store->nativeStatusUpdates)->toBe([]);
});

final class CorexEditorialWorkflowStoreFake implements EditorialWorkflowStore
{
    /** @var array<int, EditorialItem> */
    private array $editorialItems = [];

    /** @var list<array{postId:int,status:string,scheduledAt:?DateTimeImmutable}> */
    public array $nativeStatusUpdates = [];

    public function find(int $postId): EditorialItem
    {
        return $this->editorialItems[$postId] ?? new EditorialItem(
            postId: $postId,
            editorialState: EditorialItem::STATE_DRAFT,
            nativeStatus: 'draft',
        );
    }

    public function save(EditorialItem $item): void
    {
        $this->editorialItems[$item->postId] = $item;
    }

    public function updateNativeStatus(int $postId, string $status, ?DateTimeImmutable $scheduledAt = null): void
    {
        $this->nativeStatusUpdates[] = [
            'postId' => $postId,
            'status' => $status,
            'scheduledAt' => $scheduledAt,
        ];
    }
}
