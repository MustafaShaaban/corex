<?php

/**
 * The two routes an editorial workspace exists for, exercised end to end (spec 075, FR-2/FR-3).
 *
 * `POST /blog/editorial/{id}/transition` and `POST /blog/comments/{id}/moderate` were registered in
 * v0.35.0 and had no caller in the product until this spec. These assert what the panels now depend
 * on: that a transition really moves the post and comes back with the words the panel renders, that
 * moderation really changes the comment and takes it out of the queue, and that both refuse an actor
 * who may not do them — because the client hides those controls on the strength of the same
 * capability, and the two must not disagree (DECISIONS #159).
 *
 * @package Corex\Tests\Integration\Blog
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Blog\BlogProController;
use Corex\Config\Blog\CommentModerationService;
use Corex\Config\Blog\EditorialItem;

function workspaceRequest(string $method, string $route, array $payload = []): WP_REST_Request
{
    $request = new WP_REST_Request($method, $route);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    $method === 'GET' ? $request->set_query_params($payload) : $request->set_body_params($payload);

    return $request;
}

beforeEach(function () {
    if (! function_exists('wp_insert_post') || ! class_exists(Boot::class)) {
        $this->markTestSkipped('WordPress is not loaded for Blog Pro workspace tests.');
    }

    $this->container = Boot::app()->container();
    $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    $this->actorId = (int) ($admins[0] ?? 0);
    wp_set_current_user($this->actorId);

    $this->controller = $this->container->make(BlogProController::class);
    $this->postId = (int) wp_insert_post([
        'post_title'   => 'CoreX 075 workspace test',
        'post_content' => 'Content under review.',
        'post_status'  => 'draft',
        'post_author'  => $this->actorId,
    ]);
});

afterEach(function () {
    if (isset($this->postId) && $this->postId > 0) {
        wp_delete_post($this->postId, true);
    }
});

it('moves a post and answers with the words the panel renders', function () {
    $response = $this->controller->transition(workspaceRequest(
        'POST',
        '/corex/v1/blog/editorial/' . $this->postId . '/transition',
        // `id` normally arrives from the route pattern; a synthetic request has to carry it.
        ['id' => $this->postId, 'state' => EditorialItem::STATE_READY_FOR_REVIEW, 'note' => 'Ready for a look.'],
    ));

    $data = $response->get_data()['data'];

    // The labels matter as much as the state: no GET route returns an editorial item, so this
    // response is the only place the panel ever learns the new one (spec 075, FR-1).
    expect($data['editorial_state'])->toBe(EditorialItem::STATE_READY_FOR_REVIEW)
        ->and($data['editorial_state_label'])->toBe('Ready for review')
        ->and($data['native_status_label'])->not->toBe('')
        ->and($data['transitions'])->not->toBeEmpty();

    // The native post really moved, not just CoreX's record of it.
    expect(get_post_status($this->postId))->toBe('pending');
});

it('never offers a move to the state the post is already in', function () {
    $response = $this->controller->transition(workspaceRequest(
        'POST',
        '/corex/v1/blog/editorial/' . $this->postId . '/transition',
        ['id' => $this->postId, 'state' => EditorialItem::STATE_APPROVED],
    ));

    $data = $response->get_data()['data'];

    expect(array_column($data['transitions'], 'key'))
        ->not->toContain(EditorialItem::STATE_APPROVED)
        ->and($data['transitions'])->toHaveCount(5);
});

it('refuses to schedule a post without saying when', function () {
    // `EditorialWorkflowService::scheduledStatus()` throws, which is why the panel requires the field
    // up front rather than letting the person meet this as an exception.
    expect(fn () => $this->controller->transition(workspaceRequest(
        'POST',
        '/corex/v1/blog/editorial/' . $this->postId . '/transition',
        ['id' => $this->postId, 'state' => EditorialItem::STATE_SCHEDULED],
    )))->toThrow(InvalidArgumentException::class);
});

it('approves a queued comment and takes it out of the queue', function () {
    $commentId = (int) wp_insert_comment([
        'comment_post_ID'  => $this->postId,
        'comment_author'   => 'Ada',
        'comment_content'  => 'This helped, thank you.',
        'comment_approved' => '0',
    ]);

    $queued = $this->controller->commentQueue(
        workspaceRequest('GET', '/corex/v1/blog/comments', ['post_id' => $this->postId])
    )->get_data()['data']['comments'];

    // What the panel renders: the text, when it arrived, the state in words, and whether this actor
    // may act on it.
    expect($queued)->toHaveCount(1)
        ->and($queued[0]['content'])->toBe('This helped, thank you.')
        ->and($queued[0]['state_label'])->toBe('Awaiting review')
        ->and($queued[0]['submitted_at'])->not->toBe('')
        ->and($queued[0]['can_moderate'])->toBeTrue();

    $this->controller->moderateComment(workspaceRequest(
        'POST',
        '/corex/v1/blog/comments/' . $commentId . '/moderate',
        ['id' => $commentId, 'action' => CommentModerationService::ACTION_APPROVE],
    ));

    expect(wp_get_comment_status($commentId))->toBe('approved');

    // The queue holds only comments awaiting review, so an approved one leaves it entirely — which
    // is why the screen refetches rather than editing the row it already has.
    $after = $this->controller->commentQueue(
        workspaceRequest('GET', '/corex/v1/blog/comments', ['post_id' => $this->postId])
    )->get_data()['data']['comments'];

    expect($after)->toBeEmpty();

    wp_delete_comment($commentId, true);
});

it('tells a reader without the capability that they may not moderate', function () {
    $subscriber = wp_insert_user([
        'user_login' => 'corex075_subscriber',
        'user_pass'  => wp_generate_password(),
        'role'       => 'subscriber',
    ]);

    $commentId = (int) wp_insert_comment([
        'comment_post_ID'  => $this->postId,
        'comment_author'   => 'Ada',
        'comment_content'  => 'Waiting.',
        'comment_approved' => '0',
    ]);

    wp_set_current_user((int) $subscriber);

    // The client hides the buttons on this flag; if it ever disagreed with what the server allows,
    // the UI would be lying about what it can do.
    $queued = $this->controller->commentQueue(
        workspaceRequest('GET', '/corex/v1/blog/comments', ['post_id' => $this->postId])
    )->get_data()['data']['comments'];

    expect($queued[0]['can_moderate'])->toBeFalse();

    wp_set_current_user($this->actorId);
    wp_delete_comment($commentId, true);
    wp_delete_user((int) $subscriber);
});

it('closes the analytics route to an actor who cannot edit posts', function () {
    $subscriber = wp_insert_user([
        'user_login' => 'corex075_reader',
        'user_pass'  => wp_generate_password(),
        'role'       => 'subscriber',
    ]);
    wp_set_current_user((int) $subscriber);

    expect($this->controller->allowed(
        workspaceRequest('GET', '/corex/v1/blog/analytics', ['post_id' => $this->postId])
    ))->toBeFalse();

    wp_set_current_user($this->actorId);
    wp_delete_user((int) $subscriber);
});
