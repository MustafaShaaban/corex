<?php

/**
 * A run on CoreX → Data is read as it was recorded.
 *
 * WordPress takes one level of slashes off a meta value on its way in, and the four stores of
 * runs handed a run over as it was. An import keeps the rows of its file in its run and commits
 * them from there, so a row saying `C:\offers\new` was imported as `C:offersnew`; a migration
 * is run from the plan in its run; an export keeps the search it was asked for. A run is one
 * value, written again whole when a job is attached to it and when it ends, so each of those
 * took one more level (measured 2026-10-13, DECISIONS #332).
 *
 * On real WordPress, through each store.
 *
 * @package Corex\Tests\Integration\DataModels
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\DataModels\DataExportRun;
use Corex\Config\DataModels\DataImportRun;
use Corex\Config\DataModels\MigrationRun;
use Corex\Config\DataModels\WpDataExportStore;
use Corex\Config\DataModels\WpDataImportStore;
use Corex\Config\DataModels\WpMigrationRunStore;
use Corex\Config\Submissions\SubmissionExportRun;
use Corex\Config\Submissions\WpSubmissionExportStore;
use Corex\Tests\Support\BackslashedText;
use Corex\Tests\Support\CreatedPosts;

/** A checksum no other run has, so that no store answers with a run it already holds. */
function keptRunHash(): string
{
    return hash('sha256', 'kept-run-' . wp_generate_password(20, false));
}

beforeEach(function () {
    $this->container = Boot::app()->container();
    $this->runs = CreatedPosts::watch(
        WpDataImportStore::POST_TYPE,
        WpDataExportStore::POST_TYPE,
        WpMigrationRunStore::POST_TYPE,
        WpSubmissionExportStore::POST_TYPE,
    );
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    $this->actorId = (int) ($administrators[0] ?? 0);
});

afterEach(function () {
    $this->runs->delete();
});

it('commits the rows of an imported file as the file had them', function (string $text) {
    $imports = $this->container->make(WpDataImportStore::class);
    $recorded = [
        'header' => ['Name', $text],
        'rows' => [['Salma', $text]],
        'mapping' => [$text => 'note'],
        'accepted_rows' => [['name' => 'Salma', 'note' => $text, 'visits' => 3, 'active' => true, 'left' => null]],
        'rejected_rows' => [['line' => 3, 'reason' => 'A note is required.', 'row' => ['', $text]]],
    ];

    $run = $imports->create(DataImportRun::from($recorded + [
        'actor_id' => $this->actorId,
        'source_key' => 'kept-notes',
        'state' => DataImportRun::STATE_VALID,
        'input_hash' => keptRunHash(),
    ]));
    // The run is written again whole when its job is attached and when the import ends.
    $imports->attachJob($run->id, 41);
    $imports->finish($run->id, 1, 1);
    $read = $imports->find($run->id);

    expect($read->header)->toBe($recorded['header'])
        ->and($read->sourceRows)->toBe($recorded['rows'])
        ->and($read->mapping)->toBe($recorded['mapping'])
        // What the job hands to the model, row by row.
        ->and($read->acceptedRows)->toBe($recorded['accepted_rows'])
        ->and($read->rejectedRows)->toBe($recorded['rejected_rows'])
        ->and($read->jobId)->toBe(41)
        ->and($read->committedRows)->toBe(1);
})->with(BackslashedText::cases());

it('keeps the search a Data export was asked for', function (string $text) {
    $exports = $this->container->make(WpDataExportStore::class);
    $query = ['search' => $text, 'filters' => ['status' => 'active', 'note' => $text], 'page' => 2];

    $run = $exports->create(DataExportRun::from([
        'actor_id' => $this->actorId, 'source_key' => 'contacts', 'scope' => 'filtered', 'columns' => ['name'],
        'query' => $query, 'format' => 'csv', 'record_count' => 3, 'state' => 'queued', 'input_hash' => keptRunHash(),
    ]));
    $exports->attachJob($run->id, 41);
    $exports->finish($run->id, 3);
    $read = $exports->find($run->id);

    expect($read->query)->toBe($query)
        ->and($read->exportedRows)->toBe(3)
        ->and($exports->findByHash($run->inputHash)?->id)->toBe($run->id);
})->with(BackslashedText::cases());

it('keeps the plan a migration is run from', function (string $text) {
    $migrations = $this->container->make(WpMigrationRunStore::class);
    $definition = [
        'key' => 'kept.default_note',
        'version' => '2026.10.1',
        'description' => $text,
        'plan' => ['ALTER TABLE {table} ALTER note SET DEFAULT \'' . addslashes($text) . '\''],
        'transactional' => false,
        'rollback_supported' => true,
    ];

    $run = $migrations->create(MigrationRun::from([
        'actor_id' => $this->actorId, 'action' => MigrationRun::ACTION_APPLY, 'source_key' => 'kept-notes',
        'definition' => $definition, 'snapshot_id' => 'kept-snapshot', 'state' => MigrationRun::STATE_QUEUED,
        'input_hash' => keptRunHash(),
    ]));
    $migrations->attachJob($run->id, 41);
    $migrations->finish($run->id, MigrationRun::STATE_FAILED, $text);
    $read = $migrations->find($run->id);

    expect($read->definition->toArray())->toBe($definition)
        ->and($read->message)->toBe($text);
})->with(BackslashedText::cases());

it('keeps the search a submissions export was asked for', function (string $text) {
    $exports = $this->container->make(WpSubmissionExportStore::class);
    $query = ['search' => $text, 'status' => 'new', 'flow' => 44];

    $run = $exports->create(SubmissionExportRun::from([
        'actor_id' => $this->actorId, 'scope' => 'filtered', 'columns' => ['submitter_name'], 'query' => $query,
        'include_test' => true, 'record_count' => 3, 'input_hash' => keptRunHash(),
    ]));
    $exports->attachJob($run->id, 41);
    $read = $exports->find($run->id);

    expect($read->query)->toBe($query)
        ->and($read->includeTest)->toBeTrue()
        ->and($read->jobId)->toBe(41);
})->with(BackslashedText::cases());
