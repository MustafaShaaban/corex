<?php

/**
 * Integration test: several models exported in one go, and the history of every Data export,
 * through the routes the Export tab uses and the job the runner runs (spec 109, slice 2).
 *
 * The owner, on a live site: "the import/export/migrate model is using one by one, i can't do a
 * bulk or multiselect". Two models are registered here, exported together, and the file that
 * comes back is opened.
 *
 * @package Corex\Tests\Integration\DataModels
 */

declare(strict_types=1);

use Corex\Access\CorexAbility;
use Corex\Boot;
use Corex\Config\Activity\ActivityTable;
use Corex\Config\Data\CapabilityAwareDataSource;
use Corex\Config\Data\DataManagementController;
use Corex\Config\Data\DataQuery;
use Corex\Config\Data\DataRegistry;
use Corex\Config\Data\DataSource;
use Corex\Config\Data\ExportableDataSource;
use Corex\Config\DataModels\DataExportSetController;
use Corex\Config\DataModels\WpDataExportStore;
use Corex\Data\DataField;
use Corex\Data\DataSourceCapabilities;
use Corex\Database\Schema\Migrator;
use Corex\Tests\Support\CreatedPosts;

const DATA_SET_TEST_LOGIN = 'corex-data-set-test';

/**
 * A model that exists only in this file: a key, a name, its records, and which of its two
 * columns holds a person's name.
 *
 * @param list<array{id:int,code:string,person:string}> $records
 */
function exportSetModel(string $key, string $label, array $records): DataSource
{
    return new class($key, $label, $records) implements DataSource, ExportableDataSource, CapabilityAwareDataSource {
        public function __construct(private string $key, private string $label, private array $records) {}
        public function key(): string { return $this->key; }
        public function label(): string { return $this->label; }
        public function columns(): array { return [['id' => 'code', 'label' => 'Code'], ['id' => 'person', 'label' => 'Person']]; }
        public function rows(int $page, int $perPage): array { return array_slice($this->records, ($page - 1) * $perPage, $perPage); }
        public function total(): int { return count($this->records); }
        public function delete(int $id): bool { return false; }
        public function query(DataQuery $query): array { return $this->rows($query->page, $query->perPage); }
        public function count(DataQuery $query): int { return $this->total(); }
        public function record(int $id): ?array { return null; }
        public function exportRows(DataQuery $query): array { return $this->query($query); }
        public function exportRowsOf(array $ids): array { return []; }
        public function fields(): array
        {
            return [
                new DataField('code', 'Code', DataField::TYPE_TEXT, false, true, true, [], true, DataField::PERSONAL_NONE, [], []),
                new DataField('person', 'Person', DataField::TYPE_TEXT, false, true, true, [], true, DataField::PERSONAL_IDENTITY, [], []),
            ];
        }
        public function capabilities(): DataSourceCapabilities
        {
            return new DataSourceCapabilities(
                sourceKey: $this->key, read: true, query: true, schema: true, detail: true,
                create: false, update: false, delete: false, bulkUpdate: false, bulkDelete: false,
                importDryRun: false, importCommit: false, exportCsv: true, exportXlsx: true,
                migrations: false, rollback: false, maxPageSize: 100,
                permissionMap: array_fill_keys(['read', 'query', 'schema', 'detail', 'export_csv', 'export_xlsx'], CorexAbility::MANAGE_DATA),
            );
        }
    };
}

function exportSetRequest(string $method, string $path, array $payload = [], array $params = []): WP_REST_Request
{
    $request = new WP_REST_Request($method, '/corex/v1/data-exports' . $path);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    $method === 'GET' ? $request->set_query_params($payload) : $request->set_body_params($payload);
    $request->set_url_params($params);

    return $request;
}

/** @return array<string,string> Every member of a zip, by name. */
function exportSetArchive(string $bytes): array
{
    $path = tempnam(sys_get_temp_dir(), 'corex-set-test-');
    file_put_contents($path, $bytes);
    $zip = new ZipArchive();
    $zip->open($path);
    $members = [];
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $members[$zip->getNameIndex($index)] = (string) $zip->getFromIndex($index);
    }
    $zip->close();
    unlink($path);

    return $members;
}

beforeEach(function () {
    $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($admins[0] ?? 0));
    $container = Boot::app()->container();
    $container->make(WpDataExportStore::class)->registerPostType();
    $this->runs = CreatedPosts::watch(WpDataExportStore::POST_TYPE);

    $registry = $container->make(DataRegistry::class);
    $registry->register(exportSetModel('set-orders', 'Set orders', [
        ['id' => 1, 'code' => 'ORD-1', 'person' => 'Ada Lovelace'],
        ['id' => 2, 'code' => 'ORD-2', 'person' => 'Grace Hopper'],
    ]));
    $registry->register(exportSetModel('set-parcels', 'Set parcels', [
        ['id' => 1, 'code' => 'PCL-1', 'person' => 'Linus'],
    ]));
    $registry->register(exportSetModel('set-empty', 'Set empty', []));

    $this->controller = $container->make(DataExportSetController::class);
    $this->store = $container->make(WpDataExportStore::class);
});

