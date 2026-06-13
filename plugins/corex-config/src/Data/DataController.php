<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

use Corex\Http\ResponseEnvelope;
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
            'methods'             => 'GET',
            'callback'            => [$this, 'show'],
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

    /**
     * The query payload for a source — search/filter/sort/paginate when the source is
     * queryable (spec 045), else plain pagination (unchanged for non-queryable sources).
     *
     * @return array{columns:list<array{id:string,label:string}>,rows:list<array<string,scalar>>,total:int}|null
     */
    public function queryPayload(string $sourceKey, DataQuery $query): ?array
    {
        $source = $this->registry->find($sourceKey);

        if ($source === null) {
            return null;
        }

        if ($source instanceof QueryableDataSource) {
            return [
                'columns' => $source->columns(),
                'rows'    => $source->query($query),
                'total'   => $source->count($query),
            ];
        }

        return [
            'columns' => $source->columns(),
            'rows'    => $source->rows($query->page, $query->perPage),
            'total'   => $source->total(),
        ];
    }

    /**
     * Build the query from the (sanitised) request params.
     */
    public function queryFrom(WP_REST_Request $request): DataQuery
    {
        return DataQuery::from([
            'search'   => sanitize_text_field((string) $request->get_param('search')),
            'form'     => sanitize_key((string) $request->get_param('form')),
            'sort'     => sanitize_key((string) $request->get_param('sort')),
            'dir'      => sanitize_key((string) $request->get_param('dir')),
            'page'     => (int) ($request->get_param('page') ?: 1),
            'per_page' => (int) ($request->get_param('per_page') ?: 20),
        ]);
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $payload = $this->queryPayload((string) $request->get_param('source'), $this->queryFrom($request));

        if ($payload === null) {
            return new WP_REST_Response(
                ResponseEnvelope::error('unknown_source', __('Unknown data source.', 'corex'))->toArray(),
                404,
            );
        }

        return new WP_REST_Response(ResponseEnvelope::success($payload)->toArray());
    }

    public function show(WP_REST_Request $request): WP_REST_Response
    {
        $source = $this->registry->find((string) $request->get_param('source'));

        $record = $source instanceof QueryableDataSource
            ? $source->record((int) $request->get_param('id'))
            : null;

        if ($record === null) {
            return new WP_REST_Response(
                ResponseEnvelope::error('not_found', __('That record was not found.', 'corex'))->toArray(),
                404,
            );
        }

        return new WP_REST_Response(ResponseEnvelope::success($record)->toArray());
    }

    public function remove(WP_REST_Request $request): WP_REST_Response
    {
        $source = $this->registry->find((string) $request->get_param('source'));

        if ($source === null) {
            return new WP_REST_Response(
                ResponseEnvelope::error('unknown_source', __('Unknown data source.', 'corex'))->toArray(),
                404,
            );
        }

        $deleted = $source->delete((int) $request->get_param('id'));

        if (! $deleted) {
            return new WP_REST_Response(
                ResponseEnvelope::error('not_found', __('That item no longer exists.', 'corex'))->toArray(),
                404,
            );
        }

        return new WP_REST_Response(ResponseEnvelope::success(['deleted' => true])->toArray());
    }
}
