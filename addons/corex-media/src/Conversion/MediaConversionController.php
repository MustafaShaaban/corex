<?php

/**
 * @package Corex\Media
 */

declare(strict_types=1);

namespace Corex\Media\Conversion;

defined('ABSPATH') || exit;

use Corex\Http\ResponseEnvelope;
use WP_REST_Request;
use WP_REST_Response;

/**
 * The routes Settings > Media drives a library conversion through (spec 119, US-SET-06). Each needs
 * `manage_options` and a REST nonce; each answers the run as the page shows it.
 */
final class MediaConversionController
{
    public function __construct(private readonly MediaConversionService $conversion)
    {
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'routes']);
    }

    public function routes(): void
    {
        register_rest_route('corex/v1', '/media/conversion', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'show'],
                'permission_callback' => [$this, 'canManage'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'start'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);
        register_rest_route('corex/v1', '/media/conversion/advance', [
            'methods'             => 'POST',
            'callback'            => [$this, 'advance'],
            'permission_callback' => [$this, 'canManage'],
        ]);
        register_rest_route('corex/v1', '/media/conversion/cancel', [
            'methods'             => 'POST',
            'callback'            => [$this, 'cancel'],
            'permission_callback' => [$this, 'canManage'],
        ]);
    }

    public function canManage(WP_REST_Request $request): bool
    {
        return current_user_can('manage_options')
            && wp_verify_nonce((string) $request->get_header('X-WP-Nonce'), 'wp_rest') !== false;
    }

    public function show(): WP_REST_Response
    {
        return $this->answer($this->conversion->status());
    }

    public function start(): WP_REST_Response
    {
        $started = $this->conversion->start(get_current_user_id());

        return match ($started['error']) {
            'conversion_running'     => $this->refused('conversion_running', __('A conversion is already running.', 'corex')),
            'conversion_unavailable' => $this->refused(
                'conversion_unavailable',
                $this->conversion->unavailableReason() !== ''
                    ? $this->conversion->unavailableReason()
                    : __('The conversion could not be scheduled on this site.', 'corex'),
            ),
            default => $this->answer($started['run']),
        };
    }

    public function advance(): WP_REST_Response
    {
        return $this->answer($this->conversion->advance());
    }

    public function cancel(): WP_REST_Response
    {
        $run = $this->conversion->cancel();

        return $run === null
            ? $this->refused('conversion_not_running', __('No conversion is running.', 'corex'))
            : $this->answer($run);
    }

    /** @param array<string,mixed>|null $run */
    private function answer(?array $run): WP_REST_Response
    {
        return new WP_REST_Response(ResponseEnvelope::success(['run' => $run], (string) ($run['summary'] ?? ''))->toArray());
    }

    private function refused(string $code, string $message): WP_REST_Response
    {
        return new WP_REST_Response(ResponseEnvelope::error($code, $message)->toArray(), 409);
    }
}
