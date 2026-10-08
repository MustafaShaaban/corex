<?php

/**
 * Column-scoped export job/history tests (spec 068 T121 / FR-062, FR-068), and the file a Data
 * export writes (spec 103, US10).
 *
 * @package Corex\Tests\Unit\DataModels
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityRepository;
use Corex\Activity\ActivityService;
use Corex\Config\Data\CapabilityAwareDataSource;
use Corex\Config\Data\DataAccessPolicy;
use Corex\Config\Data\DataQuery;
use Corex\Config\Data\DataQueryService;
use Corex\Config\Data\DataRegistry;
use Corex\Config\Data\DataSource;
use Corex\Config\Data\DataSourceService;
use Corex\Config\Data\ExportableDataSource;
use Corex\Config\Data\SubmissionsSource;
use Corex\Config\DataModels\DataExportFiles;
use Corex\Config\DataModels\DataExportJobHandler;
use Corex\Config\DataModels\DataExportJobQueue;
use Corex\Config\DataModels\DataExportRequest;
use Corex\Config\DataModels\DataExportRun;
use Corex\Config\DataModels\DataExportService;
use Corex\Config\DataModels\DataExportStore;
use Corex\Config\DataModels\DataExportTable;
use Corex\Config\Export\ExportDirectory;
use Corex\Config\Export\ExportWriters;
use Corex\Data\DataField;
use Corex\Data\DataSourceCapabilities;
use Corex\Jobs\BoundedJob;
use Corex\Tests\Support\InMemorySubmissionsReader;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('wp_json_encode')->alias('json_encode');
    Functions\when('wp_delete_file')->alias('unlink');
    Functions\when('sanitize_key')->alias(static fn (string $key): string => strtolower($key));
});

/**
 * A source whose three shapes differ, as a real one's may: the row its table shows, the record its
 * detail view shows, and the row it hands an export.
 */
function contactsSource(bool $xlsx = true): DataSource
{
    return new class($xlsx) implements DataSource, ExportableDataSource, CapabilityAwareDataSource {
        private array $records = [
            ['id' => 1, 'name' => '=Ada', 'email' => 'ada@example.com', 'status' => 'active', 'joined' => '2026-10-07 09:30:00', 'tags' => ['vip', 'beta'], 'secret' => 'drop'],
            ['id' => 2, 'name' => 'Grace', 'email' => 'grace@example.com', 'status' => 'inactive', 'joined' => '2026-10-06 08:00:00', 'tags' => [], 'secret' => 'drop'],
            ['id' => 3, 'name' => 'Linus', 'email' => 'linus@example.com', 'status' => 'active', 'joined' => '2026-10-05 17:45:00', 'tags' => ['beta'], 'secret' => 'drop'],
        ];
        public function __construct(private bool $xlsx) {}
        /** A record that arrives in the source after an export of it was counted. */
        public function arrive(array $record): void { $this->records[] = $record; }
        public function key(): string { return 'contacts'; }
        public function label(): string { return 'Contacts'; }
        public function columns(): array { return [['id' => 'name', 'label' => 'Name'], ['id' => 'email', 'label' => 'Email'], ['id' => 'status', 'label' => 'Status']]; }
        public function rows(int $page, int $perPage): array { return array_slice($this->records, ($page - 1) * $perPage, $perPage); }
        public function total(): int { return count($this->records); }
        public function delete(int $id): bool { return false; }
        public function query(DataQuery $query): array
        {
            $rows = $this->matching($query);

            return array_slice($rows, ($query->page - 1) * $query->perPage, $query->perPage);
        }
        public function count(DataQuery $query): int { return count($this->matching($query)); }
        /** The detail view's shape: labelled pairs, with nothing under a field's key. */
        public function record(int $id): ?array
        {
            foreach ($this->records as $record) {
                if ($record['id'] === $id) {
                    return ['id' => $id, 'fields' => [['label' => 'Name', 'value' => $record['name']]]];
                }
            }

            return null;
        }
        public function exportRows(DataQuery $query): array { return $this->query($query); }
        public function exportRowsOf(array $ids): array
        {
            return array_values(array_filter($this->records, static fn (array $record): bool => in_array($record['id'], $ids, true)));
        }
        public function capabilities(): DataSourceCapabilities
        {
            return new DataSourceCapabilities(
                sourceKey: 'contacts', read: true, query: true, schema: true, detail: true,
                create: false, update: false, delete: false, bulkUpdate: false, bulkDelete: false,
                importDryRun: false, importCommit: false, exportCsv: true, exportXlsx: $this->xlsx,
                migrations: false, rollback: false, maxPageSize: 100,
                permissionMap: ['query' => 'corex_manage_data', 'detail' => 'corex_manage_data', 'export_csv' => 'corex_manage_data', 'export_xlsx' => 'corex_manage_data'],
            );
        }
        public function fields(): array
        {
            return [
                new DataField('id', 'ID', DataField::TYPE_ID, false, true, true, [], true, DataField::PERSONAL_NONE, [], []),
                new DataField('name', 'Name', DataField::TYPE_TEXT, true, false, true, ['contains'], true, DataField::PERSONAL_IDENTITY, [], []),
                new DataField('email', 'Email', DataField::TYPE_EMAIL, true, false, true, ['equals'], true, DataField::PERSONAL_CONTACT, [], []),
                new DataField('status', 'Status', DataField::TYPE_SELECT, false, true, true, ['equals'], true, DataField::PERSONAL_NONE, ['options' => ['active', 'inactive']], []),
                new DataField('joined', 'Joined', DataField::TYPE_DATETIME, false, true, true, [], true, DataField::PERSONAL_NONE, [], []),
                new DataField('tags', 'Tags', DataField::TYPE_JSON, false, true, true, [], false, DataField::PERSONAL_NONE, [], []),
            ];
        }
        private function matching(DataQuery $query): array
        {
            return array_values(array_filter($this->records, static fn (array $record): bool =>
                ($query->filters['status'] ?? '') === '' || $record['status'] === $query->filters['status']));
        }
    };
}

