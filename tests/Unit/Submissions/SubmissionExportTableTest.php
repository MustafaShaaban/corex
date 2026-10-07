<?php

/**
 * Unit tests for the table an export is written from (spec 103, US1: FR-001 to FR-008).
 *
 * The export wrote one column per group of data and put the whole group, encoded, in one cell. A
 * lead's five answers arrived as a single cell of punctuation. This is the model that replaces it:
 * one row per submission, one column per answer, headed by the question the form asked.
 *
 * @package Corex\Tests\Unit\Submissions
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Export\ExportCell;
use Corex\Config\Export\ExportSheet;
use Corex\Config\Submissions\SubmissionExportTable;
use Corex\Config\Submissions\SubmissionOwnerNames;

const EXPORT_TABLE_QUESTIONS = [
    ['key' => 'name', 'label' => 'Name', 'type' => 'text'],
    ['key' => 'email', 'label' => 'Email', 'type' => 'email'],
    ['key' => 'looking_for', 'label' => 'What are you looking for?', 'type' => 'select'],
];

/**
 * @param array<string,mixed> $overrides
 *
 * @return array<string,mixed>
 */
function exportedLead(array $overrides = []): array
{
    return $overrides + [
        'id' => 41,
        'form' => 'lead',
        'flow' => 'Lead form',
        'created_at' => '2026-10-07 09:30:00',
        'status' => 'new',
        'owner_type' => 'none',
        'owner_key' => '',
        'read_at' => null,
        'is_test' => false,
        'values' => ['name' => 'Salma', 'email' => 'salma@example.com', 'looking_for' => 'Branding'],
        'hidden_metadata' => [],
        'utm' => [],
        'consent_snapshot' => [],
        'notes' => [],
    ];
}

function exportTable(string $timezone = 'UTC'): SubmissionExportTable
{
    $owners = new class implements SubmissionOwnerNames {
        public function nameOf(string $ownerType, string $ownerKey): string
        {
            return $ownerType === 'user' && $ownerKey === '7' ? 'Mona Adel' : '';
        }

        public function people(): array
        {
            return [];
        }
    };

    return new SubmissionExportTable($owners, static fn (): DateTimeZone => new DateTimeZone($timezone));
}

/**
 * @param list<array<string,mixed>> $records
 * @param list<string>              $columns
 *
 * @return array{headings:list<string>,rows:list<list<string>>,cells:list<list<ExportCell>>}
 */
function readSheet(array $records, array $columns = SubmissionExportTable::DEFAULT_COLUMNS, array $questions = EXPORT_TABLE_QUESTIONS, string $timezone = 'UTC'): array
{
    $sheet = exportTable($timezone)->sheet('Lead form', $records, $questions, $columns);
    $cells = [];
    foreach ($sheet->rows as $row) {
        $cells[] = $row;
    }

    return [
        'headings' => $sheet->headings,
        'rows' => array_map(static fn (array $row): array => array_map(static fn (ExportCell $cell): string => $cell->asText(), $row), $cells),
        'cells' => $cells,
    ];
}

beforeEach(function () {
    Functions\stubTranslationFunctions();
});

it('writes one row per submission and one column per question, headed by the question', function () {
    $sheet = readSheet([
        exportedLead(),
        exportedLead(['id' => 42, 'values' => ['name' => 'Omar', 'email' => 'omar@example.com', 'looking_for' => 'A website']]),
    ]);

    expect($sheet['headings'])->toBe([
        'ID', 'Submitted', 'Form', 'Status', 'Assigned to', 'Read', 'Test',
        'Name', 'Email', 'What are you looking for?',
    ])
        ->and($sheet['rows'])->toHaveCount(2)
        ->and(array_slice($sheet['rows'][0], 7))->toBe(['Salma', 'salma@example.com', 'Branding'])
        ->and(array_slice($sheet['rows'][1], 7))->toBe(['Omar', 'omar@example.com', 'A website']);
});

it('keeps every answer in its own column when one is missing', function () {
    $sheet = readSheet([exportedLead(['values' => ['name' => 'Salma', 'looking_for' => 'Branding']])]);

    expect(array_slice($sheet['rows'][0], 7))->toBe(['Salma', '', 'Branding']);
});

it('says the fixed columns in words a person reads', function (array $record, array $expected) {
    $sheet = readSheet([exportedLead($record)]);

    expect(array_slice($sheet['rows'][0], 0, 7))->toBe($expected);
})->with([
    'an unread, unassigned, real submission' => [
        [],
        ['41', '2026-10-07 09:30', 'Lead form', 'New', 'Unassigned', 'No', 'No'],
    ],
    'one assigned to a person, read, in progress' => [
        ['owner_type' => 'user', 'owner_key' => '7', 'read_at' => '2026-10-07T10:00:00+00:00', 'status' => 'in_progress'],
        ['41', '2026-10-07 09:30', 'Lead form', 'In progress', 'Mona Adel', 'Yes', 'No'],
    ],
    'an owner nobody can name falls back to what is stored' => [
        ['owner_type' => 'team', 'owner_key' => 'sales'],
        ['41', '2026-10-07 09:30', 'Lead form', 'New', 'team: sales', 'No', 'No'],
    ],
    'one routed to the form’s owner, who has no key' => [
        ['owner_type' => 'flow_owner', 'owner_key' => ''],
        ['41', '2026-10-07 09:30', 'Lead form', 'New', 'The form’s owner', 'No', 'No'],
    ],
    'a marked test' => [
        ['is_test' => true],
        ['41', '2026-10-07 09:30', 'Lead form', 'New', 'Unassigned', 'No', 'Yes'],
    ],
]);

