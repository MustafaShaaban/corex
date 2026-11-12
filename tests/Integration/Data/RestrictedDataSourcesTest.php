<?php

/**
 * Security records on the Data screen go only to the people who manage them (spec 112, User
 * Story 6, FR-029 to FR-031, SC-007).
 *
 * Through the real REST server, as a person of the test's own who holds "Manage data" and nothing
 * else. Two models exist only in this file: one owned by "Manage access", one owned by nobody; the
 * three security tables CoreX ships are checked by their keys.
 *
 * @package Corex\Tests\Integration\Data
 */

declare(strict_types=1);

use Corex\Access\CorexAbility;
use Corex\Boot;
use Corex\Config\Access\AccessAuditLog;
use Corex\Config\Data\CapabilityAwareDataSource;
use Corex\Config\Data\DataQuery;
use Corex\Config\Data\DataRegistry;
use Corex\Config\Data\DataSource;
use Corex\Config\Data\ExportableDataSource;
use Corex\Config\Data\RestrictedDataSource;
use Corex\Config\DataModels\WpDataExportStore;
use Corex\Data\DataField;
use Corex\Data\DataSourceCapabilities;
use Corex\Tests\Support\CreatedPosts;

const RESTRICTED_SECURITY_TABLES = ['table-access-requests', 'table-role-ability-grants', 'table-login-attempts'];

