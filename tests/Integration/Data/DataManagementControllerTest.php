<?php

/**
 * Data and Data Models REST contracts (spec 068 T125 / FR-059–FR-070).
 *
 * @package Corex\Tests\Integration\Data
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Data\CapabilityAwareDataSource;
use Corex\Config\Data\DataManagementController;
use Corex\Config\Data\DataQuery;
use Corex\Config\Data\DataRegistry;
use Corex\Config\Data\DataSource;
use Corex\Config\Data\FieldAwareDataSource;
use Corex\Config\Data\QueryableDataSource;
use Corex\Config\Data\WritableDataSource;
use Corex\Config\DataModels\DataImportRequest;
use Corex\Config\DataModels\MigrationAwareDataSource;
use Corex\Config\DataModels\MigrationDefinition;
use Corex\Config\DataModels\MigrationProvider;
use Corex\Config\DataModels\WpDataExportStore;
use Corex\Config\DataModels\WpDataImportStore;
use Corex\Config\DataModels\WpMigrationRunStore;
use Corex\Data\DataField;
use Corex\Data\DataSourceCapabilities;
use Corex\Data\DataWriteAdapter;
use Corex\Operations\OperationResult;

it('provides the consolidated Data management REST boundary', function () {
    expect(class_exists(Corex\Config\Data\DataManagementController::class))->toBeTrue()
        ->and(class_exists(Corex\Config\Data\DataManagementServices::class))->toBeTrue()
        ->and(class_exists(Corex\Config\Data\DataRestGateway::class))->toBeTrue();
});

function dataManagementRequest(string $method, string $route, array $payload = []): WP_REST_Request
{
    $request = new WP_REST_Request($method, $route);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    $method === 'GET' ? $request->set_query_params($payload) : $request->set_body_params($payload);

    return $request;
}

beforeEach(function () {
    $this->dataRunBaseline = [];
    $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($admins[0] ?? 0));
    $container = Boot::app()->container();
    foreach ([WpDataImportStore::class, WpDataExportStore::class, WpMigrationRunStore::class] as $store) {
        $container->make($store)->registerPostType();
    }
    foreach ([WpDataImportStore::POST_TYPE, WpDataExportStore::POST_TYPE, WpMigrationRunStore::POST_TYPE] as $type) {
        $this->dataRunBaseline[$type] = get_posts([
            'post_type' => $type, 'post_status' => 'any', 'posts_per_page' => 500, 'fields' => 'ids',
        ]);
    }

    $adapter = new class implements DataWriteAdapter {
        public array $records = [
            1 => ['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com', 'status' => 'active'],
            2 => ['id' => 2, 'name' => 'Grace', 'email' => 'grace@example.com', 'status' => 'inactive'],
        ];
        public function create(array $values): OperationResult { $id = count($this->records) + 1; $this->records[$id] = ['id' => $id, ...$values]; return dataOperation([$id]); }
        public function update(array $recordIds, array $values): OperationResult { foreach ($recordIds as $id) $this->records[(int) $id] = [...$this->records[(int) $id], ...$values]; return dataOperation($recordIds); }
        public function delete(array $recordIds): OperationResult { foreach ($recordIds as $id) unset($this->records[(int) $id]); return dataOperation($recordIds); }
    };
    $migration = new class implements MigrationProvider {
        public array $calls = [];
        public function definitions(): array { return [new MigrationDefinition('contacts-v2', '2.0.0', 'Add state index.', ['Create index'], true, true)]; }
        public function snapshot(MigrationDefinition $definition): string { $this->calls[] = 'snapshot'; return 'snapshot-rest'; }
        public function execute(MigrationDefinition $definition, string $snapshotId, bool $rollback): OperationResult { $this->calls[] = $rollback ? 'rollback' : 'apply'; return dataOperation([]); }
    };
    $source = new class($adapter, $migration) implements DataSource, QueryableDataSource, CapabilityAwareDataSource, FieldAwareDataSource, WritableDataSource, MigrationAwareDataSource {
        public function __construct(private DataWriteAdapter $adapter, private MigrationProvider $migration) {}
        public function key(): string { return 'rest-contacts'; }
        public function label(): string { return 'REST Contacts'; }
        public function columns(): array { return [['id' => 'name', 'label' => 'Name'], ['id' => 'email', 'label' => 'Email'], ['id' => 'status', 'label' => 'Status']]; }
        public function rows(int $page, int $perPage): array { return array_slice(array_values($this->adapter->records), ($page - 1) * $perPage, $perPage); }
        public function total(): int { return count($this->adapter->records); }
        public function delete(int $id): bool { return false; }
        public function query(DataQuery $query): array { return $this->rows($query->page, $query->perPage); }
        public function count(DataQuery $query): int { return $this->total(); }
        public function record(int $id): ?array { return $this->adapter->records[$id] ?? null; }
        public function writeAdapter(): DataWriteAdapter { return $this->adapter; }
        public function migrationProvider(): MigrationProvider { return $this->migration; }
        public function fields(): array { return [
            new DataField('name', 'Name', DataField::TYPE_TEXT, true, false, false, ['contains'], true, DataField::PERSONAL_IDENTITY, [], ['full_name']),
            new DataField('email', 'Email', DataField::TYPE_EMAIL, true, false, false, ['equals'], true, DataField::PERSONAL_CONTACT, [], []),
            new DataField('status', 'Status', DataField::TYPE_SELECT, false, true, false, ['equals'], true, DataField::PERSONAL_NONE, ['options' => ['active', 'inactive']], []),
        ]; }
        public function capabilities(): DataSourceCapabilities { return new DataSourceCapabilities(
            sourceKey: 'rest-contacts', read: true, query: true, schema: true, detail: true,
            create: true, update: true, delete: true, bulkUpdate: true, bulkDelete: true,
            importDryRun: true, importCommit: true, exportCsv: true, exportXlsx: true,
            migrations: true, rollback: true, maxPageSize: 100,
            permissionMap: array_fill_keys(['read','query','schema','detail','create','update','delete','bulk_update','bulk_delete','import_dry_run','import_commit','export_csv','export_xlsx','migrations','rollback'], 'corex_manage_data'),
        ); }
    };
    $container->make(DataRegistry::class)->register($source);
    $this->dataAdapter = $adapter;
    $this->migrationProvider = $migration;
    $this->controller = $container->make(DataManagementController::class);
});

afterEach(function () {
    foreach ($this->dataRunBaseline ?? [] as $type => $baseline) {
        $ids = get_posts(['post_type' => $type, 'post_status' => 'any', 'posts_per_page' => 500, 'fields' => 'ids']);
        foreach (array_diff($ids, $baseline) as $id) wp_delete_post((int) $id, true);
    }
});

function dataOperation(array $ids): OperationResult
{
    $now = new DateTimeImmutable('2026-07-04T14:00:00+00:00');
    return new OperationResult('123e4567-e89b-42d3-a456-426614174000', OperationResult::STATE_COMPLETED, 'Completed.', [], $ids, $now, $now);
}

it('registers every source mutation import export and migration route', function () {
    add_action('rest_api_init', [$this->controller, 'register']);
    do_action('rest_api_init', rest_get_server());

    expect(rest_get_server()->get_routes())->toHaveKeys([
        '/corex/v1/data/sources', '/corex/v1/data/(?P<source>[\w-]+)', '/corex/v1/data/(?P<source>[\w-]+)/(?P<id>\d+)',
        '/corex/v1/data/(?P<source>[\w-]+)/mutations/preview', '/corex/v1/data/(?P<source>[\w-]+)/mutations/apply',
        '/corex/v1/data/(?P<source>[\w-]+)/imports', '/corex/v1/data/(?P<source>[\w-]+)/imports/(?P<id>\d+)',
        '/corex/v1/data/(?P<source>[\w-]+)/imports/(?P<id>\d+)/dry-run', '/corex/v1/data/(?P<source>[\w-]+)/imports/(?P<id>\d+)/commit',
        '/corex/v1/data/(?P<source>[\w-]+)/imports/(?P<id>\d+)/report', '/corex/v1/data/(?P<source>[\w-]+)/exports',
        '/corex/v1/data/(?P<source>[\w-]+)/exports/(?P<id>\d+)/download', '/corex/v1/data/migrations',
        '/corex/v1/data/migrations/preview', '/corex/v1/data/migrations/apply', '/corex/v1/data/migrations/(?P<run>\d+)/rollback',
    ]);
});

it('queries catalogs details and applies only a nonce-backed mutation preview', function () {
    $sources = $this->controller->sources(dataManagementRequest('GET', '/corex/v1/data/sources'));
    $index = dataManagementRequest('GET', '/corex/v1/data/rest-contacts'); $index->set_param('source', 'rest-contacts');
    $detail = dataManagementRequest('GET', '/corex/v1/data/rest-contacts/1'); $detail->set_param('source', 'rest-contacts'); $detail->set_param('id', 1);
    $previewRequest = dataManagementRequest('POST', '/corex/v1/data/rest-contacts/mutations/preview', [
        'operation' => 'update', 'record_ids' => [1], 'values' => ['status' => 'inactive'],
    ]); $previewRequest->set_param('source', 'rest-contacts');
    $preview = $this->controller->mutationPreview($previewRequest);
    $token = $preview->get_data()['data']['preview']['token'];
    $apply = dataManagementRequest('POST', '/corex/v1/data/rest-contacts/mutations/apply', ['token' => $token]);
    $apply->set_param('source', 'rest-contacts');
    $applied = $this->controller->mutationApply($apply);

    $wrongRoutePreviewRequest = dataManagementRequest('POST', '/corex/v1/data/rest-contacts/mutations/preview', [
        'operation' => 'update', 'record_ids' => [2], 'values' => ['status' => 'active'],
    ]);
    $wrongRoutePreviewRequest->set_param('source', 'rest-contacts');
    $wrongRoutePreview = $this->controller->mutationPreview($wrongRoutePreviewRequest);
    $wrongRouteApply = dataManagementRequest('POST', '/corex/v1/data/other-source/mutations/apply', [
        'token' => $wrongRoutePreview->get_data()['data']['preview']['token'],
    ]);
    $wrongRouteApply->set_param('source', 'other-source');
    $wrongRouteDenied = $this->controller->mutationApply($wrongRouteApply);

    $bad = dataManagementRequest('POST', '/corex/v1/data/rest-contacts/mutations/preview', ['operation' => 'delete', 'record_ids' => [2]]);
    $bad->set_param('source', 'rest-contacts'); $bad->set_header('X-WP-Nonce', 'invalid');
    $denied = $this->controller->mutationPreview($bad);

    expect($sources->get_status())->toBe(200)
        ->and($sources->get_data()['data']['sources'])->not->toBeEmpty()
        ->and($this->controller->index($index)->get_data()['data']['total'])->toBe(2)
        ->and($this->controller->show($detail)->get_data()['data']['record']['name'])->toBe('Ada')
        ->and($applied->get_data()['data']['result']['state'])->toBe('completed')
        ->and($this->dataAdapter->records[1]['status'])->toBe('inactive')
        ->and($wrongRouteDenied->get_status())->toBe(422)
        ->and($this->dataAdapter->records[2]['status'])->toBe('inactive')
        ->and($denied->get_status())->toBe(403)
        ->and($this->dataAdapter->records)->toHaveKey(2);
});

it('creates and remaps an import report and previews migrations without applying them', function () {
    $create = dataManagementRequest('POST', '/corex/v1/data/rest-contacts/imports', [
        'header' => ['full_name', 'email', 'extra'], 'rows' => [['Ada', 'ada@example.com', '=cmd']],
        'mapping' => [], 'unknown_policy' => 'reject', 'file_name' => 'contacts.csv',
    ]); $create->set_param('source', 'rest-contacts');
    $created = $this->controller->createImport($create);
    $run = $created->get_data()['data']['import'];
    $remap = dataManagementRequest('PATCH', '/corex/v1/data/rest-contacts/imports/' . $run['id'], [
        'mapping' => ['extra' => ''], 'unknown_policy' => 'ignore',
    ]); $remap->set_param('source', 'rest-contacts'); $remap->set_param('id', $run['id']);
    $remapped = $this->controller->remapImport($remap)->get_data()['data']['import'];
    $report = dataManagementRequest('GET', '/corex/v1/data/rest-contacts/imports/' . $run['id'] . '/report');
    $report->set_param('source', 'rest-contacts'); $report->set_param('id', $run['id']);

    $migration = dataManagementRequest('POST', '/corex/v1/data/migrations/preview', [
        'source' => 'rest-contacts', 'definition' => 'contacts-v2', 'action' => 'apply',
    ]);
    $migrationPreview = $this->controller->migrationPreview($migration);

    expect($run['state'])->toBe('invalid')
        ->and($remapped['state'])->toBe('valid')
        ->and($this->controller->importReport($report)->get_data()['data']['content'])->toContain('Unknown column')
        ->and($migrationPreview->get_data()['data']['preview']['definition']['key'])->toBe('contacts-v2')
        ->and($migrationPreview->get_data()['data']['preview']['production_warning'])->toBeTrue()
        ->and($this->migrationProvider->calls)->toBe([]);
});

it('accepts a bounded CSV upload and parses it server-side before the dry run', function () {
    $path = tempnam(sys_get_temp_dir(), 'corex-import-rest-');
    file_put_contents($path, "full_name,email,status\nAda,ada@example.com,active\n");
    $request = dataManagementRequest('POST', '/corex/v1/data/rest-contacts/imports', [
        'mapping' => [], 'unknown_policy' => 'reject',
    ]);
    $request->set_param('source', 'rest-contacts');
    $request->set_file_params(['file' => [
        'name' => 'contacts.csv', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK,
        'size' => filesize($path), 'type' => 'text/csv',
    ]]);

    $response = $this->controller->createImport($request);
    unlink($path);

    expect($response->get_status())->toBe(200)
        ->and($response->get_data()['data']['import']['state'])->toBe('valid')
        ->and($response->get_data()['data']['import']['accepted_rows'][0]['name'])->toBe('Ada');
});
