<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use Corex\Http\Middleware\Request;
use Corex\Http\Middleware\Response;
use DateTimeImmutable;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Thin WordPress REST boundary for flow authoring and lifecycle operations.
 */
final readonly class FlowController
{
    public function __construct(
        private FlowControllerServices $services,
        private FlowExtensionCatalog $extensions,
        private FlowRestGateway $gateway,
        private FlowRestMapper $mapper,
    ) {
    }

    public function register(): void
    {
        $this->route('/flows', 'GET', 'index');
        $this->route('/flows', 'POST', 'create');
        $this->route('/flows/(?P<id>\d+)', 'GET', 'show');
        $this->route('/flows/(?P<id>\d+)', 'PATCH', 'update');
        $this->route('/flows/(?P<id>\d+)/preview', 'POST', 'preview');
        $this->route('/flows/(?P<id>\d+)/publish', 'POST', 'publish');
        $this->route('/flows/(?P<id>\d+)/unpublish', 'POST', 'unpublish');
        $this->route('/flows/(?P<id>\d+)/close', 'POST', 'close');
        $this->route('/flows/(?P<id>\d+)/test', 'POST', 'test');
        $this->route('/flows/extensions', 'GET', 'extensions');
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->read($request, function () use ($request): Response {
            $filters = $this->mapper->listFilters($request);
            $flows = array_map(
                fn (Flow $flow): array => $this->mapper->summary($flow, $this->services->flows->currentVersion($flow)),
                $this->services->flows->search($filters['query'], $filters['state']),
            );

            return Response::ok(['flows' => $flows]);
        });
    }

    public function create(WP_REST_Request $request): WP_REST_Response
    {
        $response = $this->gateway->mutate($request, $this->mapper->createShape(), function (Request $safe): Response {
            $created = $this->services->flows->create($this->mapper->newFlow(
                $safe->input,
                get_current_user_id(),
                new DateTimeImmutable('now'),
            ));

            return Response::ok([
                'flow' => $this->mapper->flow($created['flow']),
                'version' => $this->mapper->version($created['version']),
            ]);
        });
        if ($response->get_status() === 200) {
            $response->set_status(201);
        }

        return $response;
    }

    public function show(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->read($request, function () use ($request): Response {
            $flow = $this->services->flows->get(absint($request->get_param('id')));
            $versions = array_map($this->mapper->version(...), $this->services->flows->versions($flow->id));

            return Response::ok(['flow' => $this->mapper->flow($flow), 'versions' => $versions]);
        });
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->mutate($request, $this->mapper->updateShape(), function (Request $safe) use ($request): Response {
            $flowId = absint($request->get_param('id'));
            $version = $this->services->flows->saveDraft($this->mapper->draftUpdate(
                $flowId,
                $safe->input,
                get_current_user_id(),
                new DateTimeImmutable('now'),
            ));

            return Response::ok([
                'flow' => $this->mapper->flow($this->services->flows->get($flowId)),
                'version' => $this->mapper->version($version),
            ]);
        });
    }

    public function preview(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->read($request, function () use ($request): Response {
            $version = $this->services->flows->preview(absint($request->get_param('id')));

            return Response::ok(['version' => $this->mapper->version($version)]);
        });
    }

    public function publish(WP_REST_Request $request): WP_REST_Response
    {
        return $this->transition($request, $this->services->flows->publish(...));
    }

    public function unpublish(WP_REST_Request $request): WP_REST_Response
    {
        return $this->transition($request, $this->services->flows->unpublish(...));
    }

    public function close(WP_REST_Request $request): WP_REST_Response
    {
        return $this->transition($request, $this->services->flows->close(...));
    }

    public function test(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->mutate($request, $this->mapper->testShape(), function (Request $safe) use ($request): Response {
            $result = $this->services->tests->run(
                absint($request->get_param('id')),
                (int) ($safe->input['expected_version'] ?? 0),
                (array) ($safe->input['values'] ?? []),
            );

            return Response::ok($this->mapper->pipeline($result));
        });
    }

    public function extensions(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->read($request, fn (): Response => Response::ok($this->extensions->all()));
    }

    /** @param callable(FlowTransition):Flow $operation */
    private function transition(WP_REST_Request $request, callable $operation): WP_REST_Response
    {
        return $this->gateway->mutate($request, $this->mapper->transitionShape(), function (Request $safe) use ($request, $operation): Response {
            $command = $this->mapper->transition(
                absint($request->get_param('id')),
                $safe->input,
                get_current_user_id(),
                new DateTimeImmutable('now'),
            );

            return Response::ok(['flow' => $this->mapper->flow($operation($command))]);
        });
    }

    private function route(string $path, string $methods, string $callback): void
    {
        register_rest_route('corex/v1', $path, [
            'methods' => $methods,
            'callback' => [$this, $callback],
            'permission_callback' => '__return_true',
            'args' => ['id' => ['sanitize_callback' => 'absint']],
        ]);
    }
}
