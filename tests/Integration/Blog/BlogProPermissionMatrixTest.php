<?php

/**
 * Blog Pro allows each role exactly what WordPress allows it on the post or comment in question,
 * and comment moderation needs comment moderation rights (spec 112, User Story 5, FR-024 to
 * FR-028, SC-006).
 *
 * Through the real REST server. A `rest_dispatch_request` filter answers in every handler's place,
 * so what is measured is whether the route lets the request through, and no post or comment of
 * the test's own is changed by it. Every account, post and comment here is the test's own.
 *
 * @package Corex\Tests\Integration\Blog
 */

declare(strict_types=1);

const BLOG_MATRIX_REACHED = 'corex_test_blog_handler_reached';

/** @return int The new account's id. */
function blogMatrixUser(string $role): int
{
    $id = wp_insert_user([
        'user_login' => 'corex-blog-' . $role . '-' . wp_generate_password(6, false),
        'user_pass'  => wp_generate_password(),
        'user_email' => uniqid('corex-blog-', true) . '@example.test',
        'role'       => $role,
    ]);
    expect($id)->toBeInt();

    return $id;
}

function blogMatrixPost(int $author, string $status): int
{
    return (int) wp_insert_post([
        'post_title'     => 'Blog Pro matrix ' . $status,
        'post_status'    => $status,
        'post_type'      => 'post',
        'post_author'    => $author,
        'comment_status' => 'open',
    ]);
}

function blogMatrixHeldComment(int $postId): int
{
    return (int) wp_insert_comment([
        'comment_post_ID'  => $postId,
        'comment_author'   => 'A visitor',
        'comment_content'  => 'Held for review.',
        'comment_approved' => 0,
    ]);
}

/** @param array<string,mixed> $body */
function blogMatrixCall(string $method, string $route, array $body = []): WP_REST_Response
{
    $request = new WP_REST_Request($method, '/corex/v1/blog/' . $route);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    $method === 'GET' ? $request->set_query_params($body) : $request->set_body_params($body);

    return rest_do_request($request);
}

beforeEach(function () {
    $this->admin = blogMatrixUser('administrator');
    $this->users = [
        'contributor'   => blogMatrixUser('contributor'),
        'author'        => blogMatrixUser('author'),
        'editor'        => blogMatrixUser('editor'),
        'administrator' => $this->admin,
    ];

    $this->otherDraft     = blogMatrixPost($this->admin, 'draft');
    $this->otherPublished = blogMatrixPost($this->admin, 'publish');
    $this->otherHeld      = blogMatrixHeldComment($this->otherPublished);
    $this->ownDraft       = array_map(static fn (int $user): int => blogMatrixPost($user, 'draft'), $this->users);
    $this->authorsOwnPost = blogMatrixPost($this->users['author'], 'publish');
    $this->authorsOwnHeld = blogMatrixHeldComment($this->authorsOwnPost);

    add_filter('rest_dispatch_request', static fn () => new WP_REST_Response(['data' => BLOG_MATRIX_REACHED], 200));

    // Asks the route as this role, and answers whether it was let through.
    $this->lets = function (string $role, string $method, string $route, array $body = []): bool {
        wp_set_current_user($this->users[$role]);
        $response = blogMatrixCall($method, $route, $body);

        return $response->get_status() === 200;
    };
});

