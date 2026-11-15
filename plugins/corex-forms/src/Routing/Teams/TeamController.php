<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Routing\Teams;

defined('ABSPATH') || exit;

use Corex\Forms\Flow\FlowRestGateway;
use Corex\Http\Middleware\Request;
use Corex\Http\Middleware\Response;
use WP_REST_Request;
use WP_REST_Response;

/**
 * The Teams view's REST boundary (spec 122, FR-012): list, create, change and remove teams. Asked
 * the same as every flow route, through the flow gateway.
 */
final readonly class TeamController
{
    public function __construct(
        private TeamService $teams,
        private FlowRestGateway $gateway,
    ) {
    }

    public function register(): void
    {
        $this->route('/teams', 'GET', 'index');
        $this->route('/teams', 'POST', 'create');
        $this->route('/teams/(?P<key>[a-z0-9_-]+)', 'PATCH', 'update');
        $this->route('/teams/(?P<key>[a-z0-9_-]+)', 'DELETE', 'delete');
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->read($request, fn (): Response => Response::ok(['teams' => $this->listed()]));
    }

    public function create(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->mutate($request, $this->shape(), function (Request $safe): Response {
            $team = $this->teams->create((string) ($safe->input['name'] ?? ''), Team::memberIds($safe->input['members'] ?? []));

            return Response::ok(['team' => $team->toArray(), 'teams' => $this->listed()]);
        });
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $key = sanitize_key((string) $request->get_param('key'));

        return $this->gateway->mutate($request, $this->shape(), function (Request $safe) use ($key): Response {
            $team = $this->teams->update($key, (string) ($safe->input['name'] ?? ''), Team::memberIds($safe->input['members'] ?? []));

            return Response::ok(['team' => $team->toArray(), 'teams' => $this->listed()]);
        });
    }

    public function delete(WP_REST_Request $request): WP_REST_Response
    {
        $key = sanitize_key((string) $request->get_param('key'));

        return $this->gateway->mutate($request, [], function () use ($key): Response {
            $this->teams->delete($key);

            return Response::ok(['teams' => $this->listed()]);
        });
    }

    /** @return list<array{key:string,name:string,members:list<int>}> */
    private function listed(): array
    {
        return array_map(static fn (Team $team): array => $team->toArray(), $this->teams->all());
    }

    /** @return array<string,callable|string> */
    private function shape(): array
    {
        return [
            'name' => 'sanitize_text_field',
            'members' => static fn (mixed $members): array => Team::memberIds($members),
        ];
    }

    private function route(string $path, string $methods, string $callback): void
    {
        register_rest_route('corex/v1', $path, [
            'methods' => $methods,
            'callback' => [$this, $callback],
            'permission_callback' => [$this->gateway, 'permits'],
        ]);
    }
}
