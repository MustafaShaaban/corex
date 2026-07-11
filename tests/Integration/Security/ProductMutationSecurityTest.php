<?php

/**
 * Cross-domain mutation security: unauthorized, stale, and replayed previews (spec 068 T224).
 *
 * Every product write travels through an actor-bound, five-minute, single-use preview before
 * exact adapter dispatch. These tests assert that shared invariant on the Data mutation and
 * Data Models migration surfaces so no domain can be driven by an unauthorized actor, a reused
 * token, or an expired confirmation.
 *
 * @package Corex\Tests\Integration\Security
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Data\CapabilityAwareDataSource;
use Corex\Config\Data\DataMutationApplyRequest;
use Corex\Config\Data\DataMutationRequest;
use Corex\Config\Data\DataMutationService;
use Corex\Config\Data\DataQuery;
use Corex\Config\Data\DataRegistry;
use Corex\Config\Data\DataSource;
use Corex\Config\Data\FieldAwareDataSource;
use Corex\Config\Data\QueryableDataSource;
use Corex\Config\Data\WpDataMutationPreviewStore;
use Corex\Config\Data\WritableDataSource;
use Corex\Config\DataModels\MigrationDefinition;
use Corex\Config\DataModels\WpMigrationPreviewStore;
use Corex\Data\DataField;
use Corex\Data\DataSourceCapabilities;
use Corex\Data\DataWriteAdapter;
use Corex\Operations\OperationResult;

const MUTATION_SECURITY_SOURCE = 'security-contacts';

/** Replicates the transient key the store uses so an expired confirmation can be staged. */
function mutationPreviewTransientKey(string $token): string
{
    return 'corex_data_mutation_' . hash('sha256', $token);
}

function securityOperationResult(array $ids): OperationResult
{
    $now = new DateTimeImmutable('2026-07-10T12:00:00+00:00');

    return new OperationResult('123e4567-e89b-42d3-a456-426614174000', OperationResult::STATE_COMPLETED, 'Completed.', [], $ids, $now, $now);
}

beforeEach(function () {
    $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    $this->adminId = (int) ($admins[0] ?? 0);
    wp_set_current_user($this->adminId);

    $this->subscriberId = (int) wp_insert_user([
        'user_login' => 'corex-mutation-subscriber',
        'user_pass' => wp_generate_password(20, true, true),
        'user_email' => 'corex-mutation-subscriber@example.com',
        'role' => 'subscriber',
    ]);

    $adapter = new class implements DataWriteAdapter {
        public array $records = [
            1 => ['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com', 'status' => 'active'],
        ];
        public function create(array $values): OperationResult { $id = count($this->records) + 1; $this->records[$id] = ['id' => $id, ...$values]; return securityOperationResult([$id]); }
        public function update(array $recordIds, array $values): OperationResult { foreach ($recordIds as $id) { $this->records[(int) $id] = [...$this->records[(int) $id], ...$values]; } return securityOperationResult($recordIds); }
        public function delete(array $recordIds): OperationResult { foreach ($recordIds as $id) { unset($this->records[(int) $id]); } return securityOperationResult($recordIds); }
    };
    $source = new class($adapter) implements DataSource, QueryableDataSource, CapabilityAwareDataSource, FieldAwareDataSource, WritableDataSource {
        public function __construct(private DataWriteAdapter $adapter) {}
        public function key(): string { return MUTATION_SECURITY_SOURCE; }
        public function label(): string { return 'Security Contacts'; }
        public function columns(): array { return [['id' => 'name', 'label' => 'Name'], ['id' => 'status', 'label' => 'Status']]; }
        public function rows(int $page, int $perPage): array { return array_values($this->adapter->records); }
        public function total(): int { return count($this->adapter->records); }
        public function delete(int $id): bool { return false; }
        public function query(DataQuery $query): array { return $this->rows($query->page, $query->perPage); }
        public function count(DataQuery $query): int { return $this->total(); }
        public function record(int $id): ?array { return $this->adapter->records[$id] ?? null; }
        public function writeAdapter(): DataWriteAdapter { return $this->adapter; }
        public function fields(): array { return [
            new DataField('name', 'Name', DataField::TYPE_TEXT, true, false, false, ['contains'], true, DataField::PERSONAL_IDENTITY, [], []),
            new DataField('status', 'Status', DataField::TYPE_SELECT, false, false, false, ['equals'], true, DataField::PERSONAL_NONE, ['options' => ['active', 'inactive']], []),
        ]; }
        public function capabilities(): DataSourceCapabilities { return new DataSourceCapabilities(
            sourceKey: MUTATION_SECURITY_SOURCE, read: true, query: true, schema: true, detail: true,
            create: true, update: true, delete: true, bulkUpdate: true, bulkDelete: true,
            importDryRun: false, importCommit: false, exportCsv: false, exportXlsx: false,
            migrations: false, rollback: false, maxPageSize: 100,
            permissionMap: array_fill_keys(['read','query','schema','detail','create','update','delete','bulk_update','bulk_delete'], 'corex_manage_data'),
        ); }
    };

    $container = Boot::app()->container();
    $container->make(DataRegistry::class)->register($source);
    $this->adapter = $adapter;
    $this->service = $container->make(DataMutationService::class);
    $this->previewStore = new WpDataMutationPreviewStore();
});

