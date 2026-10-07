<?php

/**
 * Submission export scope/privacy/audit tests for spec 068 T099 / FR-053-FR-055.
 *
 * @package Corex\Tests\Unit\Submissions
 */

declare(strict_types=1);

use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityRepository;
use Corex\Activity\ActivityService;
use Corex\Config\Submissions\SubmissionAccessScope;
use Corex\Config\Submissions\SubmissionExportJobQueue;
use Corex\Config\Submissions\SubmissionExportJobHandler;
use Corex\Config\Submissions\SubmissionExportRequest;
use Corex\Config\Submissions\SubmissionExportRun;
use Corex\Config\Submissions\SubmissionExportService;
use Corex\Config\Submissions\SubmissionExportSource;
use Corex\Config\Submissions\SubmissionExportStore;
use Corex\Config\Export\ExportDirectory;
use Corex\Config\Export\ExportWriters;
use Corex\Config\Submissions\SubmissionExportFiles;
use Corex\Config\Submissions\SubmissionExportTable;
use Corex\Config\Submissions\SubmissionOwnerNames;
use Corex\Config\Submissions\SubmissionQuestions;
use Corex\Config\Submissions\SubmissionAccessPolicy;
use Corex\Config\Submissions\SubmissionInboxQuery;
use Corex\Config\Submissions\SubmissionInboxReader;
use Corex\Jobs\BoundedJob;

function exportReader(array $records): SubmissionInboxReader
{
    return new class($records) implements SubmissionInboxReader {
        public function __construct(private array $records)
        {
        }

        public function queryInbox(SubmissionInboxQuery $query, SubmissionAccessScope $scope): array
        {
            $items = array_values(array_filter($this->records, fn (array $record): bool =>
                $scope->allows($record) && ($query->includeTest || ! $record['is_test'])));

            return ['items' => array_slice($items, 0, $query->perPage), 'total' => count($items)];
        }

        public function findInbox(int $id, SubmissionAccessScope $scope): ?array
        {
            $record = $this->records[$id] ?? null;

            return is_array($record) && $scope->allows($record) ? $record : null;
        }
    };
}

function exportStore(): SubmissionExportStore
{
    return new class() implements SubmissionExportStore {
        /** @var array<int,SubmissionExportRun> */
        public array $runs = [];
        /** @var array<int,string> */
        public array $artifacts = [];

        public function create(SubmissionExportRun $run): SubmissionExportRun
        {
            $stored = $run->withId(count($this->runs) + 1);
            $this->runs[$stored->id] = $stored;

            return $stored;
        }

        public function attachJob(int $runId, int $jobId): SubmissionExportRun
        {
            return $this->runs[$runId] = $this->runs[$runId]->withJob($jobId);
        }

        public function find(int $runId): ?SubmissionExportRun
        {
            return $this->runs[$runId] ?? null;
        }

        public function findByHash(string $inputHash): ?SubmissionExportRun
        {
            foreach ($this->runs as $run) {
                if ($run->inputHash === $inputHash) {
                    return $run;
                }
            }

            return null;
        }

        public function history(SubmissionAccessScope $scope, int $limit): array
        {
            return array_values(array_filter($this->runs, fn (SubmissionExportRun $run): bool =>
                $scope->manageAll || $run->actorId === $scope->actorId));
        }

        /** @var array<int,array{path:string,extension:string,content_type:string,subject:string}> */
        public array $files = [];

        public function saveFile(int $runId, array $file, int $recordCount): void
        {
            $this->files[$runId] = $file;
        }

        public function file(int $runId): ?array
        {
            return $this->files[$runId] ?? null;
        }

        public function artifact(int $runId): ?string
        {
            return $this->artifacts[$runId] ?? null;
        }
    };
}

function exportQueue(): SubmissionExportJobQueue
{
    return new class() implements SubmissionExportJobQueue {
        /** @var list<int> */
        public array $advanced = [];

        public function enqueue(SubmissionExportRun $run): int
        {
            return 900 + $run->id;
        }

        public function advance(int $jobId): array
        {
            $this->advanced[] = $jobId;

            return ['state' => 'running', 'processed' => 100, 'total' => 250, 'error' => ''];
        }
    };
}

