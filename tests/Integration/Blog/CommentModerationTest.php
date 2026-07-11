<?php

/**
 * Integration tests for Blog Pro native comment moderation.
 *
 * @package Corex\Tests\Integration\Blog
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Blog\CommentModerationRequest;
use Corex\Config\Blog\CommentModerationService;

beforeEach(function () {
    if (! function_exists('wp_insert_post') || ! function_exists('wp_insert_comment')) {
        $this->markTestSkipped('WordPress is not loaded for Blog comment moderation integration tests.');
    }

    $this->service = Boot::app()->container()->make(CommentModerationService::class);
    $this->postId = wp_insert_post([
        'post_title' => 'CoreX moderation test',
        'post_content' => 'Integration content',
        'post_status' => 'publish',
        'post_type' => 'post',
        'comment_status' => 'open',
    ], true);

    if ($this->postId instanceof WP_Error || (int) $this->postId < 1) {
        $this->markTestSkipped('Could not create a native post for comment moderation integration tests.');
    }

    $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    $this->actorId = (int) ($admins[0] ?? 0);
    wp_set_current_user($this->actorId);
});

afterEach(function () {
    if (! empty($this->postId) && function_exists('wp_delete_post')) {
        wp_delete_post((int) $this->postId, true);
    }
});

it('classifies pending native comments for moderation review', function () {
    wp_insert_comment([
        'comment_post_ID' => (int) $this->postId,
        'comment_author' => 'First Reader',
        'comment_author_email' => 'first-reader@example.test',
        'comment_content' => 'This is my first comment.',
        'comment_approved' => '0',
    ]);
    wp_insert_comment([
        'comment_post_ID' => (int) $this->postId,
        'comment_author' => 'Suspicious Reader',
        'comment_author_email' => 'spam-reader@example.test',
        'comment_content' => 'Buy now http://spam.example.test http://spam-2.example.test',
        'comment_approved' => '0',
    ]);

    $queue = $this->service->queue((int) $this->postId);

    expect($queue)->toHaveCount(2)
        ->and($queue[0]->state)->toBe('pending')
        ->and($queue[0]->firstComment)->toBeTrue()
        ->and($queue[0]->heldForReview)->toBeTrue()
        ->and($queue[1]->likelySpam)->toBeTrue();
});

it('applies approve reply edit spam and trash through native comment APIs', function () {
    $commentId = wp_insert_comment([
        'comment_post_ID' => (int) $this->postId,
        'comment_author' => 'Needs Review',
        'comment_author_email' => 'review@example.test',
        'comment_content' => 'Please answer this question.',
        'comment_approved' => '0',
    ]);

    $approved = $this->service->moderate(new CommentModerationRequest(
        commentId: (int) $commentId,
        action: CommentModerationService::ACTION_APPROVE,
        actorId: $this->actorId,
    ));
    $reply = $this->service->moderate(new CommentModerationRequest(
        commentId: (int) $commentId,
        action: CommentModerationService::ACTION_REPLY,
        actorId: $this->actorId,
        body: 'Thanks for reading — here is the answer.',
    ));
    $edited = $this->service->moderate(new CommentModerationRequest(
        commentId: (int) $commentId,
        action: CommentModerationService::ACTION_EDIT,
        actorId: $this->actorId,
        body: 'Please answer this edited question.',
    ));
    $spammed = $this->service->moderate(new CommentModerationRequest(
        commentId: (int) $commentId,
        action: CommentModerationService::ACTION_SPAM,
        actorId: $this->actorId,
    ));
    $trashed = $this->service->moderate(new CommentModerationRequest(
        commentId: (int) $commentId,
        action: CommentModerationService::ACTION_TRASH,
        actorId: $this->actorId,
    ));

    $comment = get_comment((int) $commentId);
    $replyComment = get_comment((int) $reply->createdCommentId);

    expect($approved->state)->toBe('approved')
        ->and($reply->createdCommentId)->toBeGreaterThan(0)
        ->and($replyComment?->comment_parent)->toBe((string) $commentId)
        ->and($edited->state)->toBe('approved')
        ->and($spammed->state)->toBe('spam')
        ->and($trashed->state)->toBe('trash')
        ->and($comment?->comment_content)->toBe('Please answer this edited question.')
        ->and($comment?->comment_approved)->toBe('trash');
});