afterEach(function () {
    global $wpdb;
    $activity = (new Migrator())->fullName(ActivityTable::NAME);

    foreach ($this->runs->ids() as $id) {
        $run = $this->store->find($id);
        $file = $this->store->file($id);
        if ($file !== null) {
            unlink($file['path']);
        }
        if ($run !== null && $run->jobId > 0) {
            $wpdb->delete($wpdb->prefix . 'corex_bounded_jobs', ['id' => $run->jobId]);
        }
        $wpdb->delete($activity, ['target_type' => 'data_export', 'target_id' => (string) $id]);
    }
    $this->runs->delete();

    $leftover = get_user_by('login', DATA_SET_TEST_LOGIN);
    if ($leftover instanceof WP_User) {
        wp_set_current_user(0);
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($leftover->ID);
    }
});

/**
 * Asks for an export of the two models that hold records and the one that holds none, and takes
 * its job to the end as the open dialog does.
 *
 * @return array{id:int,export:array<string,mixed>,progress:array<string,mixed>}
 */
function exportTheSet(object $test, string $format): array
{
    $export = $test->controller->create(exportSetRequest('POST', '', [
        'parts' => [['source' => 'set-orders'], ['source' => 'set-parcels', 'columns' => ['code']], ['source' => 'set-empty']],
        'format' => $format,
        'personal_data_acknowledged' => true,
    ]))->get_data()['data']['export'];
    $id = (int) $export['id'];
    do {
        $progress = $test->controller->advance(exportSetRequest('POST', "/$id/advance", [], ['id' => $id]))->get_data()['data']['progress'];
    } while ($progress['state'] === 'running' || $progress['state'] === 'queued');

    return ['id' => $id, 'export' => $export, 'progress' => $progress];
}

it('registers the routes for several models outside the pattern that takes any word for a source', function () {
    add_action('rest_api_init', [$this->controller, 'register']);
    do_action('rest_api_init', rest_get_server());

    expect(rest_get_server()->get_routes())->toHaveKeys([
        '/corex/v1/data-exports', '/corex/v1/data-exports/preview',
        '/corex/v1/data-exports/(?P<id>\d+)', '/corex/v1/data-exports/(?P<id>\d+)/advance',
        '/corex/v1/data-exports/(?P<id>\d+)/download',
    ]);
});

it('says how many records each model would give, and which formats can be written', function () {
    $answer = $this->controller->preview(exportSetRequest('POST', '/preview', [
        'sources' => ['set-orders', 'set-parcels', 'set-empty'],
    ]))->get_data();

    expect($answer['ok'])->toBeTrue()
        ->and($answer['data']['counts'])->toBe(['set-orders' => 2, 'set-parcels' => 1, 'set-empty' => 0])
        ->and($answer['data']['formats']['available'])->toContain('xlsx', 'csv');
});

it('exports several models into one workbook, a sheet for each that holds records', function () {
    $made = exportTheSet($this, 'xlsx');

    $artifact = $this->controller->download(
        exportSetRequest('GET', "/{$made['id']}/download", [], ['id' => $made['id']])
    )->get_data()['data']['artifact'];
    $workbook = exportSetArchive((string) base64_decode($artifact['content'], true));

    expect($made['progress'])->toMatchArray(['state' => 'completed', 'processed' => 3, 'total' => 3])
        // The empty model was left out when the export was asked for.
        ->and(array_column($made['export']['parts'], 'source_key'))->toBe(['set-orders', 'set-parcels'])
        ->and($artifact['filename'])->toBe(sanitize_file_name(
            sanitize_title(get_bloginfo('name')) . '-2-models-' . gmdate('Y-m-d') . '.xlsx'
        ))
        ->and($workbook['xl/workbook.xml'])->toContain('name="Set orders"')->toContain('name="Set parcels"')
        ->and($workbook['xl/worksheets/sheet1.xml'])->toContain('>ORD-2<')->toContain('>Grace Hopper<')
        // Only the column that was asked for, of the model it was asked for.
        ->and($workbook['xl/worksheets/sheet2.xml'])->toContain('>PCL-1<')->not->toContain('Linus');
});

it('exports several models as text into one archive, a file for each', function () {
    $made = exportTheSet($this, 'csv');

    $artifact = $this->controller->download(
        exportSetRequest('GET', "/{$made['id']}/download", [], ['id' => $made['id']])
    )->get_data()['data']['artifact'];

    expect($artifact['filename'])->toEndWith('.zip')
        ->and(array_keys(exportSetArchive((string) base64_decode($artifact['content'], true))))
        ->toBe(['Set orders.csv', 'Set parcels.csv']);
});

