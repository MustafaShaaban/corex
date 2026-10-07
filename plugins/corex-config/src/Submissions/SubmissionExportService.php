<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;
use DateInterval;
use DateTimeImmutable;
use DomainException;

/**
 * Permission-scoped, acknowledged, audited submission export orchestration: asking for an export
 * and moving it along. What was exported before is {@see SubmissionExportHistory}.
 */
final readonly class SubmissionExportService
{
    public function __construct(
        private SubmissionInboxReader $submissions,
        private SubmissionExportStore $exports,
        private SubmissionExportJobQueue $jobs,
        private ActivityService $activity,
    ) {
    }

    public function request(SubmissionAccessScope $scope, SubmissionExportRequest $request): SubmissionExportRun
    {
        $this->assertPersonalData($scope, $request);
        $recordCount = $request->scope === 'selected'
            ? $this->validateSelection($scope, $request)
            : $this->countQuery($scope, $request);
        if ($recordCount === 0) {
            throw new DomainException('There is nothing to export.');
        }
        $run = $this->exports->create(SubmissionExportRun::queued($scope->actorId, $request, $recordCount));
        $run = $this->exports->attachJob($run->id, $this->jobs->enqueue($run));
        $this->audit($run);

        return $run;
    }

    /**
     * How many submissions each scope would export, before anything is exported.
     *
     * @param list<int>           $selectedIds
     * @param array<string,mixed> $query       The filters in force.
     *
     * @return array{selected:int,filtered:int,accessible:int}
     */
    public function preview(SubmissionAccessScope $scope, array $selectedIds, array $query, bool $includeTest): array
    {
        $selected = 0;
        foreach ($selectedIds as $id) {
            $record = $this->submissions->findInbox((int) $id, $scope);
            $selected += $record !== null && ($includeTest || ! ($record['is_test'] ?? false)) ? 1 : 0;
        }

        return [
            'selected' => $selected,
            'filtered' => $this->count($scope, $query, $includeTest),
            'accessible' => $this->count($scope, [], $includeTest),
        ];
    }

    /**
     * Takes one step of an export now, for the person waiting on it.
     *
     * @return array{state:string,processed:int,total:int,error:string}
     */
    public function advance(SubmissionAccessScope $scope, int $runId): array
    {
        $run = $this->exports->find($runId);
        if ($run === null || (! $scope->manageAll && $run->actorId !== $scope->actorId)) {
            throw new DomainException('The submission export is unavailable.');
        }

        return $this->jobs->advance($run->jobId);
    }

    private function assertPersonalData(SubmissionAccessScope $scope, SubmissionExportRequest $request): void
    {
        if (! $request->includesPersonalData()) {
            return;
        }
        if (! $scope->canExportPersonalData) {
            throw new DomainException('This actor cannot export submission personal data.');
        }
        if (! $request->personalDataAcknowledged) {
            throw new DomainException('The actor must acknowledge the personal data export warning.');
        }
    }

    private function validateSelection(SubmissionAccessScope $scope, SubmissionExportRequest $request): int
    {
        foreach ($request->selectedIds as $id) {
            $record = $this->submissions->findInbox($id, $scope);
            if ($record === null || (! $request->includeTest && (bool) ($record['is_test'] ?? false))) {
                throw new DomainException('One or more selected submissions are unavailable for export.');
            }
        }

        return count($request->selectedIds);
    }

    private function countQuery(SubmissionAccessScope $scope, SubmissionExportRequest $request): int
    {
        return $this->count($scope, $request->scope === 'filtered' ? $request->query : [], $request->includeTest);
    }

    /**
     * @param array<string,mixed> $query
     */
    private function count(SubmissionAccessScope $scope, array $query, bool $includeTest): int
    {
        $query['include_test'] = $includeTest;
        $query['per_page'] = 1;
        $page = $this->submissions->queryInbox(SubmissionInboxQuery::from($query), $scope);

        return max(0, $page['total']);
    }

    private function audit(SubmissionExportRun $run): void
    {
        $now = new DateTimeImmutable('now');
        $this->activity->record(
            actorId: $run->actorId,
            actorKind: ActivityEvent::ACTOR_USER,
            actorLabel: 'User #' . $run->actorId,
            area: ActivityEvent::AREA_SUBMISSIONS,
            kind: 'submission.export.queued',
            targetType: 'submission_export',
            targetId: (string) $run->id,
            targetLabel: 'Submission export #' . $run->id,
            outcome: ActivityEvent::OUTCOME_QUEUED,
            summary: ['key' => 'submission.export.queued', 'args' => ['count' => $run->recordCount]],
            context: ['scope' => $run->scope, 'columns' => $run->columns, 'record_count' => $run->recordCount],
            sensitivity: ActivityEvent::SENSITIVITY_PERSONAL,
            retentionUntil: $now->add(new DateInterval('P1Y')),
            occurredAt: $now,
        );
    }
}