function exportActivity(): array
{
    $repository = new class() implements ActivityRepository {
        public array $events = [];

        public function append(ActivityEvent $event): ActivityEvent
        {
            $this->events[] = $event;

            return $event->withId(count($this->events));
        }

        public function find(int $id): ?ActivityEvent
        {
            return $this->events[$id - 1] ?? null;
        }

        public function query(array $filters = [], int $page = 1, int $perPage = 20): array
        {
            return $this->events;
        }

        public function pruneExpired(DateTimeImmutable $now, int $limit = 500): int
        {
            return 0;
        }
    };

    return [new ActivityService($repository), $repository];
}

function exportRecords(): array
{
    return [
        20 => ['id' => 20, 'owner_type' => 'team', 'owner_key' => 'sales', 'is_test' => false],
        21 => ['id' => 21, 'owner_type' => 'team', 'owner_key' => 'sales', 'is_test' => true],
        22 => ['id' => 22, 'owner_type' => 'team', 'owner_key' => 'legal', 'is_test' => false],
    ];
}

it('queues an exact selected export and excludes marked tests by default', function () {
    [$activity, $events] = exportActivity();
    $store = exportStore();
    $service = new SubmissionExportService(exportReader(exportRecords()), $store, exportQueue(), $activity);
    $scope = new SubmissionAccessScope(7, false, ['sales'], canExportPersonalData: true);
    $request = SubmissionExportRequest::from([
        'scope' => 'selected',
        'selected_ids' => [20],
        'columns' => ['submitted_fields', 'consent_snapshot'],
        'personal_data_acknowledged' => true,
    ]);

    $run = $service->request($scope, $request);

    expect($run->id)->toBe(1)
        ->and($run->jobId)->toBe(901)
        ->and($run->selectedIds)->toBe([20])
        ->and($run->includeTest)->toBeFalse()
        ->and($events->events)->toHaveCount(1)
        ->and($events->events[0]->kind)->toBe('submission.export.queued')
        ->and($events->events[0]->context)->toMatchArray(['scope' => 'selected', 'record_count' => 1]);
});

it('requires personal-data permission and explicit acknowledgement', function () {
    [$activity] = exportActivity();
    $service = new SubmissionExportService(exportReader(exportRecords()), exportStore(), exportQueue(), $activity);
    $request = SubmissionExportRequest::from([
        'scope' => 'selected',
        'selected_ids' => [20],
        'columns' => ['submitted_fields'],
    ]);

    expect(fn () => $service->request(new SubmissionAccessScope(7, true), $request))
        ->toThrow(DomainException::class, 'personal data')
        ->and(fn () => $service->request(
            new SubmissionAccessScope(7, true, canExportPersonalData: true),
            $request,
        ))->toThrow(DomainException::class, 'acknowledge');
});

it('rejects inaccessible or test records without leaking which selection member failed', function () {
    [$activity] = exportActivity();
    $service = new SubmissionExportService(exportReader(exportRecords()), exportStore(), exportQueue(), $activity);
    $scope = new SubmissionAccessScope(7, false, ['sales'], canExportPersonalData: true);

    foreach ([[20, 22], [20, 21]] as $ids) {
        $request = SubmissionExportRequest::from([
            'scope' => 'selected',
            'selected_ids' => $ids,
            'columns' => ['submitted_fields'],
            'personal_data_acknowledged' => true,
        ]);
        expect(fn () => $service->request($scope, $request))->toThrow(DomainException::class, 'unavailable');
    }
});

