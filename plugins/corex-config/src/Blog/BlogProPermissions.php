<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Blog;

defined('ABSPATH') || exit;

/**
 * What Blog Pro lets the signed-in person do (spec 112, User Story 5): WordPress's own permission
 * for the post or comment in question, and never more. The screen and every route ask this one
 * class, so a control is hidden exactly when the route would refuse it (DECISIONS #159).
 *
 * - Blog Pro opens for anyone WordPress lets edit posts (stage 3 replaces this with its own ability).
 * - A post's figures, share controls and held comments, and moving it between editorial states,
 *   need permission to edit that post; moving it to Published or Scheduled also needs permission
 *   to publish it.
 * - Approving, marking as spam, trashing and replying to a comment need comment moderation rights.
 *   That is stricter than WordPress, which lets an Author moderate the comments on their own posts;
 *   the owner chose the stricter rule (spec 112 clarifications, 2026-10-20). Editing a comment
 *   needs permission to edit that comment.
 * - The authors table shows every author to people who may edit others' posts, and only their own
 *   line to anybody else.
 */
final class BlogProPermissions
{
    public const BASE = 'edit_posts';

    private const PUBLISHING_STATES = [EditorialItem::STATE_PUBLISHED, EditorialItem::STATE_SCHEDULED];

    public function canOpen(): bool
    {
        return current_user_can(self::BASE);
    }

    public function mayWorkOnPost(int $postId): bool
    {
        return $postId > 0 && $this->canOpen() && current_user_can('edit_post', $postId);
    }

    public function mayMovePost(int $postId, string $state): bool
    {
        return $this->mayWorkOnPost($postId)
            && (! in_array($state, self::PUBLISHING_STATES, true) || current_user_can('publish_post', $postId));
    }

    public function mayActOnComment(int $commentId, string $action): bool
    {
        if ($commentId < 1 || ! $this->canOpen()) {
            return false;
        }

        return $action === CommentModerationService::ACTION_EDIT
            ? current_user_can('edit_comment', $commentId)
            : current_user_can('moderate_comments');
    }

    public function seesEveryAuthor(): bool
    {
        return current_user_can('edit_others_posts');
    }
}