it('lists the export once, under each model it covered, with its size and when it expires, and deletes its file', function () {
    $made = exportTheSet($this, 'csv');
    $path = $this->store->file($made['id'])['path'];

    $listed = $this->controller->index(exportSetRequest('GET', ''))->get_data()['data']['exports'];
    $entry = array_values(array_filter($listed, static fn (array $export): bool => $export['id'] === $made['id']))[0];
    $underParcels = Boot::app()->container()->make(DataManagementController::class)->exports(
        (static function (): WP_REST_Request {
            $request = new WP_REST_Request('GET', '/corex/v1/data/set-parcels/exports');
            $request->set_url_params(['source' => 'set-parcels']);

            return $request;
        })()
    )->get_data()['data']['exports'];

    $deleted = $this->controller->delete(exportSetRequest('DELETE', "/{$made['id']}", [], ['id' => $made['id']]));
    $after = $this->controller->index(exportSetRequest('GET', ''))->get_data()['data']['exports'];
    $entryAfter = array_values(array_filter($after, static fn (array $export): bool => $export['id'] === $made['id']))[0];
    $refused = $this->controller->download(exportSetRequest('GET', "/{$made['id']}/download", [], ['id' => $made['id']]));

    expect($entry['file_state'])->toBe('ready')
        ->and($entry['file_bytes'])->toBeGreaterThan(0)
        ->and($entry['expires_at'])->toStartWith(gmdate('Y-m-d', strtotime('+30 days')))
        ->and($entry['actor_name'])->not->toBe('')
        ->and(array_column($underParcels, 'id'))->toContain($made['id'])
        ->and($deleted->get_data()['ok'])->toBeTrue()
        ->and(is_file($path))->toBeFalse()
        ->and($entryAfter['file_state'])->toBe('deleted')
        ->and($refused->get_data()['ok'])->toBeFalse()
        ->and($refused->get_data()['message'])->toContain('deleted');
});

it('writes the export and its deletion to the activity log with the models and no value of any record', function () {
    global $wpdb;
    $made = exportTheSet($this, 'csv');
    $this->controller->delete(exportSetRequest('DELETE', "/{$made['id']}", [], ['id' => $made['id']]));

    $events = $wpdb->get_results($wpdb->prepare(
        'SELECT kind, context_json AS context FROM %i WHERE target_type = %s AND target_id = %s ORDER BY id',
        (new Migrator())->fullName(ActivityTable::NAME),
        'data_export',
        (string) $made['id'],
    ), ARRAY_A);

    expect(array_column($events, 'kind'))->toBe(['data.export.completed', 'data.export.deleted'])
        ->and($events[0]['context'])->toContain('set-orders')->toContain('set-parcels')
        ->and(implode(' ', array_column($events, 'context')))->not->toContain('Grace')->not->toContain('ORD-');
});

it('answers as if another person’s export were not there, and shows them none of it', function () {
    $made = exportTheSet($this, 'csv');

    $other = (int) wp_insert_user([
        'user_login' => DATA_SET_TEST_LOGIN,
        'user_pass' => wp_generate_password(20, true, true),
        'user_email' => DATA_SET_TEST_LOGIN . '@example.com',
        // No role: a role is a door, and the install may have granted a role any ability.
        'role' => '',
    ]);
    wp_set_current_user($other);
    wp_get_current_user()->add_cap(CorexAbility::MANAGE_DATA);

    $listed = $this->controller->index(exportSetRequest('GET', ''))->get_data()['data']['exports'];
    $download = $this->controller->download(exportSetRequest('GET', "/{$made['id']}/download", [], ['id' => $made['id']]));
    $delete = $this->controller->delete(exportSetRequest('DELETE', "/{$made['id']}", [], ['id' => $made['id']]));

    expect(array_column($listed, 'id'))->not->toContain($made['id'])
        ->and($download->get_data()['ok'])->toBeFalse()
        ->and($delete->get_data()['ok'])->toBeFalse()
        ->and($this->store->file($made['id']))->not->toBeNull();
});

it('refuses several models for a reason it says, and makes no export', function (array $request, int $status, string $reason) {
    $answer = $this->controller->create(exportSetRequest('POST', '', $request + [
        'format' => 'csv', 'personal_data_acknowledged' => true,
    ]));

    expect($answer->get_status())->toBe($status)
        ->and($answer->get_data()['message'])->toContain($reason)
        ->and($this->runs->ids())->toBe([]);
})->with([
    'every model is empty' => [['parts' => [['source' => 'set-empty']]], 422, 'nothing to export'],
    'personal data, not acknowledged' => [['parts' => [['source' => 'set-orders']], 'personal_data_acknowledged' => false], 422, 'acknowledge'],
    'a model that is not there' => [['parts' => [['source' => 'set-nowhere']]], 404, 'unavailable'],
    'no model at all' => [['parts' => []], 422, 'model'],
]);