it('returns permission-scoped export history', function () {
    [$activity] = exportActivity();
    $store = exportStore();
    $service = new SubmissionExportService(exportReader(exportRecords()), $store, exportQueue(), $activity);
    $scope = new SubmissionAccessScope(7, true, canExportPersonalData: true);
    $request = SubmissionExportRequest::from([
        'scope' => 'accessible',
        'columns' => ['submitted_fields'],
        'personal_data_acknowledged' => true,
    ]);
    $service->request($scope, $request);

    expect($service->history($scope))->toHaveCount(1)
        ->and($service->history(new SubmissionAccessScope(8, false)))->toBe([]);
});

/**
 * A source of two forms' submissions, for the job that writes an export.
 *
 * @param array<int,array<string,mixed>> $records Keyed by submission ID.
 */
function submissionExportSource(array $records): SubmissionExportSource
{
    return new class($records) implements SubmissionExportSource {
        /** @var list<int> */
        public array $marked = [];

        public function __construct(private array $records)
        {
        }

        public function queryInbox(SubmissionInboxQuery $query, SubmissionAccessScope $scope): array
        {
            $items = array_values($this->records);

            return [
                'items' => array_slice($items, ($query->page - 1) * $query->perPage, $query->perPage),
                'total' => count($items),
            ];
        }

        public function findInbox(int $id, SubmissionAccessScope $scope): ?array
        {
            return $this->records[$id] ?? null;
        }

        public function markExported(array $submissionIds, string $exportedAt): void
        {
            $this->marked = [...$this->marked, ...$submissionIds];
        }
    };
}

function exportPolicy(SubmissionAccessScope $scope): SubmissionAccessPolicy
{
    return new class($scope) implements SubmissionAccessPolicy {
        public function __construct(private SubmissionAccessScope $scope)
        {
        }

        public function scopeFor(int $actorId): ?SubmissionAccessScope
        {
            return $actorId === $this->scope->actorId ? $this->scope : null;
        }
    };
}

/**
 * The real table, writers and working files, in a scratch directory, with questions for one form.
 */
function exportFiles(): SubmissionExportFiles
{
    $directory = new class implements ExportDirectory {
        private string $path = '';

        public function path(): string
        {
            if ($this->path === '') {
                $this->path = sys_get_temp_dir() . '/corex_export_job_' . uniqid('', true);
                mkdir($this->path);
            }

            return $this->path;
        }
    };
    $questions = new class implements SubmissionQuestions {
        public function for(string $form): array
        {
            return $form === 'contact'
                ? [['key' => 'name', 'label' => 'Your name', 'type' => 'text'], ['key' => 'message', 'label' => 'Message', 'type' => 'textarea']]
                : [];
        }
    };
    $owners = new class implements SubmissionOwnerNames {
        public function nameOf(string $ownerType, string $ownerKey): string
        {
            return '';
        }
    };

    return new SubmissionExportFiles(
        new SubmissionExportTable($owners, static fn (): DateTimeZone => new DateTimeZone('UTC')),
        $questions,
        new ExportWriters(static fn (): bool => false),
        $directory,
    );
}

/**
 * @param array<string,mixed> $overrides
 *
 * @return array<string,mixed>
 */
function contactSubmission(int $id, array $overrides = []): array
{
    return $overrides + [
        'id' => $id,
        'form' => 'contact',
        'flow' => 'Contact',
        'created_at' => '2026-07-04 09:00:00',
        'status' => 'new',
        'owner_type' => 'none',
        'owner_key' => '',
        'read_at' => null,
        'is_test' => false,
        'values' => ['name' => 'Salma', 'message' => 'Hello'],
    ];
}

/**
 * Runs an export's job to the end, a batch at a time, and returns the store it wrote to.
 *
 * @param array<string,mixed>            $request
 * @param array<int,array<string,mixed>> $records
 *
 * @return array{store:SubmissionExportStore,run:SubmissionExportRun,job:BoundedJob,source:SubmissionExportSource}
 */
