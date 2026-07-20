<?php

/**
 * Integration REST contracts for Blog Pro analytics, editorial workflow, comments, authors, and sharing.
 *
 * @package Corex\Tests\Integration\Blog
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Blog\BlogAnalyticsService;
use Corex\Config\Blog\BlogProController;
use Corex\Config\Blog\EditorialItem;
use Corex\Config\Blog\ReadingEventTable;
use Corex\Database\Schema\Migrator;

function blogRestRequest(string $method, string $route, array $payload = []): WP_REST_Request
{
    $request = new WP_REST_Request($method, $route);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    $method === 'GET' ? $request->set_query_params($payload) : $request->set_body_params($payload);

    return $request;
}

beforeEach(function () {
    if (! function_exists('wp_insert_post') || ! class_exists(Boot::class)) {
        $this->markTestSkipped('WordPress is not loaded for Blog Pro REST integration tests.');
    }

    $this->container = Boot::app()->container();
    $this->container->make(Migrator::class)->create($this->container->make(ReadingEventTable::class)->schema());
    $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    $this->actorId = (int) ($admins[0] ?? 0);
    wp_set_current_user($this->actorId);
    $this->controller = $this->container->make(BlogProController::class);
    $this->postId = wp_insert_post([
        'post_title' => 'CoreX Blog REST test',
        'post_content' => 'REST contract content',
        'post_status' => 'draft',
        'post_type' => 'post',
        'comment_status' => 'open',
    ], true);

    if ($this->postId instanceof WP_Error || (int) $this->postId < 1) {
        $this->markTestSkipped('Could not create a native post for Blog Pro REST integration tests.');
    }
});

afterEach(function () {
    if (! empty($this->postId) && function_exists('wp_delete_post')) {
        wp_delete_post((int) $this->postId, true);
    }
});

it('registers the Blog Pro REST routes', function () {
    add_action('rest_api_init', [$this->controller, 'register']);
    do_action('rest_api_init', rest_get_server());

    expect(rest_get_server()->get_routes())->toHaveKeys([
        '/corex/v1/blog/analytics',
        '/corex/v1/blog/share-controls',
        '/corex/v1/blog/share-click',
        '/corex/v1/blog/editorial/(?P<id>\d+)/transition',
        '/corex/v1/blog/comments',
        '/corex/v1/blog/comments/(?P<id>\d+)/moderate',
        '/corex/v1/blog/authors',
    ]);
});

it('returns real analytics and configured share controls for a native post', function () {
    // Relative to now, not a fixed date: the request below asks for the last 7 days, so a
    // hardcoded timestamp silently falls out of range as the calendar moves and the test starts
    // reporting zero views for reasons that have nothing to do with the code under test.
    $now = new DateTimeImmutable('-1 day');
    $analytics = $this->container->make(BlogAnalyticsService::class);
    $analytics->recordView((int) $this->postId, 'session-a', '203.0.113.8', 'Browser', true, $now);
    $analytics->recordRead((int) $this->postId, 'session-a', '203.0.113.8', 'Browser', 45, true, $now);

    $analyticsResponse = $this->controller->analytics(blogRestRequest('GET', '/corex/v1/blog/analytics', [
        'post_id' => (int) $this->postId,
        'range' => 7,
    ]));
    $shareResponse = $this->controller->shareControls(blogRestRequest('GET', '/corex/v1/blog/share-controls', [
        'post_id' => (int) $this->postId,
    ]));

    expect($analyticsResponse->get_status())->toBe(200)
        ->and($analyticsResponse->get_data()['data']['views'])->toBe(1)
        ->and($analyticsResponse->get_data()['data']['reads'])->toBe(1)
        ->and($analyticsResponse->get_data()['data']['average_read_seconds'])->toBe(45)
        ->and($shareResponse->get_data()['data']['controls'])->not->toBeEmpty()
        ->and(array_column($shareResponse->get_data()['data']['controls'], 'target'))->toContain('copy_link');
});

it('transitions editorial state and moderates native comments through REST methods', function () {
    $transition = blogRestRequest('POST', '/corex/v1/blog/editorial/' . (int) $this->postId . '/transition', [
        'state' => EditorialItem::STATE_READY_FOR_REVIEW,
        'assignee_id' => $this->actorId,
        'note' => 'Ready for editorial review.',
    ]);
    $transition->set_param('id', (int) $this->postId);
    $transitionResponse = $this->controller->transition($transition);

    $commentId = wp_insert_comment([
        'comment_post_ID' => (int) $this->postId,
        'comment_author' => 'REST Commenter',
        'comment_author_email' => 'rest-commenter@example.test',
        'comment_content' => 'Please approve this.',
        'comment_approved' => '0',
    ]);
    $moderate = blogRestRequest('POST', '/corex/v1/blog/comments/' . (int) $commentId . '/moderate', [
        'action' => 'approve',
    ]);
    $moderate->set_param('id', (int) $commentId);
    $moderateResponse = $this->controller->moderateComment($moderate);

    expect($transitionResponse->get_status())->toBe(200)
        ->and($transitionResponse->get_data()['data']['editorial_state'])->toBe(EditorialItem::STATE_READY_FOR_REVIEW)
        ->and(get_post((int) $this->postId)?->post_status)->toBe('pending')
        ->and($moderateResponse->get_data()['data']['state'])->toBe('approved')
        ->and(get_comment((int) $commentId)?->comment_approved)->toBe('1');
});
