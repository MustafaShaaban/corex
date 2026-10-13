<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Corex\Mail\MailResult;
use Corex\Mail\SubmissionEmailGateway;
use DateTimeImmutable;
use DomainException;

/**
 * Permission-scoped submission reply, resend, and redacted delivery-log operations.
 */
final readonly class SubmissionEmailService
{
    /**
     * The states in which a reply has gone, is on its way, or is kept as sent: what was being
     * written is then no longer a draft. The ones the pane empties its box for (`replyState.js`).
     */
    private const GONE = [
        MailResult::STATE_QUEUED, MailResult::STATE_SENT, MailResult::STATE_ACCEPTED,
        MailResult::STATE_OPENED, MailResult::STATE_CAPTURED,
    ];

    public function __construct(
        private SubmissionWorkflowStore $submissions,
        private SubmissionEmailGateway $email,
        private SubmissionTimelineStore $timeline,
        private SubmissionReplyStore $replies,
        private ?SubmissionReplyDraftStore $drafts = null,
        private ?SubmissionConversation $conversation = null,
    ) {
    }

    /**
     * Sends a reply and keeps it with the submission, whatever happened to it: a reply that
     * was not delivered is on record as not delivered, for whoever picks the lead up next.
     *
     * Where a reply is certain to be refused none is sent and none is kept: the reply box said
     * so before anything was written (spec 106, FR-028), and this is the same answer.
     */
    public function reply(SubmissionAccessScope $scope, int $submissionId, SubmissionReply $reply, bool $inConversation = false): SentReply
    {
        $record = $this->accessible($scope, $submissionId);
        $recipient = $this->recipient($record);
        $readiness = $this->email->readiness();
        if (! $readiness->sendable()) {
            throw new DomainException($readiness->reason);
        }
        // Under the emails the person already has, where that was asked for and there are
        // any (spec 106, FR-039). Asked for where there are none, it goes as a new email, and
        // is recorded as one.
        $within = $inConversation ? $this->conversation?->of($record) : null;
        $result = $this->email->reply($recipient, $reply->subject, $reply->htmlBody, $within);
        $continued = $within !== null && ! $within->isEmpty();
        $sent = new SentReply($result, $this->kept($scope, $submissionId, $reply, $result, $continued));
        $this->forgetDraft($scope, $submissionId, $result);

        return $sent;
    }

    /**
     * Sends a reply that did not go another time, as it was written, and adds what happened
     * to it (spec 106, FR-022).
     *
     * By anybody who may answer the submission, not only whoever wrote it: it is the second
     * person picking the lead up who finds a reply that did not arrive. It goes in the
     * conversation where it was to and there still is one, and as a new email otherwise.
     *
     * The reply is written down as being sent before the email is handed over. Two people
     * pressing the button in the seconds a mail server takes would otherwise both pass the
     * check above it, and the customer would have the reply twice.
     *
     * @throws DomainException When the submission is not the person's to answer, the reply is
     *                         not one of its replies, it does not need sending again,
     *                         somebody is already sending it, or a reply from this site
     *                         would be refused.
     */
    public function sendAgain(SubmissionAccessScope $scope, int $submissionId, string $replyId): SentReply
    {
        $record = $this->accessible($scope, $submissionId);
        $recipient = $this->recipient($record);
        $kept = $this->keptReply($submissionId, $replyId);
        $now = new DateTimeImmutable('now');
        if (! $kept->canBeSentAgain($now)) {
            throw new DomainException(__('This reply does not need sending again.', 'corex'));
        }
        $readiness = $this->email->readiness();
        if (! $readiness->sendable()) {
            throw new DomainException($readiness->reason);
        }
        $begun = $this->begunAgain($submissionId, $kept, $now);
        $within = $kept->inConversation ? $this->conversation?->of($record) : null;

        try {
            $result = $this->email->reply($recipient, $kept->subject, $kept->bodyHtml, $within);
        } catch (DomainException $refused) {
            // Nothing went. The reply is as it was, for the next person to send again.
            $this->writtenOver($submissionId, $begun, $kept);

            throw $refused;
        }

        // The email has gone or been refused by now, as with a first sending: what cannot be
        // written down is said, and is not an error.
        $recorded = $this->writtenOver($submissionId, $begun, $begun->sentAgain($result, $within !== null && ! $within->isEmpty()));
        if ($recorded) {
            $this->noted($scope, $submissionId, $result, $kept->id);
        }

        return new SentReply($result, $recorded, $this->endsTheirDraftOf($scope, $submissionId, $kept, $result));
    }

    private function keptReply(int $submissionId, string $replyId): SubmissionReplyRecord
    {
        foreach ($this->replies->forSubmission($submissionId) as $reply) {
            if ($reply->id === $replyId) {
                return $reply;
            }
        }

        throw new DomainException(__('That reply is not on this submission.', 'corex'));
    }

    /**
     * Writes the reply down as being sent, in place of itself as it was read.
     *
     * @throws DomainException When it is no longer as it was read: somebody else got there first.
     */
    private function begunAgain(int $submissionId, SubmissionReplyRecord $kept, DateTimeImmutable $now): SubmissionReplyRecord
    {
        $begun = $kept->beingSentAgain($now);
        if (! $this->writtenOver($submissionId, $kept, $begun)) {
            throw new DomainException(__('Somebody else is sending this reply again. Open the submission again to see what happened.', 'corex'));
        }

        return $begun;
    }

    private function writtenOver(int $submissionId, SubmissionReplyRecord $was, SubmissionReplyRecord $now): bool
    {
        try {
            $this->replies->replace($submissionId, $was, $now);

            return true;
        } catch (DomainException) {
            return false;
        }
    }

    /** The history's entry for a reply sent again. The reply is recorded whether or not this is. */
    private function noted(SubmissionAccessScope $scope, int $submissionId, MailResult $result, string $replyId): void
    {
        try {
            $this->recordOutcome($scope, $submissionId, 'reply_again', $result, $replyId);
        } catch (DomainException) {
            // The reply says what happened to it; only the history's line is missing.
        }
    }

    /**
     * Discards the draft of the person who sent a reply again, where it holds that same reply.
     *
     * A reply that was not sent stays in its writer's box and as their draft, for them to try
     * again. Sent again from where it is listed, it has gone: left in the box it would be sent
     * a second time, as a new reply.
     */
    private function endsTheirDraftOf(SubmissionAccessScope $scope, int $submissionId, SubmissionReplyRecord $kept, MailResult $result): bool
    {
        $draft = $this->drafts?->find($submissionId, $scope->actorId);
        $same = $draft !== null && trim($draft->subject) === trim($kept->subject) && $draft->htmlBody === trim($kept->bodyHtml);
        if (! $same || ! in_array($result->state, self::GONE, true)) {
            return false;
        }
        $this->drafts?->discard($submissionId, $scope->actorId);

        return true;
    }

    /**
     * What was being written has gone, or is kept as sent: it is no longer a draft. A reply
     * that was not sent stays one, for the person to try again.
     *
     * The states are the ones the pane empties its box for (`replyState.js`): where the box
     * keeps its words (FR-031), the draft keeps them too.
     */
    private function forgetDraft(SubmissionAccessScope $scope, int $submissionId, MailResult $result): void
    {
        if (in_array($result->state, self::GONE, true)) {
            $this->drafts?->discard($submissionId, $scope->actorId);
        }
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
    private function kept(SubmissionAccessScope $scope, int $submissionId, SubmissionReply $reply, MailResult $result, bool $inConversation): bool
    {
        try {
            $kept = SubmissionReplyRecord::sent($reply, $scope->actorId, $result, $inConversation);
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