function runExport(array $request, array $records, int $batchSize = 100): array
{
    Brain\Monkey\Functions\stubTranslationFunctions();
    Brain\Monkey\Functions\when('wp_json_encode')->alias('json_encode');
    Brain\Monkey\Functions\when('wp_delete_file')->alias('unlink');

    $scope  = new SubmissionAccessScope(7, true, canExportPersonalData: true);
    $source = submissionExportSource($records);
    $store  = exportStore();
    $total  = ($request['scope'] ?? '') === 'selected' ? count($request['selected_ids']) : count($records);
    $run    = $store->create(SubmissionExportRun::queued(7, SubmissionExportRequest::from($request), $total));
    $now    = new DateTimeImmutable('2026-07-04T12:00:00+00:00');
    $job    = BoundedJob::queued(SubmissionExportJobHandler::KIND, 7, $total, $run->inputHash, $now)->withId(9)->start($now);
    $handler = new SubmissionExportJobHandler($source, exportPolicy($scope), $store, exportFiles());

    for ($step = 0; $step < 50 && ! $job->terminal(); $step++) {
        $job = $handler->handle($job, $batchSize);
    }

    return ['store' => $store, 'run' => $run, 'job' => $job, 'source' => $source];
}

it('writes a selected export as a file with a column per answer, headed by the question', function () {
    $export = runExport(
        ['scope' => 'selected', 'selected_ids' => [20], 'columns' => ['identity', 'submitted_fields'], 'personal_data_acknowledged' => true],
        [20 => contactSubmission(20)],
    );
    $file = $export['store']->file($export['run']->id);

    expect($export['job']->state)->toBe(BoundedJob::STATE_COMPLETED)
        ->and($export['job']->resultArtifact)->toBe('submission-export:' . $export['run']->id)
        ->and($file)->toMatchArray(['extension' => 'csv', 'content_type' => 'text/csv; charset=utf-8', 'subject' => 'contact'])
        ->and(file_get_contents($file['path']))->toBe(
            "\xEF\xBB\xBF"
            . "ID,Submitted,Form,\"Your name\",Message\r\n"
            . "20,\"2026-07-04 09:00\",Contact,Salma,Hello\r\n",
        )
        ->and($export['source']->marked)->toBe([20]);
});

it('keeps an answer a spreadsheet would run as a formula from running', function () {
    $export = runExport(
        ['scope' => 'selected', 'selected_ids' => [20], 'columns' => ['submitted_fields'], 'personal_data_acknowledged' => true],
        [20 => contactSubmission(20, ['values' => ['name' => '=2+2', 'message' => 'Hello']])],
    );

    expect(file_get_contents($export['store']->file($export['run']->id)['path']))->toContain("\r\n'=2+2,Hello\r\n");
});

/**
 * The columns are not known until the last batch: a late submission may answer what no earlier
 * one did. The file is written once, from every batch.
 */
it('gathers an export over several batches and writes one file with every column', function () {
    $export = runExport(
        ['scope' => 'accessible', 'columns' => ['submitted_fields'], 'personal_data_acknowledged' => true],
        [
            20 => contactSubmission(20),
            21 => contactSubmission(21, ['values' => ['name' => 'Omar', 'message' => 'Hi']]),
            22 => contactSubmission(22, ['values' => ['name' => 'Mona', 'message' => 'Hey', 'company' => 'Acme']]),
        ],
        batchSize: 2,
    );

    expect($export['job']->state)->toBe(BoundedJob::STATE_COMPLETED)
        ->and(file_get_contents($export['store']->file($export['run']->id)['path']))->toBe(
            "\xEF\xBB\xBF"
            . "\"Your name\",Message,company\r\n"
            . "Salma,Hello,\r\n"
            . "Omar,Hi,\r\n"
            . "Mona,Hey,Acme\r\n",
        )
        ->and($export['source']->marked)->toBe([20, 21, 22]);
});