/** @return array{DataExportService,DataExportStore,DataExportJobQueue,DataSourceService,ActivityRepository} */
function exportService(bool $xlsx = true, ?DataSource $source = null): array
{
    $registry = new DataRegistry();
    $registry->register($source ?? contactsSource($xlsx));
    $policy = new class implements DataAccessPolicy {
        public function allows(int $actorId, string $ability): bool { return $actorId === 7; }
    };
    $sources = new DataSourceService($registry, $policy);
    $queries = new DataQueryService($registry, $sources);
    $store = new class implements DataExportStore {
        /** @var array<int,DataExportRun> */ public array $runs = [];
        /** @var array<int,array{path:string,extension:string,content_type:string}> */ public array $files = [];
        /** @var array<int,string> */ public array $artifacts = [];
        public function create(DataExportRun $run): DataExportRun { $run = $run->withId(count($this->runs) + 1); return $this->runs[$run->id] = $run; }
        public function attachJob(int $id, int $jobId): DataExportRun { return $this->runs[$id] = $this->runs[$id]->withJob($jobId); }
        public function find(int $id): ?DataExportRun { return $this->runs[$id] ?? null; }
        public function findByHash(string $hash): ?DataExportRun { foreach ($this->runs as $run) if ($run->inputHash === $hash) return $run; return null; }
        public function history(int $actorId, bool $manageAll, int $limit): array { return array_slice(array_values(array_filter($this->runs, static fn (DataExportRun $run): bool => $manageAll || $run->actorId === $actorId)), 0, $limit); }
        public function saveFile(int $id, array $file): void { $this->files[$id] = $file; }
        public function file(int $id): ?array { return $this->files[$id] ?? null; }
        public function artifact(int $id): ?string { return $this->artifacts[$id] ?? null; }
        public function finish(int $id, int $rows): void { $this->runs[$id] = $this->runs[$id]->completed($rows); }
    };
    $queue = new class implements DataExportJobQueue {
        /** @var list<DataExportRun> */ public array $runs = [];
        /** @var list<int> */ public array $advanced = [];
        public function enqueue(DataExportRun $run): int { $this->runs[] = $run; return 52; }
        public function advance(int $jobId): array
        {
            $this->advanced[] = $jobId;

            return ['state' => 'running', 'processed' => 100, 'total' => 250, 'error' => ''];
        }
    };
    $activity = new class implements ActivityRepository {
        /** @var list<ActivityEvent> */ public array $events = [];
        public function append(ActivityEvent $event): ActivityEvent { $event = $event->withId(count($this->events) + 1); $this->events[] = $event; return $event; }
        public function find(int $id): ?ActivityEvent { return $this->events[$id - 1] ?? null; }
        public function query(array $filters = [], int $page = 1, int $perPage = 20): array { return $this->events; }
        public function pruneExpired(DateTimeImmutable $now, int $limit = 500): int { return 0; }
    };

    return [new DataExportService($sources, $queries, $store, $queue), $store, $queue, $sources, $activity];
}