afterEach(function () {
    remove_all_filters('rest_dispatch_request');
    wp_set_current_user(0);

    foreach ([$this->otherHeld, $this->authorsOwnHeld] as $comment) {
        wp_delete_comment($comment, true);
    }
    foreach ([$this->otherDraft, $this->otherPublished, $this->authorsOwnPost, ...array_values($this->ownDraft)] as $post) {
        wp_delete_post($post, true);
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($this->users as $user) {
        wp_delete_user($user);
    }
});

/** contributor, author, editor, administrator */
const BLOG_ROLES = ['contributor', 'author', 'editor', 'administrator'];

it('shows a post\'s figures, share controls and held comments only to people who may edit that post', function (string $route) {
    $allowed = fn (string $postKey): array => array_map(
        fn (string $role): bool => ($this->lets)($role, 'GET', $route, [
            'post_id' => $postKey === 'own' ? $this->ownDraft[$role] : $this->{$postKey},
        ]),
        BLOG_ROLES,
    );

    expect($allowed('own'))->toBe([true, true, true, true])
        ->and($allowed('otherDraft'))->toBe([false, false, true, true])
        ->and($allowed('otherPublished'))->toBe([false, false, true, true]);
})->with(['analytics', 'share-controls', 'comments']);

it('moves a post only for people who may edit it, and publishes or schedules only for people who may publish it', function () {
    $moves = fn (string $postKey, string $state): array => array_map(
        fn (string $role): bool => ($this->lets)($role, 'POST', 'editorial/' . ($postKey === 'own' ? $this->ownDraft[$role] : $this->{$postKey}) . '/transition', [
            'state'        => $state,
            'scheduled_at' => '2030-01-01T10:00:00+00:00',
        ]),
        BLOG_ROLES,
    );

    expect($moves('own', 'ready_for_review'))->toBe([true, true, true, true])
        ->and($moves('own', 'published'))->toBe([false, true, true, true])
        ->and($moves('own', 'scheduled'))->toBe([false, true, true, true])
        ->and($moves('otherDraft', 'ready_for_review'))->toBe([false, false, true, true])
        ->and($moves('otherPublished', 'draft'))->toBe([false, false, true, true]);
});

it('approves, spams, trashes and replies only for people with comment moderation rights', function (string $action) {
    $acts = fn (int $comment): array => array_map(
        fn (string $role): bool => ($this->lets)($role, 'POST', "comments/{$comment}/moderate", ['action' => $action, 'body' => 'Thanks.']),
        BLOG_ROLES,
    );

    expect($acts($this->otherHeld))->toBe([false, false, true, true])
        // An author's own held comment on their own post follows the moderation rule (the edge case).
        ->and($acts($this->authorsOwnHeld))->toBe([false, false, true, true]);
})->with(['approve', 'spam', 'trash', 'reply']);

it('edits a comment only for people who may edit that comment', function () {
    $edits = fn (int $comment): array => array_map(
        fn (string $role): bool => ($this->lets)($role, 'POST', "comments/{$comment}/moderate", ['action' => 'edit', 'body' => 'Edited.']),
        BLOG_ROLES,
    );

    expect($edits($this->otherHeld))->toBe([false, false, true, true])
        ->and($edits($this->authorsOwnHeld))->toBe([false, true, true, true]);
});

it('opens to anyone who may edit posts, and to nobody else', function () {
    $subscriber = blogMatrixUser('subscriber');
    $this->users['subscriber'] = $subscriber;

    expect(($this->lets)('subscriber', 'GET', 'authors'))->toBeFalse()
        ->and(($this->lets)('subscriber', 'GET', 'analytics', ['post_id' => $this->otherPublished]))->toBeFalse()
        ->and(array_map(fn (string $role): bool => ($this->lets)($role, 'GET', 'authors'), BLOG_ROLES))->toBe([true, true, true, true]);
});

it('refuses a post id that is not a post anyone may edit', function () {
    expect(($this->lets)('administrator', 'GET', 'analytics', ['post_id' => 0]))->toBeFalse();
});

it('answers a share click without recording it', function () {
    remove_all_filters('rest_dispatch_request');
    wp_set_current_user(0);

    $answer = blogMatrixCall('POST', 'share-click', ['post_id' => $this->otherPublished, 'target' => 'x', 'consented' => true])->get_data();

    expect($answer['data']['recorded'])->toBeFalse();
});

/** The Blog Pro screen's configuration as this role would be sent it, for this post. */
function blogMatrixScreen(int $userId, int $postId): array
{
    wp_set_current_user($userId);
    $_GET['post'] = (string) $postId;
    $screen = \Corex\Boot::app()->container()->make(\Corex\Config\Blog\BlogProScreen::class);
    $config = (new ReflectionMethod($screen, 'clientConfig'))->invoke($screen);
    unset($_GET['post']);

    return $config;
}

it('lists on the screen only the posts this person may work on, and only their own author line', function () {
    $config = blogMatrixScreen($this->users['author'], $this->ownDraft['author']);
    $listed = array_column($config['posts'], 'id');

    expect($listed)->toContain($this->ownDraft['author'], $this->authorsOwnPost)
        ->and($listed)->not->toContain($this->otherDraft, $this->otherPublished)
        ->and($config['authors'])->toHaveCount(1);
});

it('offers a contributor no move to Published or Scheduled, and an author both', function () {
    $offered = fn (string $role): array => array_column(
        blogMatrixScreen($this->users[$role], $this->ownDraft[$role])['editorial']['transitions'],
        'key',
    );

    expect($offered('contributor'))->not->toContain('published', 'scheduled')
        ->and($offered('contributor'))->toContain('ready_for_review')
        ->and($offered('author'))->toContain('published', 'scheduled');
});
