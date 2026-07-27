<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Blog;

defined('ABSPATH') || exit;

/**
 * One shape for an editorial item and a comment, wherever they leave the server (spec 075).
 *
 * `BlogProScreen` localises the first paint and `BlogProController` answers every refetch after it.
 * They were projecting the same two objects into the same two array shapes independently, so a field
 * added to one path silently did not exist on the other — and the client cannot tell which path a
 * given item arrived by. That is the same "two derivations, one eventual divergence" this project has
 * now written down twice (DECISIONS #157, #160), so here it is derived once.
 *
 * Pure apart from the capability checks, which are deliberately here: a control must be decided by the
 * same capability the route enforces, not guessed at by the client (DECISIONS #159).
 */
final class BlogProPresenter
{
    /** @return array<string,mixed> */
    public static function editorialItem(EditorialItem $item): array
    {
        return [
            'post_id'         => $item->postId,
            'editorial_state' => $item->editorialState,
            'native_status'   => $item->nativeStatus,
            // The words. The screen printed `ready_for_review` and `publish` at people (spec 075, D4).
            'editorial_state_label' => BlogProLabels::editorialState($item->editorialState),
            'native_status_label'   => BlogProLabels::nativeStatus($item->nativeStatus),
            'transitions'           => EditorialTransitions::from($item->editorialState),
            'can_transition'        => current_user_can('edit_post', $item->postId),
            'assignee_id'           => $item->assigneeId,
            'due_at'                => $item->dueAt?->format(DATE_ATOM),
            'scheduled_at'          => $item->scheduledAt?->format(DATE_ATOM),
        ];
    }

    /** @return array<string,mixed> */
    public static function commentItem(CommentModerationItem $comment): array
    {
        return [
            'comment_id'      => $comment->commentId,
            'post_id'         => $comment->postId,
            'author'          => $comment->author,
            'content'         => $comment->content,
            'submitted_at'    => $comment->submittedAt,
            'state'           => $comment->state,
            'state_label'     => BlogProLabels::commentState($comment->state),
            'first_comment'   => $comment->firstComment,
            'likely_spam'     => $comment->likelySpam,
            'held_for_review' => $comment->heldForReview,
            'can_moderate'    => current_user_can('moderate_comments'),
        ];
    }
}
