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
 * Permission-scoped, acknowledged, audited submission export orchestration.
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
        $run = $this->exports->create(SubmissionExportRun::queued($scope->actorId, $request, $recordCount));
        $run = $this->exports->attachJob($run->id, $this->jobs->enqueue($run));
        $this->audit($run);

        return $run;
    }

    /** @return list<SubmissionExportRun> */
    public function history(SubmissionAccessScope $scope, int $limit = 50): array
    {
        return $this->exports->history($scope, min(100, max(1, $limit)));
    }

    /** @return array{filename:string,csv:string} */
    public function download(SubmissionAccessScope $scope, int $runId): array
    {
        $run = $this->exports->find($runId);
        if ($run === null || (! $scope->manageAll && $run->actorId !== $scope->actorId)) {
            throw new DomainException('The submission export is unavailable.');
        }
        $csv = $this->exports->artifact($runId);
        if ($csv === null) {
            throw new DomainException('The submission export artifact is not ready.');
        }

        return ['filename' => 'corex-submissions-' . $runId . '.csv', 'csv' => $csv];
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
        $input = $request->scope === 'filtered' ? $request->query : [];
        $input['include_test'] = $request->includeTest;
        $input['per_page'] = 1;
        $page = $this->submissions->queryInbox(SubmissionInboxQuery::from($input), $scope);

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