/** A model with two records, owned by `$owner` when one is given. */
function restrictedTestModel(string $key, ?string $owner): DataSource
{
    return new class($key, $owner) implements DataSource, ExportableDataSource, CapabilityAwareDataSource, RestrictedDataSource {
        private array $records = [['id' => 1, 'code' => 'A-1'], ['id' => 2, 'code' => 'A-2']];

        public function __construct(private string $key, private ?string $owner) {}
        public function key(): string { return $this->key; }
        public function label(): string { return 'Model ' . $this->key; }
        public function ownerAbility(): ?string { return $this->owner; }
        public function columns(): array { return [['id' => 'code', 'label' => 'Code']]; }
        public function rows(int $page, int $perPage): array { return $this->records; }
        public function total(): int { return count($this->records); }
        public function delete(int $id): bool { return false; }
        public function query(DataQuery $query): array { return $this->records; }
        public function count(DataQuery $query): int { return $this->total(); }
        public function record(int $id): ?array { return $this->records[$id - 1] ?? null; }
        public function exportRows(DataQuery $query): array { return $this->records; }
        public function exportRowsOf(array $ids): array { return []; }
        public function fields(): array
        {
            return [new DataField('code', 'Code', DataField::TYPE_TEXT, false, true, true, [], true, DataField::PERSONAL_NONE, [], [])];
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

/** @param array<string,mixed> $body */
function restrictedDataRoute(string $method, string $path, array $body = []): WP_REST_Response
{
    $request = new WP_REST_Request($method, '/corex/v1/' . $path);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    if ($body !== []) {
        $method === 'GET' ? $request->set_query_params($body) : $request->set_body_params($body);
    }

    return rest_do_request($request);
}

/** @return list<string> */
function catalogKeys(): array
{
    return array_column(restrictedDataRoute('GET', 'data/sources')->get_data()['data']['sources'], 'key');
}

beforeEach(function () {
    $container = Boot::app()->container();
    $container->make(WpDataExportStore::class)->registerPostType();
    $this->runs = CreatedPosts::watch(WpDataExportStore::POST_TYPE);

    $registry = $container->make(DataRegistry::class);
    $registry->register(restrictedTestModel('restricted-owned', CorexAbility::MANAGE_ACCESS));
    $registry->register(restrictedTestModel('restricted-open', null));

    $this->audit = $container->make(AccessAuditLog::class);
    $this->savedAudit = get_option('corex_access_audit_log', null);

    $this->holderId = wp_insert_user([
        'user_login' => 'corex-data-holder-' . wp_generate_password(8, false),
        'user_pass'  => wp_generate_password(),
        'user_email' => uniqid('corex-data-holder-', true) . '@example.test',
        'role'       => 'subscriber',
    ]);
    expect($this->holderId)->toBeInt();
    (new WP_User($this->holderId))->add_cap(CorexAbility::MANAGE_DATA, true);
    wp_set_current_user($this->holderId);
});

afterEach(function () {
    $store = Boot::app()->container()->make(WpDataExportStore::class);
    foreach ($this->runs->ids() as $id) {
        $file = $store->file($id);
        if ($file !== null) {
            unlink($file['path']);
        }
    }
    $this->runs->delete();

    $this->savedAudit === null
        ? delete_option('corex_access_audit_log')
        : update_option('corex_access_audit_log', $this->savedAudit, false);

    wp_set_current_user(0);
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($this->holderId);
});

it('leaves the security models out of the catalogue for a holder of "Manage data" alone', function () {
    $keys = catalogKeys();

    expect(array_intersect($keys, [...RESTRICTED_SECURITY_TABLES, 'restricted-owned']))->toBe([])
        ->and($keys)->toContain('restricted-open');
});

it('refuses every read of a security model with the standard access-denied answer', function (string $path) {
    $response = restrictedDataRoute(str_ends_with($path, 'preview') ? 'POST' : 'GET', $path, str_ends_with($path, 'preview') ? ['scope' => 'all', 'format' => 'csv'] : []);

    expect($response->get_status())->toBe(403)
        ->and($response->get_data()['code'] ?? null)->toBe('data_forbidden');
})->with([
    'the list'          => 'data/restricted-owned',
    'one record'        => 'data/restricted-owned/1',
    'an export preview' => 'data/restricted-owned/exports/preview',
    'access requests'   => 'data/table-access-requests',
    'role grants'       => 'data/table-role-ability-grants',
    'sign-in attempts'  => 'data/table-login-attempts',
]);

it('records each refusal in the access audit, under the model it was for', function () {
    restrictedDataRoute('GET', 'data/restricted-owned');
    restrictedDataRoute('GET', 'data/restricted-owned/1');

    $mine = array_values(array_filter(
        $this->audit->entries(),
        fn (array $entry): bool => $entry['user'] === $this->holderId,
    ));

    expect($mine)->toHaveCount(1)
        ->and($mine[0]['kind'])->toBe('data')
        ->and($mine[0]['section'])->toBe('restricted-owned')
        ->and($mine[0]['count'])->toBe(2);
});

it('leaves a security model out of an export of several, and says which and why', function () {
    $answer = restrictedDataRoute('POST', 'data-exports', [
        'parts'  => [['source' => 'restricted-owned'], ['source' => 'restricted-open']],
        'format' => 'csv',
    ])->get_data();
    $parts = $answer['data']['export']['parts'];

    expect($answer['ok'])->toBeTrue()
        ->and(array_column($parts, 'left_out', 'source_key'))->toBe(['restricted-owned' => true, 'restricted-open' => false])
        ->and($parts[0]['left_out_because'])->toBe('restricted')
        ->and($parts[0]['label'])->toBe('Model restricted-owned');
});

it('refuses an export made only of security models, and names them', function () {
    $response = restrictedDataRoute('POST', 'data-exports', [
        'parts'  => [['source' => 'restricted-owned']],
        'format' => 'csv',
    ]);

    expect($response->get_status())->toBe(403)
        ->and($response->get_data()['message'] ?? '')->toContain('Model restricted-owned');
});

it('lets an administrator, and a holder of both abilities, read the security models as before', function () {
    (new WP_User($this->holderId))->add_cap(CorexAbility::MANAGE_ACCESS, true);
    // The signed-in user object is WordPress's own copy; select the person again to read the new ability.
    wp_set_current_user(0);
    wp_set_current_user($this->holderId);

    expect(catalogKeys())->toContain('restricted-owned', 'table-access-requests', 'table-role-ability-grants')
        ->and(restrictedDataRoute('GET', 'data/restricted-owned')->get_status())->toBe(200);

    $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) $admins[0]);

    expect(catalogKeys())->toContain(...RESTRICTED_SECURITY_TABLES);
});
