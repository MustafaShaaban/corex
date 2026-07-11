<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Blog;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST boundary for Blog Pro analytics, editorial workflow, comments, authors, and sharing.
 */
final class BlogProController
{
    private const NAMESPACE = 'corex/v1';

    public function __construct(private readonly BlogProServices $services)
    {
    }

    public function register(): void
    {
        register_rest_route(self::NAMESPACE, '/blog/analytics', [
            'methods' => 'GET',
            'callback' => [$this, 'analytics'],
            'permission_callback' => [$this, 'allowed'],
        ]);
        register_rest_route(self::NAMESPACE, '/blog/share-controls', [
            'methods' => 'GET',
            'callback' => [$this, 'shareControls'],
            'permission_callback' => [$this, 'allowed'],
        ]);
        register_rest_route(self::NAMESPACE, '/blog/share-click', [
            'methods' => 'POST',
            'callback' => [$this, 'shareClick'],
            'permission_callback' => [$this, 'hasNonce'],
        ]);
        register_rest_route(self::NAMESPACE, '/blog/editorial/(?P<id>\d+)/transition', [
            'methods' => 'POST',
            'callback' => [$this, 'transition'],
            'permission_callback' => [$this, 'allowed'],
        ]);
        register_rest_route(self::NAMESPACE, '/blog/comments', [
            'methods' => 'GET',
            'callback' => [$this, 'commentQueue'],
            'permission_callback' => [$this, 'allowed'],
        ]);
        register_rest_route(self::NAMESPACE, '/blog/comments/(?P<id>\d+)/moderate', [
            'methods' => 'POST',
            'callback' => [$this, 'moderateComment'],
            'permission_callback' => [$this, 'allowed'],
        ]);
        register_rest_route(self::NAMESPACE, '/blog/authors', [
            'methods' => 'GET',
            'callback' => [$this, 'authorAnalytics'],
            'permission_callback' => [$this, 'allowed'],
        ]);
    }

    public function allowed(WP_REST_Request $request): bool
    {
        return $this->hasNonce($request) && current_user_can('edit_posts');
    }

    public function hasNonce(WP_REST_Request $request): bool
    {
        $nonce = (string) ($request->get_header('X-WP-Nonce') ?: $request->get_param('_wpnonce'));

        return $nonce !== '' && wp_verify_nonce($nonce, 'wp_rest') !== false;
    }

    public function analytics(WP_REST_Request $request): WP_REST_Response
    {
        $aggregate = $this->services->analytics->aggregate(
            $this->postId($request),
            ...$this->range($request),
        );

        return $this->ok([
            'post_id' => $aggregate->postId,
            'views' => $aggregate->views,
            'reads' => $aggregate->reads,
            'share_clicks' => $aggregate->shareClicks,
            'unique_visitors' => $aggregate->uniqueVisitors,
            'average_read_seconds' => $aggregate->averageReadSeconds,
        ]);
    }

    public function shareControls(WP_REST_Request $request): WP_REST_Response
    {
        $postId = $this->postId($request);

        return $this->ok([
            'post_id' => $postId,
            'controls' => $this->services->sharing->controls(
                $postId,
                get_permalink($postId) ?: '',
                get_the_title($postId),
            ),
        ]);
    }

    public function shareClick(WP_REST_Request $request): WP_REST_Response
    {
        $event = $this->services->sharing->recordShareClick(
            $this->postId($request),
            sanitize_key((string) $request->get_param('target')),
            sanitize_text_field((string) $request->get_param('visitor_key')),
            $this->serverValue('REMOTE_ADDR'),
            $this->serverValue('HTTP_USER_AGENT'),
            (bool) $request->get_param('consented'),
            new DateTimeImmutable('now'),
        );

        return $this->ok([
            'recorded' => $event !== null,
            'target' => $event?->shareTarget,
        ]);
    }

    public function transition(WP_REST_Request $request): WP_REST_Response
    {
        $item = $this->services->editorial->transition(new EditorialTransitionRequest(
            postId: (int) $request->get_param('id'),
            state: sanitize_key((string) $request->get_param('state')),
            actorId: get_current_user_id(),
            note: sanitize_textarea_field((string) $request->get_param('note')),
            assigneeId: $this->optionalInt($request->get_param('assignee_id')),
            dueAt: $this->optionalDate($request->get_param('due_at')),
            scheduledAt: $this->optionalDate($request->get_param('scheduled_at')),
            occurredAt: new DateTimeImmutable('now'),
        ));

        return $this->ok($this->editorialItem($item));
    }

    public function commentQueue(WP_REST_Request $request): WP_REST_Response
    {
        return $this->ok([
            'comments' => array_map($this->commentItem(...), $this->services->comments->queue($this->postId($request))),
        ]);
    }

    public function moderateComment(WP_REST_Request $request): WP_REST_Response
    {
        $result = $this->services->comments->moderate(new CommentModerationRequest(
            commentId: (int) $request->get_param('id'),
            action: sanitize_key((string) $request->get_param('action')),
            actorId: get_current_user_id(),
            body: sanitize_textarea_field((string) $request->get_param('body')),
        ));

        return $this->ok([
            'comment_id' => $result->commentId,
            'action' => $result->action,
            'state' => $result->state,
            'created_comment_id' => $result->createdCommentId,
        ]);
    }

    public function authorAnalytics(WP_REST_Request $request): WP_REST_Response
    {
        return $this->ok([
            'authors' => $this->services->authors->authors(...$this->range($request)),
        ]);
    }

    /**
     * @return array{0:DateTimeImmutable,1:DateTimeImmutable}
     */
    private function range(WP_REST_Request $request): array
    {
        $days = max(1, min(90, absint($request->get_param('range') ?: 30)));
        $until = new DateTimeImmutable('+1 day');

        return [$until->modify(sprintf('-%d days', $days)), $until];
    }

    private function postId(WP_REST_Request $request): int
    {
        return absint($request->get_param('post_id') ?: $request->get_param('id'));
    }

    private function optionalInt(mixed $value): ?int
    {
        $value = absint($value);

        return $value > 0 ? $value : null;
    }

    private function optionalDate(mixed $value): ?DateTimeImmutable
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : new DateTimeImmutable($value);
    }

    private function serverValue(string $key): string
    {
        return sanitize_text_field(wp_unslash((string) ($_SERVER[$key] ?? '')));
    }

    /**
     * @return array<string,mixed>
     */
    private function editorialItem(EditorialItem $item): array
    {
        return [
            'post_id' => $item->postId,
            'editorial_state' => $item->editorialState,
            'native_status' => $item->nativeStatus,
            'assignee_id' => $item->assigneeId,
            'due_at' => $item->dueAt?->format(DATE_ATOM),
            'scheduled_at' => $item->scheduledAt?->format(DATE_ATOM),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function commentItem(CommentModerationItem $comment): array
    {
        return [
            'comment_id' => $comment->commentId,
            'post_id' => $comment->postId,
            'author' => $comment->author,
            'state' => $comment->state,
            'first_comment' => $comment->firstComment,
            'likely_spam' => $comment->likelySpam,
            'held_for_review' => $comment->heldForReview,
        ];
    }

    /**
     * @param array<string,mixed> $data
     */
    private function ok(array $data): WP_REST_Response
    {
        return new WP_REST_Response(['data' => $data], 200);
    }
}
