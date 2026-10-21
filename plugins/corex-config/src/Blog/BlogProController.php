<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Blog;

defined('ABSPATH') || exit;

use Corex\Http\RouteParam;
use DateTimeImmutable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST boundary for Blog Pro analytics, editorial workflow, comments, authors, and sharing.
 */
final class BlogProController
{
    private const NAMESPACE = 'corex/v1';

    public function __construct(
        private readonly BlogProServices $services,
        private readonly BlogProPermissions $permissions,
    ) {
    }

    public function register(): void
    {
        register_rest_route(self::NAMESPACE, '/blog/analytics', [
            'methods' => 'GET',
            'callback' => [$this, 'analytics'],
            'permission_callback' => [$this, 'mayWorkOnPost'],
        ]);
        register_rest_route(self::NAMESPACE, '/blog/share-controls', [
            'methods' => 'GET',
            'callback' => [$this, 'shareControls'],
            'permission_callback' => [$this, 'mayWorkOnPost'],
        ]);
        register_rest_route(self::NAMESPACE, '/blog/share-click', [
            'methods' => 'POST',
            'callback' => [$this, 'shareClick'],
            'permission_callback' => [$this, 'hasNonce'],
        ]);
        register_rest_route(self::NAMESPACE, '/blog/editorial/(?P<id>\d+)/transition', [
            'methods' => 'POST',
            'callback' => [$this, 'transition'],
            'permission_callback' => [$this, 'mayMovePost'],
        ]);
        register_rest_route(self::NAMESPACE, '/blog/comments', [
            'methods' => 'GET',
            'callback' => [$this, 'commentQueue'],
            'permission_callback' => [$this, 'mayWorkOnPost'],
        ]);
        register_rest_route(self::NAMESPACE, '/blog/comments/(?P<id>\d+)/moderate', [
            'methods' => 'POST',
            'callback' => [$this, 'moderateComment'],
            'permission_callback' => [$this, 'mayActOnComment'],
        ]);
        register_rest_route(self::NAMESPACE, '/blog/authors', [
            'methods' => 'GET',
            'callback' => [$this, 'authorAnalytics'],
            'permission_callback' => [$this, 'allowed'],
        ]);
    }

    /** The base permission, for the routes about no one post: the authors table. */
    public function allowed(WP_REST_Request $request): bool|WP_Error
    {
        return $this->answer($request, $this->permissions->canOpen(), __('You do not have permission to use Blog Pro.', 'corex'));
    }

    public function mayWorkOnPost(WP_REST_Request $request): bool|WP_Error
    {
        return $this->answer(
            $request,
            $this->permissions->mayWorkOnPost($this->postId($request)),
            __('You do not have permission to work on this post.', 'corex'),
        );
    }

    public function mayMovePost(WP_REST_Request $request): bool|WP_Error
    {
        return $this->answer(
            $request,
            $this->permissions->mayMovePost($this->postId($request), sanitize_key((string) $request->get_param('state'))),
            __('You do not have permission to move this post to that state.', 'corex'),
        );
    }

    public function mayActOnComment(WP_REST_Request $request): bool|WP_Error
    {
        return $this->answer(
            $request,
            $this->permissions->mayActOnComment(RouteParam::int($request), sanitize_key((string) $request->get_param('action'))),
            __('You do not have permission to do that to this comment.', 'corex'),
        );
    }

    /** A refusal names what is not allowed (spec 112, FR-025); a missing nonce is refused as before. */
    private function answer(WP_REST_Request $request, bool $allowed, string $refusal): bool|WP_Error
    {
        if (! $this->hasNonce($request)) {
            return false;
        }

        return $allowed ? true : new WP_Error('rest_forbidden', $refusal, ['status' => 403]);
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

    /**
     * Counts nothing until reading data is collected (spec 112, FR-027): nothing calls it, and an
     * open address that writes a row per call is a cost with no reader.
     */
    public function shareClick(WP_REST_Request $request): WP_REST_Response
    {
        return $this->ok([
            'recorded' => false,
            'target' => null,
        ]);
    }

    public function transition(WP_REST_Request $request): WP_REST_Response
    {
        $item = $this->services->editorial->transition(new EditorialTransitionRequest(
            postId: RouteParam::int($request),
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
            commentId: RouteParam::int($request),
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
            'authors' => $this->authors(...$this->range($request)),
        ]);
    }

    /**
     * Every author for people who may edit others' posts; otherwise only the person's own line.
     *
     * @return list<array<string,mixed>>
     */
    private function authors(DateTimeImmutable $since, DateTimeImmutable $until): array
    {
        return $this->permissions->seesEveryAuthor()
            ? $this->services->authors->authors($since, $until)
            : $this->services->authors->authorsAmong([get_current_user_id()], $since, $until);
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

    /** The post a route is about: the one in its path, else the one named as `post_id`. */
    private function postId(WP_REST_Request $request): int
    {
        return RouteParam::int($request) ?: absint($request->get_param('post_id'));
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
        return BlogProPresenter::editorialItem($item, $this->permissions);
    }

    /**
     * @return array<string,mixed>
     */
    private function commentItem(CommentModerationItem $comment): array
    {
        return BlogProPresenter::commentItem($comment, $this->permissions);
    }

    /**
     * @param array<string,mixed> $data
     */
    private function ok(array $data): WP_REST_Response
    {
        return new WP_REST_Response(['data' => $data], 200);
    }
}