function exportRequest(array $changes = []): DataExportRequest
{
    return DataExportRequest::from($changes + [
        'actor_id' => 7, 'source_key' => 'contacts', 'scope' => 'filtered',
        'query' => ['filters' => ['status' => 'active']], 'selected_ids' => [],
        'columns' => ['name', 'status'], 'format' => 'csv', 'personal_data_acknowledged' => true,
    ]);
}

/** The real table, writers and working file, in a scratch directory of its own. */
function dataExportFiles(): array
{
    $directory = new class implements ExportDirectory {
        private string $path = '';

        public function path(): string
        {
            if ($this->path === '') {
                $this->path = sys_get_temp_dir() . '/corex_data_export_' . uniqid('', true);
                mkdir($this->path);
            }

            return $this->path;
        }
    };

    return [
        new DataExportFiles(
            new DataExportTable(static fn (): DateTimeZone => new DateTimeZone('UTC')),
            new ExportWriters(static fn (): bool => false),
            $directory,
        ),
        $directory,
    ];
}

/**
 * Runs an export's job to the end, a batch at a time, as the runner would.
 *
 * @return array{download:array{filename:string,mime:string,content:string},run:DataExportRun,states:list<string>,directory:string,store:DataExportStore,events:list<ActivityEvent>}
 */
function runDataExport(array $request = [], int $batchSize = 25, ?DataSource $source = null): array
{
    [$service, $store, , $sources, $activity] = exportService(source: $source);
    [$files, $directory] = dataExportFiles();
    $run = $service->request(exportRequest($request));
    $now = new DateTimeImmutable('2026-07-04T12:00:00+00:00');
    $job = BoundedJob::queued(DataExportJobHandler::KIND, 7, $run->recordCount, $run->inputHash, $now)->withId(52)->start($now);
    $handler = new DataExportJobHandler($sources, $store, $files, new ActivityService($activity));
    $states = [];
    do {
        $job = $handler->handle($job, $batchSize);
        $states[] = $job->state;
    } while ($job->state !== BoundedJob::STATE_COMPLETED && count($states) < 10);

    return [
        'download' => $service->download(7, $run->id, false),
        'run' => $store->find($run->id),
        'states' => $states,
        'directory' => $directory->path(),
        'store' => $store,
        'events' => $activity->events,
    ];
}

/** One part of a written workbook, as text. */
function dataWorkbookPart(string $workbook, string $part): string
{
    $path = tempnam(sys_get_temp_dir(), 'corex-xlsx-test-');
    file_put_contents($path, $workbook);
    $zip = new ZipArchive();
    $opened = $zip->open($path);
    $xml = $opened === true ? (string) $zip->getFromName($part) : '';
    if ($opened === true) {
        $zip->close();
    }
    unlink($path);

    return $xml;
}

it('queues a column-scoped filtered export with truthful count and personal-data classes', function () {
    [$service, , $queue] = exportService();

    $run = $service->request(exportRequest());

    expect($run->recordCount)->toBe(2)
        ->and($run->columns)->toBe(['name', 'status'])
        ->and($run->personalDataClasses)->toBe(['identity'])
        ->and($run->jobId)->toBe(52)
        ->and($queue->runs)->toHaveCount(1);
});

