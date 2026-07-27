<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Blog;

defined('ABSPATH') || exit;

/**
 * Blog Pro's state vocabulary, in words (spec 075, FR-2/FR-3).
 *
 * The screen used to print `ready_for_review` and `publish` at people — raw slugs on an otherwise
 * translated screen, and the same defect Spec 074 removed from Data Models and Notifications.
 *
 * The mapping lives on the server rather than in JavaScript on purpose. The states are the workflow
 * service's vocabulary; a second copy in the client is a second thing to update when a state is added,
 * and the two would drift the way the drawer and the screen drifted before DECISIONS #157. The client
 * is handed `{ key, label }` and renders the label.
 *
 * An unrecognised key returns the key itself. A slug is bad, but an empty cell where a state should be
 * is worse: it reads as "no state" rather than "a state CoreX has no word for yet".
 */
final class BlogProLabels
{
    /**
     * The CoreX editorial states, in the order a post moves through them.
     *
     * @return list<string>
     */
    public static function editorialStates(): array
    {
        return [
            EditorialItem::STATE_DRAFT,
            EditorialItem::STATE_READY_FOR_REVIEW,
            EditorialItem::STATE_NEEDS_CHANGES,
            EditorialItem::STATE_APPROVED,
            EditorialItem::STATE_SCHEDULED,
            EditorialItem::STATE_PUBLISHED,
        ];
    }

    public static function editorialState(string $state): string
    {
        return match ($state) {
            EditorialItem::STATE_DRAFT            => __('Draft', 'corex'),
            EditorialItem::STATE_READY_FOR_REVIEW => __('Ready for review', 'corex'),
            EditorialItem::STATE_NEEDS_CHANGES    => __('Needs changes', 'corex'),
            EditorialItem::STATE_APPROVED         => __('Approved', 'corex'),
            EditorialItem::STATE_SCHEDULED        => __('Scheduled', 'corex'),
            EditorialItem::STATE_PUBLISHED        => __('Published', 'corex'),
            default                               => $state,
        };
    }

    /**
     * The native WordPress status, shown beside the CoreX state.
     *
     * The workflow service maps between the two, so a divergence — approved in CoreX, still a draft in
     * WordPress — is worth being able to see rather than having to infer.
     */
    public static function nativeStatus(string $status): string
    {
        return match ($status) {
            'draft'   => __('Draft', 'corex'),
            'pending' => __('Pending review', 'corex'),
            'publish' => __('Published', 'corex'),
            'future'  => __('Scheduled', 'corex'),
            'private' => __('Private', 'corex'),
            'trash'   => __('Trashed', 'corex'),
            default   => $status,
        };
    }

    public static function commentState(string $state): string
    {
        return match ($state) {
            'approved' => __('Approved', 'corex'),
            'spam'     => __('Spam', 'corex'),
            'trash'    => __('Trashed', 'corex'),
            'pending'  => __('Awaiting review', 'corex'),
            default    => $state,
        };
    }

    /**
     * The moderation actions this screen offers, keyed by the action the service accepts.
     *
     * Three, not five. `CommentModerationService::queue()` returns only comments held for review, so
     * there is nothing for an "unapprove" to act on, and the service's `edit` and `reply` are excluded
     * by spec 075 §10 — this screen moderates, it does not author.
     *
     * @return array<string,string>
     */
    public static function commentActions(): array
    {
        return [
            CommentModerationService::ACTION_APPROVE => __('Approve', 'corex'),
            CommentModerationService::ACTION_SPAM    => __('Mark as spam', 'corex'),
            CommentModerationService::ACTION_TRASH   => __('Move to trash', 'corex'),
        ];
    }
}