it('gives the time a submission arrived in the site’s timezone, as a date a spreadsheet can sort', function () {
    $sheet = readSheet([exportedLead()], SubmissionExportTable::DEFAULT_COLUMNS, EXPORT_TABLE_QUESTIONS, 'Africa/Cairo');
    $submitted = $sheet['cells'][0][1];

    expect($submitted->type)->toBe(ExportCell::DATETIME)
        ->and($submitted->asText())->toBe('2026-10-07 12:30');
});

it('writes an answer with several values as those values, in words', function (mixed $answer, string $cell) {
    $sheet = readSheet([exportedLead(['values' => ['name' => 'Salma', 'email' => 'salma@example.com', 'looking_for' => $answer]])]);

    expect($sheet['rows'][0][9])->toBe($cell);
})->with([
    'several choices' => [['Branding', 'A website'], 'Branding; A website'],
    'a ticked box' => [true, 'Yes'],
    'an unticked box' => [false, 'No'],
    'an uploaded file' => [['name' => 'brief.pdf', 'size' => 1200, 'type' => 'application/pdf'], 'brief.pdf'],
    'two uploaded files' => [[['name' => 'a.pdf'], ['name' => 'b.pdf']], 'a.pdf; b.pdf'],
    'nothing' => [null, ''],
]);

it('carries a numeric answer as a number when the question is a number', function () {
    $questions = [['key' => 'budget', 'label' => 'Budget', 'type' => 'number']];
    $sheet = readSheet([exportedLead(['values' => ['budget' => '2500']])], ['answers'], $questions);

    expect($sheet['cells'][0][0]->type)->toBe(ExportCell::NUMBER)
        ->and($sheet['cells'][0][0]->value)->toBe(2500);
});

it('keeps a phone number that is all digits as text', function () {
    $questions = [['key' => 'phone', 'label' => 'Phone', 'type' => 'tel']];
    $sheet = readSheet([exportedLead(['values' => ['phone' => '01016999700']])], ['answers'], $questions);

    expect($sheet['cells'][0][0]->type)->toBe(ExportCell::TEXT)
        ->and($sheet['rows'][0][0])->toBe('01016999700');
});

it('still exports an answer the form no longer asks for, headed by its key', function () {
    $sheet = readSheet([exportedLead(['values' => ['name' => 'Salma', 'old_field' => 'kept']])], ['answers']);

    expect($sheet['headings'])->toBe(['Name', 'Email', 'What are you looking for?', 'old_field'])
        ->and($sheet['rows'][0])->toBe(['Salma', '', '', 'kept']);
});

it('heads answers by their keys when the form’s questions are not available', function () {
    $sheet = readSheet([exportedLead()], ['answers'], []);

    expect($sheet['headings'])->toBe(['name', 'email', 'looking_for']);
});

it('tells two questions with the same wording apart', function () {
    $questions = [
        ['key' => 'phone', 'label' => 'Phone', 'type' => 'tel'],
        ['key' => 'phone_2', 'label' => 'Phone', 'type' => 'tel'],
    ];
    $sheet = readSheet([exportedLead(['values' => ['phone' => '1', 'phone_2' => '2']])], ['answers'], $questions);

    expect($sheet['headings'])->toBe(['Phone', 'Phone (phone_2)']);
});

it('includes only the columns asked for, in the order asked', function () {
    $sheet = readSheet([exportedLead()], ['answer:email', 'id', 'status']);

    expect($sheet['headings'])->toBe(['Email', 'ID', 'Status'])
        ->and($sheet['rows'][0])->toBe(['salma@example.com', '41', 'New']);
});

it('gives each value of an optional group a column of its own', function () {
    $sheet = readSheet(
        [
            exportedLead(['utm' => ['source' => 'newsletter', 'campaign' => 'autumn']]),
            exportedLead(['id' => 42, 'utm' => ['source' => 'search']]),
        ],
        ['id', 'utm'],
    );

    expect($sheet['headings'])->toBe(['ID', 'Campaign: source', 'Campaign: campaign'])
        ->and($sheet['rows'])->toBe([['41', 'newsletter', 'autumn'], ['42', 'search', '']]);
});

it('writes a submission’s notes into one cell, one per line', function () {
    $sheet = readSheet(
        [exportedLead(['notes' => [
            ['body' => 'Called back.', 'created_at' => '2026-10-07T10:00:00+00:00'],
            ['body' => 'Sent the brief.', 'created_at' => '2026-10-07T11:00:00+00:00'],
        ]])],
        ['notes'],
    );

    expect($sheet['headings'])->toBe(['Notes'])
        ->and($sheet['rows'][0])->toBe(["Called back.\nSent the brief."]);
});

it('names the sheet for the form', function () {
    expect(exportTable()->sheet('Lead form', [exportedLead()], EXPORT_TABLE_QUESTIONS, ['id']))
        ->toBeInstanceOf(ExportSheet::class)
        ->name->toBe('Lead form');
});
