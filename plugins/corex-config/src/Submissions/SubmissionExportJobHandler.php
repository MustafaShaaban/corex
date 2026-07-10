<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Corex\Jobs\BoundedJob;
use Corex\Jobs\JobHandler;
use DateTimeImmutable;
use DomainException;

/**
 * Resumable CSV export handler. Each invocation processes at most the runner batch size.
 */
final readonly class SubmissionExportJobHandler implements JobHandler
{
    public const KIND = 'submissions.export';

    public function __construct(
        private SubmissionExportSource $submissions,
        private SubmissionAccessPolicy $access,
        private SubmissionExportStore $exports,
        private SubmissionExportCsvWriter $csv,
    ) {
    }

    public function kind(): string
    {
        return self::KIND;
    }

    public function handle(BoundedJob $job, int $batchSize): BoundedJob
    {
        $run = $this->exports->findByHash($job->inputHash);
        $scope = $this->access->scopeFor($job->actorId);
        if ($run === null || $scope === null) {
            throw new DomainException('The submission export or actor scope is unavailable.');
        }
        $remaining = max(0, $job->total - $job->processed);
        $records = $remaining === 0
            ? []
            : array_slice($this->records($run, $scope, $job->processed, $batchSize), 0, $remaining);
        $processed = $job->processed + count($records);
        if ($records === [] && $processed < $job->total) {
            throw new DomainException('The submission export scope changed before completion.');
        }
        $chunk = $this->csv->write($records, $run->columns, $job->processed === 0);
        $artifact = ($this->exports->artifact($run->id) ?? '') . $chunk;
        $this->exports->saveArtifact($run->id, $artifact, $processed);
        $this->submissions->markExported(array_column($records, 'id'), gmdate(DATE_ATOM));
        $advanced = $job->advance((string) $processed, $processed, $processed, 0, null, new DateTimeImmutable('now'));

        return $processed === $job->total
            ? $advanced->complete('submission-export:' . $run->id, new DateTimeImmutable('now'))
            : $advanced;
    }

    /** @return list<array<string,mixed>> */
    private function records(
        SubmissionExportRun $run,
        SubmissionAccessScope $scope,
        int $offset,
        int $batchSize,
    ): array {
        if ($run->scope === 'selected') {
            $ids = array_slice($run->selectedIds, $offset, $batchSize);

            return array_values(array_filter(array_map(
                fn (int $id): ?array => $this->submissions->findInbox($id, $scope),
                $ids,
            )));
        }

        $input = $run->scope === 'filtered' ? $run->query : [];
        $input['include_test'] = $run->includeTest;
        $input['page'] = intdiv($offset, $batchSize) + 1;
        $input['per_page'] = $batchSize;

        return $this->submissions->queryInbox(SubmissionInboxQuery::from($input), $scope)['items'];
    }
}
