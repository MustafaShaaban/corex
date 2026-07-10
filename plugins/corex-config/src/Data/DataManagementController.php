<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\Data;
defined('ABSPATH') || exit;

use Corex\Config\DataModels\DataExportRequest;
use Corex\Config\DataModels\DataExportRun;
use Corex\Config\DataModels\DataImportRequest;
use Corex\Config\DataModels\DataImportRun;
use Corex\Config\DataModels\MigrationPreview;
use Corex\Config\DataModels\MigrationRun;
use Corex\Access\CorexAbility;
use Corex\Http\Middleware\Request;
use Corex\Http\Middleware\Response;
use DomainException;
use WP_REST_Request;
use WP_REST_Response;

/** Thin REST controller for capability-derived Data and Data Models workflows. */
final readonly class DataManagementController
{
    public function __construct(private DataManagementServices $services, private DataRestGateway $gateway) {}

    public function register(): void
    {
        $this->route('/data/sources', 'GET', 'sources');
        $this->route('/data/(?P<source>[\w-]+)', 'GET', 'index');
        $this->route('/data/(?P<source>[\w-]+)/(?P<id>\d+)', 'GET', 'show');
        $this->route('/data/(?P<source>[\w-]+)/mutations/preview', 'POST', 'mutationPreview');
        $this->route('/data/(?P<source>[\w-]+)/mutations/apply', 'POST', 'mutationApply');
        $this->route('/data/(?P<source>[\w-]+)/imports', 'POST', 'createImport');
        $this->route('/data/(?P<source>[\w-]+)/imports/(?P<id>\d+)', 'PATCH', 'remapImport');
        $this->route('/data/(?P<source>[\w-]+)/imports/(?P<id>\d+)/dry-run', 'POST', 'remapImport');
        $this->route('/data/(?P<source>[\w-]+)/imports/(?P<id>\d+)/commit', 'POST', 'commitImport');
        $this->route('/data/(?P<source>[\w-]+)/imports/(?P<id>\d+)/report', 'GET', 'importReport');
        $this->route('/data/(?P<source>[\w-]+)/exports', 'POST', 'createExport');
        $this->route('/data/(?P<source>[\w-]+)/exports', 'GET', 'exports');
        $this->route('/data/(?P<source>[\w-]+)/exports/(?P<id>\d+)/download', 'GET', 'downloadExport');
        $this->route('/data/migrations', 'GET', 'migrations');
        $this->route('/data/migrations/preview', 'POST', 'migrationPreview');
        $this->route('/data/migrations/apply', 'POST', 'migrationApply');
        $this->route('/data/migrations/(?P<run>\d+)/rollback', 'POST', 'migrationRollback');
    }

    public function sources(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->read($request, fn (): Response => Response::ok([
            'sources' => $this->services->sources->catalog(get_current_user_id()),
        ]));
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->read($request, fn (): Response => Response::ok(
            $this->services->queries->query(
                get_current_user_id(), sanitize_key((string) $request->get_param('source')),
                DataQuery::from($request->get_params()),
            ),
        ));
    }

    public function show(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->read($request, function () use ($request): Response {
            $record = $this->services->queries->detail(
                get_current_user_id(), sanitize_key((string) $request->get_param('source')),
                absint($request->get_param('id')),
            );
            if ($record === null) {
                throw new DomainException(__('The data record is unavailable.', 'corex'));
            }

            return Response::ok(['record' => $record]);
        });
    }

    public function mutationPreview(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->mutate($request, $this->mutationShape(), fn (Request $safe): Response => Response::ok([
            'preview' => $this->services->mutations->preview(DataMutationRequest::from([
                ...$safe->input, 'actor_id' => get_current_user_id(),
                'source_key' => sanitize_key((string) $request->get_param('source')),
            ]))->toArray(),
        ]));
    }

    public function mutationApply(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->mutate($request, ['token' => 'sanitize_text_field'], fn (Request $safe): Response => Response::ok([
            'result' => $this->services->mutations->apply(new DataMutationApplyRequest(
                actorId: get_current_user_id(),
                token: (string) ($safe->input['token'] ?? ''),
                sourceKey: sanitize_key((string) $request->get_param('source')),
                actorLabel: wp_get_current_user()->display_name ?: 'CoreX user',
                now: new \DateTimeImmutable('now'),
            ))->toArray(),
        ]));
    }

    public function createImport(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->mutate($request, $this->importShape(), function (Request $safe) use ($request): Response {
            $input = $safe->input;
            if (is_array($input['file'] ?? null)) {
                $input = [...$input, ...$input['file']];
                unset($input['file']);
            }

            return Response::ok(['import' => $this->services->imports->dryRun(DataImportRequest::from([
                ...$input, 'actor_id' => get_current_user_id(),
                'source_key' => sanitize_key((string) $request->get_param('source')),
            ]))->toArray()]);
        });
    }

    public function remapImport(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->mutate($request, [
            'mapping' => $this->mapping(...), 'unknown_policy' => 'sanitize_key',
        ], function (Request $safe) use ($request): Response {
            $this->assertImportRoute($request);

            return Response::ok(['import' => $this->services->imports->remap(
                get_current_user_id(), absint($request->get_param('id')),
                (array) ($safe->input['mapping'] ?? []), (string) ($safe->input['unknown_policy'] ?? 'reject'),
            )->toArray()]);
        });
    }

    public function commitImport(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->mutate($request, ['input_hash' => 'sanitize_text_field'], function (Request $safe) use ($request): Response {
            $this->assertImportRoute($request);

            return Response::ok(['import' => $this->services->imports->commit(
                get_current_user_id(), absint($request->get_param('id')),
                (string) ($safe->input['input_hash'] ?? ''),
            )->toArray()]);
        });
    }

    public function importReport(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->read($request, function () use ($request): Response {
            $run = $this->services->importRuns->find(absint($request->get_param('id')));
            if ($run === null || $run->actorId !== get_current_user_id()
                || $run->sourceKey !== sanitize_key((string) $request->get_param('source'))) {
                throw new DomainException(__('The import report is unavailable.', 'corex'));
            }

            return Response::ok([
                'filename' => sprintf('corex-import-%d-rejected.csv', $run->id),
                'content' => $this->services->importReports->write($run),
            ]);
        });
    }

    public function createExport(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->mutate($request, $this->exportShape(), fn (Request $safe): Response => Response::ok([
            'export' => $this->services->exports->request(DataExportRequest::from([
                ...$safe->input, 'actor_id' => get_current_user_id(),
                'source_key' => sanitize_key((string) $request->get_param('source')),
            ]))->toArray(),
        ]));
    }

    public function exports(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->read($request, function () use ($request): Response {
            $sourceKey = sanitize_key((string) $request->get_param('source'));
            $runs = array_filter(
                $this->services->exports->history(get_current_user_id()),
                static fn (DataExportRun $run): bool => $run->sourceKey === $sourceKey,
            );

            return Response::ok(['exports' => array_map(
                static fn (DataExportRun $run): array => $run->toArray(),
                array_values($runs),
            )]);
        });
    }

    public function downloadExport(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->read($request, function () use ($request): Response {
            $artifact = $this->services->exports->download(
                get_current_user_id(),
                absint($request->get_param('id')),
                false,
                sanitize_key((string) $request->get_param('source')),
            );

            return Response::ok(['artifact' => [
                'filename' => $artifact['filename'],
                'mime' => $artifact['mime'],
                'encoding' => 'base64',
                'content' => base64_encode($artifact['content']),
            ]]);
        });
    }

    public function migrations(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->read($request, function () use ($request): Response {
            $sourceKey = sanitize_key((string) $request->get_param('source'));
            $runs = array_filter(
                $this->services->migrations->history(get_current_user_id()),
                static fn (MigrationRun $run): bool => $run->sourceKey === $sourceKey,
            );

            return Response::ok([
                'migrations' => $this->services->migrations->catalog(get_current_user_id(), $sourceKey),
                'history' => array_map(
                    static fn (MigrationRun $run): array => $run->toArray(),
                    array_values($runs),
                ),
            ]);
        });
    }

    public function migrationPreview(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->mutate($request, $this->migrationShape(), function (Request $safe): Response {
            $preview = ($safe->input['action'] ?? 'apply') === 'rollback'
                ? $this->services->migrations->previewRollback(get_current_user_id(), (int) ($safe->input['run_id'] ?? 0))
                : $this->services->migrations->previewApply(
                    get_current_user_id(), (string) ($safe->input['source'] ?? ''),
                    (string) ($safe->input['definition'] ?? ''),
                );

            return Response::ok(['preview' => $this->migrationPreviewArray($preview)]);
        });
    }

    public function migrationApply(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->mutate($request, ['token' => 'sanitize_text_field'], fn (Request $safe): Response => Response::ok([
            'run' => $this->services->migrations->queue(
                get_current_user_id(), (string) ($safe->input['token'] ?? ''),
                MigrationRun::ACTION_APPLY,
            )->toArray(),
        ]));
    }

    public function migrationRollback(WP_REST_Request $request): WP_REST_Response
    {
        return $this->gateway->mutate($request, ['token' => 'sanitize_text_field'], function (Request $safe) use ($request): Response {
            $token = (string) ($safe->input['token'] ?? '');
            $rollback = $token === ''
                ? $this->migrationPreviewArray($this->services->migrations->previewRollback(
                    get_current_user_id(), absint($request->get_param('run')),
                ))
                : $this->services->migrations->queue(
                    get_current_user_id(),
                    $token,
                    MigrationRun::ACTION_ROLLBACK,
                    absint($request->get_param('run')),
                )->toArray();

            return Response::ok([$token === '' ? 'preview' : 'run' => $rollback]);
        });
    }

    /** @return array<string,callable|string> */
    private function mutationShape(): array
    {
        return ['operation' => 'sanitize_key', 'record_ids' => $this->integerList(...), 'values' => $this->values(...)];
    }

    /** @return array<string,callable|string> */
    private function importShape(): array
    {
        return [
            'header' => $this->stringList(...), 'rows' => $this->rows(...), 'mapping' => $this->mapping(...),
            'unknown_policy' => 'sanitize_key', 'file_name' => 'sanitize_file_name',
            'file' => fn (mixed $value): array => $this->services->csv->parse(is_array($value) ? $value : []),
        ];
    }

    /** @return array<string,callable|string> */
    private function exportShape(): array
    {
        return [
            'scope' => 'sanitize_key', 'selected_ids' => $this->integerList(...), 'query' => $this->query(...),
            'columns' => $this->keyList(...), 'format' => 'sanitize_key',
            'personal_data_acknowledged' => 'rest_sanitize_boolean',
        ];
    }

    /** @return array<string,callable|string> */
    private function migrationShape(): array
    {
        return ['source' => 'sanitize_key', 'definition' => 'sanitize_key', 'action' => 'sanitize_key', 'run_id' => 'absint'];
    }

    /** @return list<int> */
    private function integerList(mixed $input): array
    {
        return array_values(array_filter(array_map('absint', is_array($input) ? $input : [])));
    }

    /** @return list<string> */
    private function stringList(mixed $input): array
    {
        return array_values(array_map('sanitize_text_field', is_array($input) ? $input : []));
    }

    /** @return list<string> */
    private function keyList(mixed $input): array
    {
        return array_values(array_filter(array_map('sanitize_key', is_array($input) ? $input : [])));
    }

    /** @return list<list<string>> */
    private function rows(mixed $input): array
    {
        return array_values(array_map(
            fn (mixed $row): array => $this->stringList($row),
            is_array($input) ? $input : [],
        ));
    }

    /** @return array<string,string> */
    private function mapping(mixed $input): array
    {
        $mapping = [];
        foreach (is_array($input) ? $input : [] as $column => $field) {
            $mapping[sanitize_text_field((string) $column)] = sanitize_key((string) $field);
        }

        return $mapping;
    }

    /** @return array<string,mixed> */
    private function values(mixed $input): array
    {
        $values = [];
        foreach (is_array($input) ? $input : [] as $field => $fieldValue) {
            $values[sanitize_key((string) $field)] = is_scalar($fieldValue) || $fieldValue === null
                ? $fieldValue
                : '';
        }

        return $values;
    }

    /** @return array<string,mixed> */
    private function query(mixed $input): array
    {
        $query = is_array($input) ? $input : [];

        return [
            'search' => sanitize_text_field((string) ($query['search'] ?? '')),
            'filters' => $this->mapping($query['filters'] ?? []),
            'sort' => sanitize_key((string) ($query['sort'] ?? '')),
            'dir' => sanitize_key((string) ($query['dir'] ?? '')),
        ];
    }

    /** @return array<string,mixed> */
    private function migrationPreviewArray(MigrationPreview $preview): array
    {
        return [...$preview->toArray(), 'production_warning' => $preview->productionWarning];
    }

    private function assertImportRoute(WP_REST_Request $request): void
    {
        $run = $this->services->importRuns->find(absint($request->get_param('id')));
        if ($run === null || $run->actorId !== get_current_user_id()
            || $run->sourceKey !== sanitize_key((string) $request->get_param('source'))) {
            throw new DomainException(__('The data import is unavailable.', 'corex'));
        }
    }

    private function route(string $path, string $method, string $callback): void
    {
        register_rest_route('corex/v1', $path, [
            'methods' => $method, 'callback' => [$this, $callback],
            'permission_callback' => [$this, 'canManage'],
            'args' => [
                'source' => ['sanitize_callback' => 'sanitize_key'],
                'id' => ['sanitize_callback' => 'absint'],
                'run' => ['sanitize_callback' => 'absint'],
            ],
        ]);
    }

    public function canManage(): bool
    {
        return current_user_can(CorexAbility::MANAGE_DATA)
            || current_user_can(CorexAbility::MANAGE_DATA_MODELS)
            || current_user_can('manage_options');
    }
}
