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
    ) {
    }

    public function reply(SubmissionAccessScope $scope, int $submissionId, SubmissionReply $reply): MailResult
    {
        $record = $this->accessible($scope, $submissionId);
        $recipient = $this->recipient($record);
        $result = $this->email->reply($recipient, $reply->subject, $reply->htmlBody);
        $this->recordOutcome($scope, $submissionId, 'reply', $result);

        return $result;
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
    ): void {
        $this->timeline->append($submissionId, 'email', $result->successful() ? 'success' : 'failure', [
            'action' => $action,
            'attempt_id' => $result->attemptId,
            'state' => $result->state,
            'actor_id' => $scope->actorId,
        ]);
    }
}
