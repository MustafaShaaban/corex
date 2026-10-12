<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use DomainException;

/**
 * A person's unsent reply to a submission, kept for them until they send or discard it
 * (spec 106, FR-006).
 *
 * It is theirs alone: nobody else is shown it, and it is kept only for a submission they can
 * reach. On the server and not in the browser, so that it is there on another machine.
 */
final readonly class SubmissionReplyDrafts
{
    public function __construct(
        private SubmissionWorkflowStore $submissions,
        private SubmissionReplyDraftStore $drafts,
    ) {
    }

    /**
     * The draft of the person asking, for a submission they have already been shown.
     *
     * @return array{subject:string,body:string,saved_at:string,thread:string}|null
     */
    public function of(int $actorId, int $submissionId): ?array
    {
        return $this->drafts->find($submissionId, $actorId)?->toArray();
    }

    /**
     * Writes down what is written so far. A draft of nothing is discarded.
     *
     * @param string $htmlBody The message as {@see ReplyBody::reduce()} leaves it.
     * @param string $thread   How the reply is to be sent: `conversation`, `new`, or '' where
     *                         that was not said.
     *
     * @return array{subject:string,body:string,saved_at:string,thread:string}|null What is
     *                                                 now kept, as it will be given back.
     *
     * @throws DomainException When the submission is not the person's to answer.
     */
    public function keep(SubmissionAccessScope $scope, int $submissionId, string $subject, string $htmlBody, string $thread = ''): ?array
    {
        $this->assertReachable($scope, $submissionId);
        $draft = new SubmissionReplyDraft($subject, $htmlBody, (new DateTimeImmutable('now'))->format(DATE_ATOM), $thread);
        if ($draft->isEmpty()) {
            $this->drafts->discard($submissionId, $scope->actorId);

            return null;
        }
        $this->drafts->keep($submissionId, $scope->actorId, $draft);

        return $this->of($scope->actorId, $submissionId);
    }

    /**
     * @throws DomainException When the submission is not the person's to answer.
     */
    public function discard(SubmissionAccessScope $scope, int $submissionId): void
    {
        $this->assertReachable($scope, $submissionId);
        $this->drafts->discard($submissionId, $scope->actorId);
    }

    private function assertReachable(SubmissionAccessScope $scope, int $submissionId): void
    {
        $record = $this->submissions->findWorkflow($submissionId);
        if ($record === null || ! $scope->allows($record)) {
            throw new DomainException('The submission is unavailable to this actor.');
        }
    }
}