it('gives each form a file of its own when an export holds several', function () {
    $export = runExport(
        ['scope' => 'accessible', 'columns' => ['identity', 'submitted_fields'], 'personal_data_acknowledged' => true],
        [
            20 => contactSubmission(20),
            30 => contactSubmission(30, ['form' => 'careers', 'flow' => 'Careers', 'values' => ['role' => 'Designer']]),
        ],
    );
    $file = $export['store']->file($export['run']->id);

    $archive = new ZipArchive();
    $archive->open($file['path']);

    expect($file)->toMatchArray(['extension' => 'zip', 'subject' => 'submissions'])
        ->and($archive->getFromName('Contact.csv'))->toContain('"Your name",Message')
        // Nobody could say what this form asked, so its answer is headed by its key.
        ->and($archive->getFromName('Careers.csv'))->toContain("ID,Submitted,Form,role\r\n");

    $archive->close();
});

it('leaves no working file behind once the export is written', function () {
    $export = runExport(
        ['scope' => 'selected', 'selected_ids' => [20], 'columns' => ['identity'], 'personal_data_acknowledged' => false],
        [20 => contactSubmission(20)],
    );
    $directory = dirname($export['store']->file($export['run']->id)['path']);

    expect(glob($directory . '/*.ndjson'))->toBe([]);
});

it('treats the same request made twice as two exports', function () {
    $request = SubmissionExportRequest::from(['scope' => 'accessible', 'columns' => ['identity']]);

    expect(SubmissionExportRun::queued(7, $request, 3)->inputHash)
        ->not->toBe(SubmissionExportRun::queued(7, $request, 3)->inputHash);
});

it('refuses an export that would hold nothing', function () {
    [$activity] = exportActivity();
    $service = new SubmissionExportService(exportReader([]), exportStore(), exportQueue(), $activity);

    $service->request(
        new SubmissionAccessScope(7, true, canExportPersonalData: true),
        SubmissionExportRequest::from(['scope' => 'accessible', 'columns' => ['identity']]),
    );
})->throws(DomainException::class, 'There is nothing to export.');

it('hands back a finished export by the name of what it holds, to the person who made it', function () {
    [$activity] = exportActivity();
    $store = exportStore();
    $service = new SubmissionExportService(exportReader(exportRecords()), $store, exportQueue(), $activity);
    $scope = new SubmissionAccessScope(7, true, canExportPersonalData: true);
    $run = $service->request($scope, SubmissionExportRequest::from(['scope' => 'accessible', 'columns' => ['identity']]));
    $store->saveFile($run->id, ['path' => '/tmp/x.csv', 'extension' => 'csv', 'content_type' => 'text/csv; charset=utf-8', 'subject' => 'contact'], 2);

    expect($service->download($scope, $run->id))->toBe([
        'name' => 'contact-' . $run->createdAt->format('Y-m-d') . '.csv',
        'content_type' => 'text/csv; charset=utf-8',
        'path' => '/tmp/x.csv',
        'csv' => null,
    ]);

    $service->download(new SubmissionAccessScope(8, false), $run->id);
})->throws(DomainException::class, 'The submission export is unavailable.');

it('still hands back an export made before files were kept on disk', function () {
    [$activity] = exportActivity();
    $store = exportStore();
    $service = new SubmissionExportService(exportReader(exportRecords()), $store, exportQueue(), $activity);
    $scope = new SubmissionAccessScope(7, true, canExportPersonalData: true);
    $run = $service->request($scope, SubmissionExportRequest::from(['scope' => 'accessible', 'columns' => ['identity']]));
    $store->artifacts[$run->id] = "identity\n1\n";

    expect($service->download($scope, $run->id))->toMatchArray(['path' => null, 'csv' => "identity\n1\n"]);
});

/**
 * A request used to name groups of data. It now names columns, and a group still means what it
 * meant: an export made from a saved link or an older screen is the same export.
 */
