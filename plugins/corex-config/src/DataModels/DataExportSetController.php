<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\DataModels;
defined('ABSPATH') || exit;

use Corex\Access\CorexAbility;
use Corex\Config\Data\DataRestGateway;
use Corex\Config\Export\ExportWriters;
use Corex\Http\Middleware\Request;
use Corex\Http\Middleware\Response;
use Corex\Http\RouteParam;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Exports of several models, and the history of every Data export (spec 109, slice 2).
 *
 * The routes are not under `/data/`, where `/data/{source}` matches any word: a fixed path there
 * has to be registered before that pattern or it is taken for a source. One model's export keeps
 * its routes under its source.
 */
final class DataExportSetController
{
    public function __construct(
        private readonly DataExportService $exports,
        private readonly DataExportHistory $history,
        private readonly ExportWriters $formats,
        private readonly DataRestGateway $gateway,
    ) {
    }

    public function register(): void
    {
        $this->route('/data-exports', 'GET', 'index');
        $this->route('/data-exports', 'POST', 'create');
        $this->route('/data-exports/preview', 'POST', 'preview');
        $this->route('/data-exports/(?P<id>\d+)/advance', 'POST', 'advance');
        $this->route('/data-exports/(?P<id>\d+)/download', 'GET', 'download');
        $this->route('/data-exports/(?P<id>\d+)', 'DELETE', 'delete');
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->read($request, fn (): Response => Response::ok([
            'exports' => $this->history->entries(get_current_user_id(), $this->managesAll()),
        ]));
    }

    public function preview(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->mutate($request, ['sources' => $this->keyList(...)], fn (Request $safe): Response => Response::ok([
            'counts' => $this->exports->previewSeveral(get_current_user_id(), (array) ($safe->input['sources'] ?? [])),
            // The dialog offers a format only where it can be written, and says the most a PDF holds.
            'formats' => $this->formats->describe(),
        ]));
    }

    public function create(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->mutate($request, $this->shape(), fn (Request $safe): Response => Response::ok([
            'export' => $this->exports->requestSeveral(DataExportSetRequest::from([
                ...$safe->input, 'actor_id' => get_current_user_id(),
            ]))->toArray(),
        ]));
    }

    public function advance(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->mutate($request, [], fn (): Response => Response::ok([
            'progress' => $this->exports->advance(get_current_user_id(), RouteParam::int($request), $this->managesAll(), ''),
        ]));
    }

    public function download(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->read($request, function () use ($request): Response {
            $run = $this->history->downloadable(get_current_user_id(), $this->managesAll(), RouteParam::int($request));
            $artifact = $this->exports->download(get_current_user_id(), $run->id, $this->managesAll());

            return Response::ok(['artifact' => [
                // Named for the site, what it holds and the day it was made.
                'filename' => sanitize_file_name(sanitize_title(get_bloginfo('name')) . '-' . $artifact['filename']),
                'mime' => $artifact['mime'],
                'encoding' => 'base64',
                'content' => base64_encode($artifact['content']),
            ]]);
        });
    }

    public function delete(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->mutate($request, [], function () use ($request): Response {
            $this->history->delete(get_current_user_id(), $this->managesAll(), RouteParam::int($request));

            return Response::ok(['deleted' => true]);
        });
    }

    public function canManage(): bool
    {
        return $this->gateway->canManage();
    }

    /** Whether this person sees and acts on everybody's exports, or their own. */
    private function managesAll(): bool
    {
        return current_user_can(CorexAbility::MANAGE_ADMIN) || current_user_can('manage_options');
    }

    /** @return array<string,callable|string> */
    private function shape(): array
    {
        return [
            'parts' => $this->parts(...), 'format' => 'sanitize_key', 'separator' => 'sanitize_key',
            'personal_data_acknowledged' => 'rest_sanitize_boolean',
        ];
    }

    /**
     * @return list<array{source:string,columns?:list<string>}> Each model asked for; `columns`
     *         only where the request named some.
     */
    private function parts(mixed $input): array
    {
        $parts = [];
        foreach (is_array($input) ? $input : [] as $part) {
            if (! is_array($part)) {
                continue;
            }
            $clean = ['source' => sanitize_key((string) ($part['source'] ?? ''))];
            if (is_array($part['columns'] ?? null)) {
                $clean['columns'] = $this->keyList($part['columns']);
            }
            $parts[] = $clean;
        }

        return $parts;
    }

    /** @return list<string> */
    private function keyList(mixed $input): array
    {
        return array_values(array_filter(array_map('sanitize_key', is_array($input) ? $input : [])));
    }

    private function route(string $path, string $method, string $callback): void
    {
        register_rest_route('corex/v1', $path, [
            'methods' => $method, 'callback' => [$this, $callback],
            'permission_callback' => [$this, 'canManage'],
            'args' => ['id' => ['sanitize_callback' => 'absint']],
        ]);
    }
}
