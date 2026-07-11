<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Activity;

defined('ABSPATH') || exit;

use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;
use Corex\Http\ResponseEnvelope;
use DateTimeImmutable;
use Exception;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Thin, read-only REST boundary for the capability-gated activity stream.
 */
final class ActivityController
{
    public function __construct(private readonly ActivityService $activity)
    {
    }

    public function register(): void
    {
        register_rest_route('corex/v1', '/activity', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => [$this, 'canManage'],
        ]);

        register_rest_route('corex/v1', '/activity/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'show'],
            'permission_callback' => [$this, 'canManage'],
        ]);
    }

    public function canManage(): bool
    {
        return current_user_can('manage_options');
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $page    = max(1, (int) ($request->get_param('page') ?: 1));
        $perPage = min(100, max(1, (int) ($request->get_param('per_page') ?: 20)));
        $events  = array_map(
            static fn (ActivityEvent $event): array => $event->toArray(),
            $this->activity->query($this->filters($request), $page, $perPage),
        );

        return new WP_REST_Response(ResponseEnvelope::success([
            'events'   => $events,
            'page'     => $page,
            'per_page' => $perPage,
        ])->toArray());
    }

    public function show(WP_REST_Request $request): WP_REST_Response
    {
        $event = $this->activity->find((int) $request->get_param('id'));

        if ($event === null) {
            return new WP_REST_Response(
                ResponseEnvelope::error('activity_not_found', __('That activity event was not found.', 'corex'))->toArray(),
                404,
            );
        }

        return new WP_REST_Response(ResponseEnvelope::success(['event' => $event->toArray()])->toArray());
    }

    /** @return array<string,mixed> */
    private function filters(WP_REST_Request $request): array
    {
        $filters = [];

        foreach (['area', 'outcome', 'sensitivity'] as $key) {
            $value = sanitize_key((string) $request->get_param($key));
            if ($value !== '') {
                $filters[$key] = $value;
            }
        }

        $kind = strtolower(sanitize_text_field((string) $request->get_param('kind')));
        if (preg_match('/^[a-z][a-z0-9_.-]*$/', $kind) === 1) {
            $filters['kind'] = $kind;
        }

        $actorId = absint($request->get_param('actor_id'));
        if ($actorId > 0) {
            $filters['actor_id'] = $actorId;
        }

        foreach (['date_from', 'date_to'] as $key) {
            $date = $this->date((string) $request->get_param($key));
            if ($date !== null) {
                $filters[$key] = $date;
            }
        }

        return $filters;
    }

    private function date(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }
}