afterEach(function () {
    if (! empty($this->subscriberId)) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($this->subscriberId);
    }
});

it('denies a mutation preview to an actor without the data capability', function () {
    $request = DataMutationRequest::from([
        'actor_id' => $this->subscriberId,
        'source_key' => MUTATION_SECURITY_SOURCE,
        'operation' => DataSourceCapabilities::UPDATE,
        'record_ids' => [1],
        'values' => ['status' => 'inactive'],
    ]);

    expect(fn () => $this->service->preview($request))
        ->toThrow(DomainException::class, 'The actor does not have permission for this data operation.');
    expect($this->adapter->records[1]['status'])->toBe('active');
});

it('applies an authorized preview exactly once and rejects the replayed token', function () {
    $preview = $this->service->preview(DataMutationRequest::from([
        'actor_id' => $this->adminId,
        'source_key' => MUTATION_SECURITY_SOURCE,
        'operation' => DataSourceCapabilities::UPDATE,
        'record_ids' => [1],
        'values' => ['status' => 'inactive'],
    ]));

    $applyRequest = new DataMutationApplyRequest(
        actorId: $this->adminId,
        token: $preview->token,
        sourceKey: MUTATION_SECURITY_SOURCE,
        actorLabel: 'Integration administrator',
        now: new DateTimeImmutable('2026-07-10T12:00:00+00:00'),
    );

    $applied = $this->service->apply($applyRequest);

    expect($applied->state)->toBe(OperationResult::STATE_COMPLETED)
        ->and($this->adapter->records[1]['status'])->toBe('inactive');

    // A single-use token cannot be replayed.
    expect(fn () => $this->service->apply($applyRequest))
        ->toThrow(DomainException::class, 'The data mutation preview expired or was already used.');
});

it('rejects a stale preview whose confirmation window has elapsed', function () {
    $preview = $this->service->preview(DataMutationRequest::from([
        'actor_id' => $this->adminId,
        'source_key' => MUTATION_SECURITY_SOURCE,
        'operation' => DataSourceCapabilities::UPDATE,
        'record_ids' => [1],
        'values' => ['status' => 'inactive'],
    ]));

    // Age the stored confirmation past its five-minute window without evicting the transient.
    $stale = $preview->toArray();
    $stale['expires_at'] = time() - 1;
    set_transient(mutationPreviewTransientKey($preview->token), $stale, 300);

    $applyRequest = new DataMutationApplyRequest(
        actorId: $this->adminId,
        token: $preview->token,
        sourceKey: MUTATION_SECURITY_SOURCE,
        actorLabel: 'Integration administrator',
        now: new DateTimeImmutable('2026-07-10T12:10:00+00:00'),
    );

    expect(fn () => $this->service->apply($applyRequest))
        ->toThrow(DomainException::class, 'The data mutation preview expired or was already used.');
    expect($this->adapter->records[1]['status'])->toBe('active');
});

it('binds a mutation preview to the issuing actor in the store', function () {
    $issue = fn (): string => $this->previewStore->issue(DataMutationRequest::from([
        'actor_id' => $this->adminId,
        'source_key' => MUTATION_SECURITY_SOURCE,
        'operation' => DataSourceCapabilities::DELETE,
        'record_ids' => [1],
        'values' => [],
    ]))->token;

    // A different actor cannot consume another actor's confirmation.
    expect($this->previewStore->consume($issue(), $this->subscriberId))->toBeNull();

    // The issuing actor consumes a fresh confirmation once, then never again.
    $token = $issue();
    expect($this->previewStore->consume($token, $this->adminId))->not->toBeNull();
    expect($this->previewStore->consume($token, $this->adminId))->toBeNull();
});

it('binds a migration confirmation to the issuing actor and single use across the Data Models domain', function () {
    $store = new WpMigrationPreviewStore();
    $definition = new MigrationDefinition('contacts-v2', '2.0.0', 'Add state index.', ['Create index'], true, true);
    $issue = fn (): string => $store->issue($this->adminId, 'apply', MUTATION_SECURITY_SOURCE, $definition)->token;

    // A different actor cannot consume another actor's migration confirmation.
    expect($store->consume($issue(), $this->subscriberId))->toBeNull();

    // The issuing actor consumes a fresh confirmation once, then never again.
    $token = $issue();
    expect($store->consume($token, $this->adminId))->not->toBeNull();
    expect($store->consume($token, $this->adminId))->toBeNull();
});