it('requires personal-data acknowledgement and rejects undeclared columns or formats', function () {
    [$service] = exportService(false);

    expect(fn () => $service->request(exportRequest(['personal_data_acknowledged' => false])))
        ->toThrow(DomainException::class, 'acknowledge')
        ->and(fn () => $service->request(exportRequest(['columns' => ['secret']])))
        ->toThrow(InvalidArgumentException::class, 'column')
        ->and(fn () => $service->request(exportRequest(['format' => 'xlsx'])))
        ->toThrow(DomainException::class, 'support')
        ->and(fn () => exportRequest(['format' => 'pdf']))
        ->toThrow(InvalidArgumentException::class, 'format')
        ->and(fn () => exportRequest(['separator' => 'pipe']))
        ->toThrow(InvalidArgumentException::class, 'separator');
});

it('validates exact selected records and scopes history and downloads to the actor', function () {
    [$service] = exportService();
    $run = $service->request(exportRequest([
        'scope' => 'selected', 'selected_ids' => [3, 1, 3], 'query' => [], 'columns' => ['status'],
    ]));

    expect($run->selectedIds)->toBe([1, 3])
        ->and($run->recordCount)->toBe(2)
        ->and($service->history(7))->toHaveCount(1)
        ->and(fn () => $service->request(exportRequest(['scope' => 'selected', 'selected_ids' => [99], 'query' => []])))
        ->toThrow(DomainException::class, 'unavailable')
        ->and(fn () => $service->download(8, $run->id, false))
        ->toThrow(DomainException::class, 'unavailable');
});

it('refuses an export that would hold nothing, and queues nothing for it', function () {
    [$service, $store, $queue] = exportService();

    expect(fn () => $service->request(exportRequest(['query' => ['filters' => ['status' => 'archived']]])))
        ->toThrow(DomainException::class, 'There is nothing to export.')
        ->and($store->runs)->toBe([])
        ->and($queue->runs)->toBe([]);
});

it('counts what each scope would export, before it is exported', function () {
    [$service] = exportService();

    expect($service->preview(7, 'contacts', [1, 99], ['filters' => ['status' => 'active']]))
        ->toBe(['selected' => 1, 'filtered' => 2, 'all' => 3])
        ->and(fn () => $service->preview(8, 'contacts', [], []))
        ->toThrow(DomainException::class, 'permission');
});

it('treats the same request made twice as two exports', function () {
    [$service, $store] = exportService();
    $first = $service->request(exportRequest());
    $second = $service->request(exportRequest());

    expect($first->inputHash)->not->toBe($second->inputHash)
        ->and($store->findByHash($first->inputHash)?->id)->toBe($first->id)
        ->and($store->findByHash($second->inputHash)?->id)->toBe($second->id);
});

it('takes a step of an export for the person who made it, and for nobody else', function () {
    [$service, , $queue] = exportService();
    $run = $service->request(exportRequest());

    expect($service->advance(7, $run->id, false, 'contacts'))->toBe(['state' => 'running', 'processed' => 100, 'total' => 250, 'error' => ''])
        ->and($queue->advanced)->toBe([52])
        ->and(fn () => $service->advance(8, $run->id, false, 'contacts'))->toThrow(DomainException::class, 'unavailable')
        ->and(fn () => $service->advance(7, $run->id, false, 'orders'))->toThrow(DomainException::class, 'unavailable')
        ->and($queue->advanced)->toBe([52]);
});

it('writes only the columns asked for, as a CSV a spreadsheet opens, and completes the history', function () {
    $export = runDataExport();
    $download = $export['download'];

    expect($export['states'])->toBe([BoundedJob::STATE_COMPLETED])
        ->and($download['filename'])->toBe('contacts-' . $export['run']->createdAt->format('Y-m-d') . '.csv')
        ->and($download['mime'])->toBe('text/csv; charset=utf-8')
        // The mark that tells a spreadsheet the file is UTF-8, then the labels, then the rows; a
        // value a spreadsheet would run as a formula is kept from running.
        ->and($download['content'])->toBe("\xEF\xBB\xBFName,Status\r\n'=Ada,active\r\nLinus,active\r\n")
        ->and($export['run']->state)->toBe(DataExportRun::STATE_COMPLETED)
        ->and($export['run']->exportedRows)->toBe(2)
        ->and($export['events'][0]->kind)->toBe('data.export.completed');
});

