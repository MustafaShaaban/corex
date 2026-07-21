<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Notifications;

defined('ABSPATH') || exit;

use Corex\Access\CorexAbility;
use Corex\Http\ResponseEnvelope;
use Corex\Notifications\NotificationQuery;
use Corex\Notifications\NotificationService;
use DateTimeImmutable;
use Exception;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST boundary for the Notification Center (corex/v1/notifications). Two tiers gate it: the
 * read/own-action tier is any signed-in user acting on their own notifications — the service
 * re-checks visibility on every call, so one user can never read or touch another's; the manage tier
 * ({@see CorexAbility::MANAGE_NOTIFICATIONS}) governs resolving a shared condition. Every mutation
 * also carries a REST nonce. Reads are bounded and paginated; every response is enveloped.
 */
final class NotificationController
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function register(): void
    {
        register_rest_route('corex/v1', '/notifications', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => [$this, 'canRead'],
        ]);
        register_rest_route('corex/v1', '/notifications/count', [
            'methods'             => 'GET',
            'callback'            => [$this, 'count'],
            'permission_callback' => [$this, 'canRead'],
        ]);
        register_rest_route('corex/v1', '/notifications/read-all', [
            'methods'             => 'POST',
            'callback'            => [$this, 'readAll'],
            'permission_callback' => [$this, 'canAct'],
        ]);
        register_rest_route('corex/v1', '/notifications/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'show'],
            'permission_callback' => [$this, 'canRead'],
        ]);
        foreach (['read', 'unread', 'dismiss', 'snooze'] as $action) {
            register_rest_route('corex/v1', '/notifications/(?P<id>\d+)/' . $action, [
                'methods'             => 'POST',
                'callback'            => [$this, $action],
                'permission_callback' => [$this, 'canAct'],
            ]);
        }
        register_rest_route('corex/v1', '/notifications/(?P<id>\d+)/resolve', [
            'methods'             => 'POST',
            'callback'            => [$this, 'resolve'],
            'permission_callback' => [$this, 'canManage'],
        ]);
    }

    public function canRead(): bool
    {
        return is_user_logged_in();
    }

    public function canAct(WP_REST_Request $request): bool
    {
        return is_user_logged_in() && $this->hasNonce($request);
    }

    public function canManage(WP_REST_Request $request): bool
    {
        return current_user_can(CorexAbility::MANAGE_NOTIFICATIONS) && $this->hasNonce($request);
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $query = NotificationQuery::fromRequest(
            [
                'category'      => $request->get_param('category'),
                'severity'      => $request->get_param('severity'),
                'status'        => $request->get_param('status'),
                'source_module' => $request->get_param('source_module'),
                'unread_only'   => (bool) $request->get_param('unread_only'),
            ],
            max(1, (int) ($request->get_param('page') ?: 1)),
            (int) ($request->get_param('per_page') ?: 20),
        );

        return $this->ok($this->notifications->forCurrentActor($query));
    }

    public function count(): WP_REST_Response
    {
        return $this->ok(['unread' => $this->notifications->unreadCountForCurrentActor()]);
    }

    public function show(WP_REST_Request $request): WP_REST_Response
    {
        $notification = $this->notifications->findForCurrentActor($this->id($request));

        return $notification === null
            ? $this->missing()
            : $this->ok(['notification' => $notification]);
    }

    public function read(WP_REST_Request $request): WP_REST_Response
    {
        return $this->settled($this->notifications->markReadForCurrentActor($this->id($request)));
    }

    public function unread(WP_REST_Request $request): WP_REST_Response
    {
        return $this->settled($this->notifications->markUnreadForCurrentActor($this->id($request)));
    }

    public function dismiss(WP_REST_Request $request): WP_REST_Response
    {
        return $this->settled($this->notifications->dismissForCurrentActor($this->id($request)));
    }

    public function snooze(WP_REST_Request $request): WP_REST_Response
    {
        $until = $this->futureDate((string) $request->get_param('until'));
        if ($until === null) {
            return new WP_REST_Response(
                ResponseEnvelope::error('invalid_snooze', __('Provide a future date to snooze until.', 'corex'))->toArray(),
                422,
            );
        }

        return $this->settled($this->notifications->snoozeForCurrentActor($this->id($request), $until));
    }

    public function readAll(): WP_REST_Response
    {
        return $this->ok(['marked' => $this->notifications->markAllReadForCurrentActor()]);
    }

    public function resolve(WP_REST_Request $request): WP_REST_Response
    {
        $reason = sanitize_text_field((string) $request->get_param('reason'));
        if ($reason === '') {
            $reason = __('Resolved.', 'corex');
        }

        return $this->settled($this->notifications->resolveById($this->id($request), $reason));
    }

    private function id(WP_REST_Request $request): int
    {
        return (int) $request->get_param('id');
    }

    private function hasNonce(WP_REST_Request $request): bool
    {
        return wp_verify_nonce((string) $request->get_header('X-WP-Nonce'), 'wp_rest') !== false;
    }

    private function futureDate(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            $date = new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }

        return $date > new DateTimeImmutable('now') ? $date : null;
    }

    /** A mutation that reports success only when the notification was the actor's to change. */
    private function settled(bool $changed): WP_REST_Response
    {
        return $changed ? $this->ok(['ok' => true]) : $this->missing();
    }

    private function missing(): WP_REST_Response
    {
        return new WP_REST_Response(
            ResponseEnvelope::error('notification_not_found', __('That notification was not found.', 'corex'))->toArray(),
            404,
        );
    }

    /** @param array<string,mixed> $data */
    private function ok(array $data): WP_REST_Response
    {
        return new WP_REST_Response(ResponseEnvelope::success($data)->toArray());
    }
}
