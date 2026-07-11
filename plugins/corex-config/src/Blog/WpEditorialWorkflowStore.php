<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Blog;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/**
 * WordPress post/meta adapter for Blog Pro editorial metadata.
 */
final class WpEditorialWorkflowStore implements EditorialWorkflowStore
{
    private const STATE_META = '_corex_blog_editorial_state';
    private const ASSIGNEE_META = '_corex_blog_assignee_id';
    private const DUE_META = '_corex_blog_due_at';
    private const SCHEDULE_META = '_corex_blog_scheduled_at';
    private const NOTES_META = '_corex_blog_editorial_notes';

    public function find(int $postId): EditorialItem
    {
        $post = get_post($postId);
        if (! $post instanceof \WP_Post || $post->post_type !== 'post') {
            throw new RuntimeException('CoreX could not find the native Blog post.');
        }

        $nativeStatus = (string) $post->post_status;
        $editorialState = (string) get_post_meta($postId, self::STATE_META, true);
        $assigneeId = (int) get_post_meta($postId, self::ASSIGNEE_META, true);

        return new EditorialItem(
            postId: $postId,
            editorialState: $editorialState !== '' ? $editorialState : $this->stateFromNative($nativeStatus),
            nativeStatus: $nativeStatus,
            assigneeId: $assigneeId > 0 ? $assigneeId : null,
            dueAt: $this->storedDate(get_post_meta($postId, self::DUE_META, true)),
            scheduledAt: $this->storedDate(get_post_meta($postId, self::SCHEDULE_META, true)),
            notes: $this->notes($postId),
        );
    }

    public function save(EditorialItem $item): void
    {
        update_post_meta($item->postId, self::STATE_META, $item->editorialState);
        update_post_meta($item->postId, self::ASSIGNEE_META, $item->assigneeId ?? 0);
        update_post_meta($item->postId, self::DUE_META, $this->nullableDate($item->dueAt));
        update_post_meta($item->postId, self::SCHEDULE_META, $this->nullableDate($item->scheduledAt));
        update_post_meta($item->postId, self::NOTES_META, array_map($this->noteRow(...), $item->notes));
    }

    public function updateNativeStatus(int $postId, string $status, ?DateTimeImmutable $scheduledAt = null): void
    {
        $post = [
            'ID' => $postId,
            'post_status' => $status,
        ];

        if ($status === 'future' && $scheduledAt !== null) {
            $post['post_date_gmt'] = $this->date($scheduledAt);
            $post['post_date'] = $scheduledAt->setTimezone(wp_timezone())->format('Y-m-d H:i:s');
        }

        $updated = wp_update_post($post, true);
        if ($updated instanceof \WP_Error || (int) $updated < 1) {
            throw new RuntimeException('CoreX could not update the native Blog post status.');
        }
    }

    private function stateFromNative(string $status): string
    {
        return match ($status) {
            'pending' => EditorialItem::STATE_READY_FOR_REVIEW,
            'future' => EditorialItem::STATE_SCHEDULED,
            'publish' => EditorialItem::STATE_PUBLISHED,
            default => EditorialItem::STATE_DRAFT,
        };
    }

    /**
     * @return list<EditorialNote>
     */
    private function notes(int $postId): array
    {
        $storedNotes = get_post_meta($postId, self::NOTES_META, true);
        if (! is_array($storedNotes)) {
            return [];
        }

        $notes = [];
        foreach ($storedNotes as $storedNote) {
            if (! is_array($storedNote) || empty($storedNote['body']) || empty($storedNote['created_at'])) {
                continue;
            }

            $actorId = (int) ($storedNote['actor_id'] ?? 0);
            if ($actorId < 1) {
                continue;
            }

            $notes[] = new EditorialNote(
                actorId: $actorId,
                body: (string) $storedNote['body'],
                createdAt: $this->storedDate($storedNote['created_at']) ?? new DateTimeImmutable('now', new DateTimeZone('UTC')),
            );
        }

        return $notes;
    }

    /**
     * @return array{actor_id:int,body:string,created_at:string}
     */
    private function noteRow(EditorialNote $note): array
    {
        return [
            'actor_id' => $note->actorId,
            'body' => $note->body,
            'created_at' => $this->date($note->createdAt),
        ];
    }

    private function nullableDate(?DateTimeImmutable $date): string
    {
        return $date === null ? '' : $this->date($date);
    }

    private function date(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function storedDate(mixed $date): ?DateTimeImmutable
    {
        return is_string($date) && $date !== '' ? new DateTimeImmutable($date, new DateTimeZone('UTC')) : null;
    }
}
