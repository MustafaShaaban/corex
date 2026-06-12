<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_REST_Response;

/**
 * REST for the Corex → Data screen: `GET corex/v1/data/<source>` lists a source's rows
 * (paginated, `manage_options`); `DELETE corex/v1/data/<source>/<id>` removes one row
 * (`manage_options` + a valid REST nonce). Pure helpers (`canManage`, `verifiedNonce`,
 * `payload`) are unit-tested; the WP_REST_* callbacks are the thin boundary (spec 030).
 */
final class DataController
{
    public function __construct(private readonly DataRegistry $registry)
    {
    }

    public function register(): void
    {
        register_rest_route('corex/v1', '/data/(?P<source>[\w-]+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => [$this, 'canManage'],
        ]);

        register_rest_route('corex/v1', '/data/(?P<source>[\w-]+)/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'remove'],
            'permission_callback' => [$this, 'canDelete'],
        ]);
    }

    public function canManage(): bool
    {
        return current_user_can('manage_options');
    }

    public function canDelete(WP_REST_Request $request): bool
    {
        return $this->canManage() && $this->verifiedNonce((string) $request->get_header('X-WP-Nonce'));
    }

    /**
     * A state-changing REST request must carry a valid REST nonce (Principle VII).
     */
    public function verifiedNonce(string $nonce): bool
    {
        return wp_verify_nonce($nonce, 'wp_rest') !== false;
    }

    /**
     * The list payload for a source, or null if the source is unknown.
     *
     * @return array{columns:list<array{id:string,label:string}>,rows:list<array<string,scalar>>,total:int}|null
     */
    public function payload(string $sourceKey, int $page, int $perPage): ?array
    {
        $source = $this->registry->find($sourceKey);

        if ($source === null) {
            return null;
        }

        return [
            'columns' => $source->columns(),
            'rows'    => $source->rows($page, $perPage),
            'total'   => $source->total(),
        ];
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $payload = $this->payload(
            (string) $request->get_param('source'),
            (int) ($request->get_param('page') ?: 1),
            (int) ($request->get_param('per_page') ?: 20),
        );

        if ($payload === null) {
            return new WP_REST_Response(['error' => 'unknown_source'], 404);
        }

        return new WP_REST_Response($payload);
    }

    public function remove(WP_REST_Request $request): WP_REST_Response
    {
        $source = $this->registry->find((string) $request->get_param('source'));

        if ($source === null) {
            return new WP_REST_Response(['error' => 'unknown_source'], 404);
        }

        $deleted = $source->delete((int) $request->get_param('id'));

        return new WP_REST_Response(['deleted' => $deleted], $deleted ? 200 : 404);
    }
}
