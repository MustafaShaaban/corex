<?php

/**
 * What a document says about an export before its records (spec 103, FR-019): what was exported
 * and under which filters, how many, and who exported it and when.
 *
 * @package Corex\Tests\Unit\Submissions
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\DataModels\DataExportAbout;
use Corex\Config\DataModels\DataExportRequest;
use Corex\Config\DataModels\DataExportRun;
use Corex\Config\Submissions\SubmissionExportAbout;
use Corex\Config\Submissions\SubmissionExportRequest;
use Corex\Config\Submissions\SubmissionExportRun;
use Corex\Config\Submissions\SubmissionOwnerNames;
use Corex\Data\DataField;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('_n')->alias(static fn (string $one, string $many, int $count): string => $count === 1 ? $one : $many);
});

function submissionsAbout(): SubmissionExportAbout
{
    $names = new class implements SubmissionOwnerNames {
        public function nameOf(string $ownerType, string $ownerKey): string
        {
            return $ownerType === 'user' && $ownerKey === '7' ? 'Salma Adel' : '';
        }

        public function people(): array
        {
            return [];
        }
    };

    return new SubmissionExportAbout($names, static fn (): DateTimeZone => new DateTimeZone('Africa/Cairo'));
}

/** An export asked for by `$actor` at half past twelve, UTC, on 8 October 2026. */
function aboutRun(array $request, int $actor = 7, int $records = 12): SubmissionExportRun
{
    $queued = SubmissionExportRun::queued($actor, SubmissionExportRequest::from($request + ['columns' => ['id']]), $records);

    return SubmissionExportRun::from(['created_at' => '2026-10-08T12:30:00+00:00'] + $queued->toArray());
}

/** What the document says under one label. */
function factUnder(array $facts, string $label): string
{
    return (string) (array_column($facts, 1, 0)[$label] ?? '');
}

it('titles a document of one form by the form, and of several as submissions', function () {
    expect(submissionsAbout()->title(['contact' => 'Contact']))->toBe('Contact submissions')
        ->and(submissionsAbout()->title(['contact' => 'Contact', 'lead' => 'Lead form']))->toBe('Submissions')
        ->and(submissionsAbout()->title([]))->toBe('Submissions');
});

it('says what was exported, in words', function (array $request, string $words) {
    $facts = submissionsAbout()->facts(aboutRun($request), ['contact' => 'Contact']);

    expect(factUnder($facts, 'What was exported'))->toBe($words);
})->with([
    'everything' => [['scope' => 'accessible'], 'Every submission the person exporting could see'],
    'ticked rows' => [['scope' => 'selected', 'selected_ids' => [4, 9, 12]], '3 selected submissions'],
    'one ticked row' => [['scope' => 'selected', 'selected_ids' => [4]], '1 selected submission'],
    'the filters it was made under' => [
        ['scope' => 'filtered', 'query' => ['flow' => 'slug:contact', 'status' => 'in_progress', 'date_from' => '2026-10-01', 'date_to' => '2026-10-07', 'search' => 'salma']],
        'Form: Contact; Status: In progress; 2026-10-01 to 2026-10-07; Search: salma',
    ],
    'filters, when none was on' => [['scope' => 'filtered', 'query' => []], 'Every submission in view, with no filter on'],
    'tests, when they were included' => [
        ['scope' => 'accessible', 'include_test' => true],
        'Every submission the person exporting could see, with submissions marked as tests',
    ],
]);

it('says how many records, and who exported them and when, in the site’s time', function () {
    $facts = submissionsAbout()->facts(aboutRun(['scope' => 'accessible']), []);

    expect(factUnder($facts, 'Records'))->toBe('12')
        // Half past twelve UTC is half past three in Cairo.
        ->and(factUnder($facts, 'Exported by'))->toBe('Salma Adel, 2026-10-08 15:30');
});

it('gives the time alone when the person can no longer be named', function () {
    $facts = submissionsAbout()->facts(aboutRun(['scope' => 'accessible'], actor: 99), []);

    expect(factUnder($facts, 'Exported by'))->toBe('2026-10-08 15:30');
});

it('says the same of a Data export, with a filter under its field’s label', function (array $request, string $words) {
    $about = new DataExportAbout(
        static fn (int $userId): string => $userId === 7 ? 'Salma Adel' : '',
        static fn (): DateTimeZone => new DateTimeZone('UTC'),
    );
    $queued = DataExportRun::queued(DataExportRequest::from($request + [
        'actor_id' => 7, 'source_key' => 'contacts', 'columns' => ['name'], 'selected_ids' => [], 'query' => [],
    ]), 3, []);
    $run = DataExportRun::from(['created_at' => '2026-10-08T12:30:00+00:00'] + $queued->toArray());
    $facts = $about->facts($run, [
        new DataField('status', 'Status', DataField::TYPE_TEXT, false, true, true, [], true, DataField::PERSONAL_NONE, [], []),
    ]);

    expect(factUnder($facts, 'What was exported'))->toBe($words)
        ->and(factUnder($facts, 'Records'))->toBe('3')
        ->and(factUnder($facts, 'Exported by'))->toBe('Salma Adel, 2026-10-08 12:30');
})->with([
    'everything' => [['scope' => 'all'], 'Every record the person exporting could see'],
    'ticked rows' => [['scope' => 'selected', 'selected_ids' => [1, 2]], '2 selected records'],
    'filters' => [
        ['scope' => 'filtered', 'query' => ['search' => 'ada', 'filters' => ['status' => 'active', 'region' => 'north']]],
        'Search: ada; Status: active; region: north',
    ],
    'filters, when none was on' => [['scope' => 'filtered'], 'Every record in view, with no filter on'],
]);
