<?php

/**
 * What is written in Blog Pro is read as it was written.
 *
 * WordPress takes one level of slashes off a comment and off a meta value on their way in. A
 * comment edited or answered from Blog Pro, and a post's editorial notes, were handed over as
 * they were: a reply saying `C:\offers\new` was published as `C:offersnew`, and the notes, one
 * value written again whole each time the post's workflow is saved, lost one more level on
 * each save (measured 2026-10-13, DECISIONS #332).
 *
 * On real WordPress, through the moderation service and the editorial store.
 *
 * @package Corex\Tests\Integration\Blog
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Blog\CommentModerationRequest;
use Corex\Config\Blog\CommentModerationService;
use Corex\Config\Blog\EditorialItem;
use Corex\Config\Blog\EditorialNote;
use Corex\Config\Blog\WpEditorialWorkflowStore;
use Corex\Tests\Support\BackslashedText;

beforeEach(function () {
    $this->moderation = Boot::app()->container()->make(CommentModerationService::class);
    $this->editorial = new WpEditorialWorkflowStore();
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    $this->actorId = (int) ($administrators[0] ?? 0);
    wp_set_current_user($this->actorId);
    // Deleting the post takes its comments and its notes with it.
    $this->postId = wp_insert_post([
        'post_title' => 'A post that is discussed',
        'post_status' => 'publish',
        'post_type' => 'post',
        'comment_status' => 'open',
    ]);
    $this->commentId = (int) wp_insert_comment([
        'comment_post_ID' => $this->postId,
        'comment_author' => 'A reader',
        'comment_author_email' => 'reader@example.com',
        'comment_content' => 'A first comment.',
        'comment_approved' => '1',
    ]);
});

afterEach(function () {
    wp_delete_post($this->postId, true);
});

it('publishes an edited comment as it was written', function (string $text) {
    $this->moderation->moderate(new CommentModerationRequest($this->commentId, 'edit', $this->actorId, $text));

    expect(get_comment($this->commentId)->comment_content)->toBe($text);
})->with(BackslashedText::cases());

it('publishes a reply as it was written, under the comment it answers', function (string $text) {
    $result = $this->moderation->moderate(new CommentModerationRequest($this->commentId, 'reply', $this->actorId, $text));
    $reply = get_comment($result->createdCommentId);

    expect($reply->comment_content)->toBe($text)
        ->and((int) $reply->comment_parent)->toBe($this->commentId)
        ->and((int) $reply->user_id)->toBe($this->actorId);
})->with(BackslashedText::cases());

it('publishes a reply under the name of who wrote it', function () {
    // The reply carries its writer's display name, which is text handed over like the reply.
    require_once ABSPATH . 'wp-admin/includes/user.php';
    $login = 'kept' . strtolower(wp_generate_password(10, false));
    $writer = (int) wp_insert_user(wp_slash([
        'user_login' => $login,
        'user_pass' => wp_generate_password(24),
        'user_email' => $login . '@example.com',
        'display_name' => 'CORP\\salma.h',
        'role' => 'editor',
    ]));

    $result = $this->moderation->moderate(new CommentModerationRequest($this->commentId, 'reply', $writer, 'A reply.'));
    $author = get_comment($result->createdCommentId)->comment_author;
    wp_delete_user($writer);

    expect($author)->toBe('CORP\\salma.h');
});

it('keeps an editorial note as it was written', function (string $text) {
    $written = new DateTimeImmutable('2026-10-13T09:00:00+00:00');

    $this->editorial->save(new EditorialItem($this->postId, EditorialItem::STATE_DRAFT, 'publish', notes: [
        new EditorialNote($this->actorId, $text, $written),
    ]));
    $note = $this->editorial->find($this->postId)->notes[0];

    expect($note->body)->toBe($text)
        // A note's writer and its date are read where they are a whole number and a date.
        ->and($note->actorId)->toBe($this->actorId)
        ->and($note->createdAt->format(DATE_ATOM))->toBe('2026-10-13T09:00:00+00:00');
})->with(BackslashedText::cases());

it('keeps an editorial note through each later save of the post\'s workflow', function () {
    $this->editorial->save(new EditorialItem($this->postId, EditorialItem::STATE_DRAFT, 'publish', notes: [
        new EditorialNote($this->actorId, BackslashedText::ALL, new DateTimeImmutable('2026-10-13T09:00:00+00:00')),
    ]));

    // An item is read and written back whole when its state, its assignee or a date changes.
    $this->editorial->save($this->editorial->find($this->postId));
    $this->editorial->save($this->editorial->find($this->postId));

    expect($this->editorial->find($this->postId)->notes[0]->body)->toBe(BackslashedText::ALL);
});