it('keeps the file on disk and leaves no working file behind', function () {
    $export = runDataExport();
    $stored = $export['store']->file($export['run']->id);

    expect(is_file($stored['path']))->toBeTrue()
        ->and(dirname($stored['path']))->toBe($export['directory'])
        ->and(glob($export['directory'] . '/*.ndjson'))->toBe([]);
});

it('never writes a field that was not asked for, to the export or to its working file', function () {
    [$service, $store, , $sources, $activity] = exportService();
    [$files, $directory] = dataExportFiles();
    $run = $service->request(exportRequest());
    $now = new DateTimeImmutable('2026-07-04T12:00:00+00:00');
    $job = BoundedJob::queued(DataExportJobHandler::KIND, 7, 2, $run->inputHash, $now)->withId(52)->start($now);

    // One record of two: the job is not finished, so the working file is still there to read.
    (new DataExportJobHandler($sources, $store, $files, new ActivityService($activity)))->handle($job, 1);
    $working = (string) file_get_contents(glob($directory->path() . '/*.ndjson')[0]);

    expect($working)->toContain('=Ada')
        ->and($working)->not->toContain('example.com')
        ->and($working)->not->toContain('drop');
});

it('gathers an export over several batches and writes one file', function () {
    $export = runDataExport(['query' => [], 'scope' => 'all', 'columns' => ['id', 'name']], batchSize: 1);

    expect($export['states'])->toBe([BoundedJob::STATE_RUNNING, BoundedJob::STATE_RUNNING, BoundedJob::STATE_COMPLETED])
        ->and($export['download']['content'])->toBe("\xEF\xBB\xBFID,Name\r\n1,'=Ada\r\n2,Grace\r\n3,Linus\r\n");
});

it('parts the values with the separator that was asked for', function () {
    $export = runDataExport(['separator' => 'semicolon']);

    expect($export['download']['content'])->toContain("Name;Status\r\n");
});

it('writes a list as its items, never as the word Array', function () {
    $export = runDataExport(['columns' => ['name', 'tags']]);

    expect($export['download']['content'])->toContain("'=Ada,\"vip, beta\"\r\n")
        ->and($export['download']['content'])->not->toContain('Array');
});

it('writes a workbook with numbers as numbers and dates as dates, under the source’s name', function () {
    $export = runDataExport(['format' => 'xlsx', 'columns' => ['id', 'name', 'joined']]);
    $download = $export['download'];
    $sheet = dataWorkbookPart($download['content'], 'xl/worksheets/sheet1.xml');

    expect($download['filename'])->toEndWith('.xlsx')
        ->and($download['mime'])->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->and(dataWorkbookPart($download['content'], 'xl/workbook.xml'))->toContain('name="Contacts"')
        // The heading stays in view, and the id is a number a spreadsheet can sum.
        ->and($sheet)->toContain('state="frozen"')
        ->and($sheet)->toContain('<v>1</v>')
        // 7 October 2026 at 09:30, as the day number a spreadsheet keeps a date in.
        ->and($sheet)->toContain('<v>46302.39583333</v>')
        // Text that begins with "=" is text: there is no formula in the sheet.
        ->and($sheet)->toContain('=Ada')
        ->and($sheet)->not->toContain('<f>');
});

it('still hands back an export made before files were kept on disk', function () {
    [$service, $store] = exportService();
    $run = $service->request(exportRequest());
    $store->artifacts[$run->id] = "Name,Status\r\nLinus,active\r\n";
    $store->finish($run->id, 1);

    expect($service->download(7, $run->id, false))->toMatchArray([
        'mime' => 'text/csv; charset=utf-8',
        'content' => "Name,Status\r\nLinus,active\r\n",
    ])->and(fn () => $service->download(7, $run->id, false, 'other-source'))
        ->toThrow(DomainException::class, 'unavailable');
});

