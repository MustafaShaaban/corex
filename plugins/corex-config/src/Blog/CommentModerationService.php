<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Blog;

defined('ABSPATH') || exit;

use InvalidArgumentException;
use RuntimeException;

/**
 * Moderates native WordPress comments for Blog Pro.
 */
final class CommentModerationService
{
    public const ACTION_APPROVE = 'approve';
    public const ACTION_REPLY = 'reply';
    public const ACTION_EDIT = 'edit';
    public const ACTION_SPAM = 'spam';
    public const ACTION_TRASH = 'trash';

    /**
     * @return list<CommentModerationItem>
     */
    public function queue(int $postId): array
    {
        if ($postId < 1) {
            return [];
        }

        $comments = get_comments([
            'post_id' => $postId,
            'status' => 'hold',
            'number' => 50,
            'orderby' => 'comment_date_gmt',
            'order' => 'ASC',
        ]);

        return array_map($this->item(...), is_array($comments) ? $comments : []);
    }

    public function moderate(CommentModerationRequest $request): CommentModerationResult
    {
        $comment = $this->comment($request->commentId);

        return match ($request->action) {
            self::ACTION_APPROVE => $this->setStatus($request, 'approve', 'approved'),
            self::ACTION_SPAM => $this->setStatus($request, 'spam', 'spam'),
            self::ACTION_TRASH => $this->trash($request),
            self::ACTION_EDIT => $this->edit($request),
            self::ACTION_REPLY => $this->reply($request, $comment),
            default => throw new InvalidArgumentException('Comment moderation action is invalid.'),
        };
    }

    private function item(\WP_Comment $comment): CommentModerationItem
    {
        return new CommentModerationItem(
            commentId: (int) $comment->comment_ID,
            postId: (int) $comment->comment_post_ID,
            author: (string) $comment->comment_author,
            state: $this->state((string) $comment->comment_approved),
            firstComment: $this->firstComment($comment),
            likelySpam: $this->likelySpam((string) $comment->comment_content),
            heldForReview: (string) $comment->comment_approved === '0',
        );
    }

    private function setStatus(CommentModerationRequest $request, string $nativeStatus, string $state): CommentModerationResult
    {
        if (! wp_set_comment_status($request->commentId, $nativeStatus)) {
            throw new RuntimeException('CoreX could not update the native comment status.');
        }

        return new CommentModerationResult($request->commentId, $request->action, $state);
    }

    private function trash(CommentModerationRequest $request): CommentModerationResult
    {
        if (! wp_trash_comment($request->commentId)) {
            throw new RuntimeException('CoreX could not trash the native comment.');
        }

        return new CommentModerationResult($request->commentId, $request->action, 'trash');
    }

    private function edit(CommentModerationRequest $request): CommentModerationResult
    {
        $body = trim($request->body);
        if ($body === '') {
            throw new InvalidArgumentException('Edited comment body is required.');
        }

        $updated = wp_update_comment([
            'comment_ID' => $request->commentId,
            'comment_content' => $body,
        ]);
        if ($updated === false || (int) $updated < 1) {
            throw new RuntimeException('CoreX could not edit the native comment.');
        }

        return new CommentModerationResult($request->commentId, $request->action, $this->stateForComment($request->commentId));
    }

    private function reply(CommentModerationRequest $request, \WP_Comment $comment): CommentModerationResult
    {
        $body = trim($request->body);
        if ($body === '') {
            throw new InvalidArgumentException('Reply body is required.');
        }

        $user = get_userdata($request->actorId);
        $replyId = wp_insert_comment([
            'comment_post_ID' => (int) $comment->comment_post_ID,
            'comment_parent' => $request->commentId,
            'user_id' => $request->actorId,
            'comment_author' => $user ? (string) $user->display_name : 'CoreX',
            'comment_author_email' => $user ? (string) $user->user_email : '',
            'comment_content' => $body,
            'comment_approved' => '1',
        ]);

        if ((int) $replyId < 1) {
            throw new RuntimeException('CoreX could not create the native comment reply.');
        }

        return new CommentModerationResult($request->commentId, $request->action, $this->stateForComment($request->commentId), (int) $replyId);
    }

    private function comment(int $commentId): \WP_Comment
    {
        $comment = get_comment($commentId);
        if (! $comment instanceof \WP_Comment) {
            throw new InvalidArgumentException('Native comment was not found.');
        }

        return $comment;
    }

    private function stateForComment(int $commentId): string
    {
        return $this->state((string) $this->comment($commentId)->comment_approved);
    }

    private function state(string $approved): string
    {
        return match ($approved) {
            '1' => 'approved',
            'spam' => 'spam',
            'trash' => 'trash',
            default => 'pending',
        };
    }

    private function firstComment(\WP_Comment $comment): bool
    {
        $approved = get_comments([
            'author_email' => (string) $comment->comment_author_email,
            'status' => 'approve',
            'count' => true,
            'number' => 1,
        ]);

        return (int) $approved === 0;
    }

    private function likelySpam(string $content): bool
    {
        return preg_match_all('/https?:\/\//i', $content) >= 2;
    }
}