it('reads the columns asked for, and a group as the columns it stands for', function (array $asked, array $columns, bool $personal) {
    $request = SubmissionExportRequest::from(['scope' => 'accessible', 'columns' => $asked]);

    expect($request->columns)->toBe($columns)
        ->and($request->includesPersonalData())->toBe($personal);
})->with([
    'the three groups the screen sends' => [
        ['identity', 'workflow', 'submitted_fields'],
        ['id', 'submitted', 'form', 'status', 'assigned_to', 'read', 'test', 'answers'],
        true,
    ],
    'columns by name, in the order asked' => [['status', 'id'], ['status', 'id'], false],
    'one answer' => [['id', 'answer:email'], ['id', 'answer:email'], true],
    'a column asked for twice' => [['id', 'identity'], ['id', 'submitted', 'form'], false],
    'campaign data' => [['utm'], ['utm'], true],
]);

it('refuses a column, a format or a separator it does not know', function (array $input) {
    SubmissionExportRequest::from($input + ['scope' => 'accessible', 'columns' => ['identity']]);
})->throws(InvalidArgumentException::class)->with([
    'an unknown column' => [['columns' => ['passwords']]],
    'no column' => [['columns' => []]],
    'an answer key with a path in it' => [['columns' => ['answer:../x']]],
    'an unknown format' => [['format' => 'docx']],
    'an unknown separator' => [['separator' => 'pipe']],
]);

/**
 * The dialog shows these before anything is exported, so a person knows what each choice means
 * (spec 103, FR-010). They are counted the way the export itself would count.
 */
it('counts what each scope would export, before it is exported', function () {
    [$activity] = exportActivity();
    $service = new SubmissionExportService(exportReader(exportRecords()), exportStore(), exportQueue(), $activity);

    // 20 and 22 are real, 21 is a marked test. A member of the sales team sees 20 and 21.
    expect($service->preview(new SubmissionAccessScope(7, true), [20, 21, 22, 99], [], false))
        ->toBe(['selected' => 2, 'filtered' => 2, 'accessible' => 2])
        ->and($service->preview(new SubmissionAccessScope(7, true), [20, 21, 22, 99], [], true))
        ->toBe(['selected' => 3, 'filtered' => 3, 'accessible' => 3])
        ->and($service->preview(new SubmissionAccessScope(7, false, ['sales']), [20, 21, 22], [], false))
        ->toBe(['selected' => 1, 'filtered' => 1, 'accessible' => 1]);
});

it('takes a step of an export for the person who made it, and for nobody else', function () {
    [$activity] = exportActivity();
    $queue = exportQueue();
    $service = new SubmissionExportService(exportReader(exportRecords()), exportStore(), $queue, $activity);
    $scope = new SubmissionAccessScope(7, true, canExportPersonalData: true);
    $run = $service->request($scope, SubmissionExportRequest::from(['scope' => 'accessible', 'columns' => ['identity']]));

    expect($service->advance($scope, $run->id))->toBe(['state' => 'running', 'processed' => 100, 'total' => 250, 'error' => ''])
        ->and($queue->advanced)->toBe([$run->jobId]);

    $service->advance(new SubmissionAccessScope(8, false), $run->id);
})->throws(DomainException::class, 'The submission export is unavailable.');

it('writes an export as a workbook, a sheet per form, when Excel is asked for', function () {
    $export = runExport(
        ['scope' => 'accessible', 'columns' => ['identity', 'submitted_fields'], 'personal_data_acknowledged' => true, 'format' => 'xlsx'],
        [
            20 => contactSubmission(20),
            30 => contactSubmission(30, ['form' => 'careers', 'flow' => 'Careers', 'values' => ['role' => 'Designer']]),
        ],
    );
    $file = $export['store']->file($export['run']->id);

    $archive = new ZipArchive();
    $archive->open($file['path']);
    $workbook = (string) $archive->getFromName('xl/workbook.xml');
    $contact  = (string) $archive->getFromName('xl/worksheets/sheet1.xml');

    expect($file)->toMatchArray([
        'extension' => 'xlsx',
        'content_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'subject' => 'submissions',
    ])
        ->and($workbook)->toContain('name="Contact"')
        ->and($workbook)->toContain('name="Careers"')
        ->and($contact)->toContain('Your name')
        ->and($contact)->toContain('Salma');

    $archive->close();
});
