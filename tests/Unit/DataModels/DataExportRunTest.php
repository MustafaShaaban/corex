<?php

/**
 * An export run holds a part for each model it covers (spec 109, slice 2, D4).
 *
 * A run was one source and its columns. Several models in one export are several parts of one
 * run; a run stored before this is read as a run of one part, which is what it was.
 *
 * @package Corex\Tests\Unit\DataModels
 */

declare(strict_types=1);

use Corex\Config\DataModels\DataExportPart;
use Corex\Config\DataModels\DataExportRun;
use Corex\Config\DataModels\DataExportSetRequest;

/** A run as the store held it before a run had parts. */
function runStoredBeforeParts(): array
{
    return [
        'id' => 9, 'actor_id' => 7, 'job_id' => 52, 'source_key' => 'contacts', 'scope' => 'filtered',
        'selected_ids' => [], 'query' => ['filters' => ['status' => 'active']], 'columns' => ['name', 'status'],
        'format' => 'csv', 'separator' => 'comma', 'personal_data_classes' => ['identity'], 'record_count' => 2,
        'exported_rows' => 2, 'state' => 'completed', 'input_hash' => str_repeat('a', 64),
        'created_at' => '2026-10-07T09:30:00+00:00',
    ];
}

function severalModels(array $changes = []): DataExportSetRequest
{
    return DataExportSetRequest::from($changes + [
        'actor_id' => 7,
        'parts' => [['source' => 'contacts'], ['source' => 'table-invoices', 'columns' => ['number']]],
        'format' => 'xlsx',
        'personal_data_acknowledged' => true,
    ]);
}

it('reads a run stored before runs had parts as a run of one part', function () {
    $run = DataExportRun::from(runStoredBeforeParts());

    expect($run->isSeveral())->toBeFalse()
        ->and($run->parts)->toHaveCount(1)
        ->and($run->parts[0]->sourceKey)->toBe('contacts')
        ->and($run->parts[0]->columns)->toBe(['name', 'status'])
        ->and($run->parts[0]->recordCount)->toBe(2)
        ->and($run->scope)->toBe('filtered')
        ->and($run->holds('contacts'))->toBeTrue()
        ->and($run->holds('table-invoices'))->toBeFalse();
});

it('holds a part for each model, and totals what they hold between them', function () {
    $run = DataExportRun::queuedSeveral(severalModels(), [
        new DataExportPart('contacts', ['name', 'email'], 3, ['contact', 'identity']),
        new DataExportPart('table-invoices', ['number'], 40, ['identity']),
    ]);
    $stored = DataExportRun::from($run->toArray());

    expect($stored->isSeveral())->toBeTrue()
        ->and($stored->sourceKeys())->toBe(['contacts', 'table-invoices'])
        ->and($stored->recordCount)->toBe(43)
        ->and($stored->personalDataClasses)->toBe(['contact', 'identity'])
        ->and($stored->scope)->toBe('all')
        ->and($stored->holds('table-invoices'))->toBeTrue()
        ->and($stored->parts[1]->columns)->toBe(['number']);
});

it('says which model was left out of a run, and stops counting its records', function () {
    $run = DataExportRun::queuedSeveral(severalModels(), [
        new DataExportPart('contacts', ['name'], 3, []),
        new DataExportPart('table-invoices', ['number'], 40, []),
    ])->leavingOut('table-invoices');

    expect($run->parts[1]->leftOut)->toBeTrue()
        ->and($run->parts[0]->leftOut)->toBeFalse()
        ->and($run->recordCount)->toBe(3)
        // It is still a run that covered the model: its history says so.
        ->and($run->holds('table-invoices'))->toBeTrue();
});

it('keeps how large its file was and what became of it', function () {
    $made = DataExportRun::from(runStoredBeforeParts())->withFileOf(2048);
    $deleted = $made->withoutFile(DataExportRun::REMOVED_DELETED, 4, new DateTimeImmutable('2026-10-08T10:00:00+00:00'));
    $stored = DataExportRun::from($deleted->toArray());

    expect($made->fileBytes)->toBe(2048)
        ->and($made->removedReason)->toBe('')
        ->and($stored->fileBytes)->toBe(2048)
        ->and($stored->removedReason)->toBe('deleted')
        ->and($stored->removedBy)->toBe(4)
        ->and($stored->removedAt?->format(DATE_ATOM))->toBe('2026-10-08T10:00:00+00:00');
});

it('refuses a request for several models that names none, one twice, or a format nothing writes', function (array $changes, string $reason) {
    expect(fn () => severalModels($changes))->toThrow(InvalidArgumentException::class, $reason);
})->with([
    'no model' => [['parts' => []], 'model'],
    'one model twice' => [['parts' => [['source' => 'contacts'], ['source' => 'contacts']]], 'twice'],
    'a model with no key' => [['parts' => [['source' => 'Not A Key']]], 'model'],
    'a format nothing writes' => [['format' => 'docx'], 'format'],
]);

it('takes every column of a model that names none, and only the named ones of a model that does', function () {
    $request = severalModels();

    expect($request->parts[0])->toBe(['source' => 'contacts', 'columns' => null])
        ->and($request->parts[1])->toBe(['source' => 'table-invoices', 'columns' => ['number']]);
});