it('finishes an export, holding what was counted, when a record arrives in the source while it is written', function () {
    [$service, $store, , $sources, $activity] = exportService();
    [$files] = dataExportFiles();
    $run = $service->request(exportRequest(['scope' => 'all', 'query' => [], 'columns' => ['id', 'name']]));
    $now = new DateTimeImmutable('2026-07-04T12:00:00+00:00');
    $job = BoundedJob::queued(DataExportJobHandler::KIND, 7, $run->recordCount, $run->inputHash, $now)->withId(52)->start($now);
    $handler = new DataExportJobHandler($sources, $store, $files, new ActivityService($activity));

    // Two of the three that were counted. Then a fourth arrives, so the second batch holds two
    // records where one was expected: the job counted past its total and stopped with "Bounded
    // job counters are inconsistent." Found in a browser run, where other tests were submitting
    // forms while everything was being exported (2026-10-08).
    $job = $handler->handle($job, 2);
    $sources->authorize(7, 'contacts', DataSourceCapabilities::EXPORT_CSV)->arrive(
        ['id' => 4, 'name' => 'Late', 'email' => 'late@example.com', 'status' => 'active', 'joined' => '', 'tags' => []],
    );
    $job = $handler->handle($job, 2);

    expect($job->state)->toBe(BoundedJob::STATE_COMPLETED)
        ->and($job->processed)->toBe(3)
        ->and($service->download(7, $run->id, false)['content'])
        ->toBe("\xEF\xBB\xBFID,Name\r\n1,'=Ada\r\n2,Grace\r\n3,Linus\r\n");
});

/**
 * The Form submissions source CoreX ships, exported (found in a real export, 2026-10-08).
 *
 * The file began `Submitted,Form,Submission,Email,Name,Message` and every row ended `,,,`: the
 * dialog offered a column for each answer and nothing filled it. A ticked row lost "Submission"
 * as well, because it was read as the detail view shows it.
 */
function submissionsToExport(): SubmissionsSource
{
    return new SubmissionsSource(new InMemorySubmissionsReader([
        ['id' => 7, 'date' => '2026-10-08 13:28:00', 'form' => 'contact', 'fields' => [
            'email' => 'sam@example.com', 'name' => 'Sam', 'message' => 'Hello',
        ]],
        ['id' => 8, 'date' => '2026-10-08 14:00:00', 'form' => 'callback', 'fields' => ['name' => 'Mona']],
    ]));
}

it('writes each answer of a form submission under its own column, however the records were chosen', function (array $choice, string $lines) {
    $export = runDataExport(
        $choice + ['source_key' => 'submissions', 'columns' => ['date', 'form', 'summary', 'email', 'name', 'message']],
        source: submissionsToExport(),
    );

    expect($export['download']['content'])
        ->toBe("\xEF\xBB\xBFSubmitted,Form,Submission,Email,Name,Message\r\n" . $lines);
})->with(function (): array {
    $contact = '"2026-10-08 13:28",contact,"email: sam@example.com · name: Sam · message: Hello",sam@example.com,Sam,Hello' . "\r\n";
    // No email and no message on this form: two empty cells, in their columns.
    $callback = '"2026-10-08 14:00",callback,"name: Mona",,Mona,' . "\r\n";

    return [
        'the ticked rows' => [['scope' => 'selected', 'selected_ids' => [7], 'query' => []], $contact],
        'the filtered rows' => [['scope' => 'filtered', 'query' => ['filters' => ['form' => 'contact']]], $contact],
        'everything' => [['scope' => 'all', 'query' => []], $contact . $callback],
    ];
});

it('writes the same answers into a workbook', function () {
    $export = runDataExport(
        ['source_key' => 'submissions', 'columns' => ['summary', 'email', 'message'], 'format' => 'xlsx', 'scope' => 'selected', 'selected_ids' => [7], 'query' => []],
        source: submissionsToExport(),
    );
    $cells = dataWorkbookPart($export['download']['content'], 'xl/worksheets/sheet1.xml');

    expect($cells)->toContain('sam@example.com')
        ->and($cells)->toContain('Hello')
        ->and($cells)->toContain('email: sam@example.com · name: Sam · message: Hello');
});
