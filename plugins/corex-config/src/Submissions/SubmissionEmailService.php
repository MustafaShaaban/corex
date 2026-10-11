<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Corex\Mail\MailResult;
use Corex\Mail\SubmissionEmailGateway;
use DomainException;

/**
 * Permission-scoped submission reply, resend, and redacted delivery-log operations.
 */
final readonly class SubmissionEmailService
{
    public function __construct(
        private SubmissionWorkflowStore $submissions,
        private SubmissionEmailGateway $email,
        private SubmissionTimelineStore $timeline,
        private SubmissionReplyStore $replies,
    ) {
    }

    /**
     * Sends a reply and keeps it with the submission, whatever happened to it: a reply that
     * was not delivered is on record as not delivered, for whoever picks the lead up next.
     *
     * Where a reply is certain to be refused none is sent and none is kept: the reply box said
     * so before anything was written (spec 106, FR-028), and this is the same answer.
     */
    public function reply(SubmissionAccessScope $scope, int $submissionId, SubmissionReply $reply): SentReply
    {
        $record = $this->accessible($scope, $submissionId);
        $recipient = $this->recipient($record);
        $readiness = $this->email->readiness();
        if (! $readiness->sendable()) {
            throw new DomainException($readiness->reason);
        }
        $result = $this->email->reply($recipient, $reply->subject, $reply->htmlBody);

        return new SentReply($result, $this->kept($scope, $submissionId, $reply, $result));
    }

    /**
     * The email a reply would be sent as, for the person writing it to read first. Nothing is
     * sent and nothing is kept; the same scope and the same address as a reply.
     *
     * @return array{html:string,text:string}
     */
    public function preview(SubmissionAccessScope $scope, int $submissionId, SubmissionReply $reply): array
    {
        $record = $this->accessible($scope, $submissionId);

        return $this->email->preview($this->recipient($record), $reply->subject, $reply->htmlBody);
    }

    /**
     * Writes the reply and its history entry down, and says whether that was done.
     *
     * By now the email has gone or been refused. A submission deleted for good in that moment,
     * or a row the database will not take, must not become an error: the person would send
     * again, and the customer would be answered twice.
     */
    private function kept(SubmissionAccessScope $scope, int $submissionId, SubmissionReply $reply, MailResult $result): bool
    {
        try {
            $kept = SubmissionReplyRecord::sent($reply, $scope->actorId, $result);
            $this->replies->add($submissionId, $kept);
            $this->recordOutcome($scope, $submissionId, 'reply', $result, $kept->id);

            return true;
        } catch (DomainException) {
            return false;
        }
    }

    public function resend(SubmissionAccessScope $scope, int $submissionId, string $attemptId): MailResult
    {
        $record = $this->accessible($scope, $submissionId);
        $this->assertRelated($record, $attemptId);
        $result = $this->email->resend($attemptId, $this->recipient($record), [
            'submission' => (array) ($record['values'] ?? []),
            'flow' => ['id' => (int) ($record['flow_id'] ?? 0), 'name' => (string) ($record['flow'] ?? '')],
        ]);
        $this->recordOutcome($scope, $submissionId, 'resend', $result);

        return $result;
    }

    /** @return array<string,mixed>|null */
    public function log(SubmissionAccessScope $scope, int $submissionId, string $attemptId): ?array
    {
        $record = $this->accessible($scope, $submissionId);
        $this->assertRelated($record, $attemptId);

        return $this->email->log($attemptId);
    }

    /** @return array<string,mixed> */
    private function accessible(SubmissionAccessScope $scope, int $submissionId): array
    {
        $record = $this->submissions->findWorkflow($submissionId);
        if ($record === null || ! $scope->allows($record)) {
            throw new DomainException('The submission is unavailable to this actor.');
        }

        return $record;
    }

    /** @param array<string,mixed> $record */
    private function recipient(array $record): string
    {
        $recipient = (string) ($record['submitter_email'] ?? '');
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException('The submission does not contain a replyable email address.');
        }

        return $recipient;
    }

    /** @param array<string,mixed> $record */
    private function assertRelated(array $record, string $attemptId): void
    {
        $bindings = $record['related_emails']['bindings'] ?? [];
        $related = [];
        foreach (is_array($bindings) ? $bindings : [] as $binding) {
            if (is_array($binding) && is_string($binding['attempt_id'] ?? null)) {
                $related[] = $binding['attempt_id'];
            }
        }
        if (! in_array($attemptId, $related, true)) {
            throw new DomainException('The email attempt is not related to this submission.');
        }
    }

    private function recordOutcome(
        SubmissionAccessScope $scope,
        int $submissionId,
        string $action,
        MailResult $result,
        ?string $replyId = null,
    ): void {
        // The history says that a reply was sent and which one. What it said is with the reply.
        $this->timeline->append($submissionId, 'email', $result->successful() ? 'success' : 'failure', [
            'action' => $action,
            'attempt_id' => $result->attemptId,
            'state' => $result->state,
            'actor_id' => $scope->actorId,
        ] + ($replyId === null ? [] : ['reply_id' => $replyId]));
    }
}
