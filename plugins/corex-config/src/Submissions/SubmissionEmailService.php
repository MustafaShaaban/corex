<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Corex\Mail\MailResult;
use Corex\Mail\RecipientAwareSubmissionEmailGateway;
use Corex\Mail\SubmissionEmailGateway;
use Corex\Mail\SubmissionEmailRecipients;
use DateTimeImmutable;
use DomainException;

/**
 * Permission-scoped submission reply, resend, and redacted delivery-log operations.
 */
final readonly class SubmissionEmailService
{
    /** A resend went to the recipients recorded when the email was sent (spec 112, FR-032a). */
    public const BASIS_RECORDED = 'recorded';

    /** A resend of an older email went to its one recipient, worked out again and confirmed. */
    public const BASIS_RECHECKED = 'rechecked';

    /** What each email of a flow is, by its binding (spec 112, FR-033). */
    private const KINDS = [
        'submitter_confirmation' => 'confirmation',
        'team_notification'      => 'team_notification',
        'admin_failure'          => 'failure_notice',
        'submitted'              => 'team_notification',
    ];

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
        private ?SubmissionEmailRecipients $recipients = null,
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

    /**
     * Sends one of the submission's emails again, to the people it was sent to (spec 112, FR-032a):
     * the recipients recorded when it was sent, or, for an email sent before they were recorded,
     * its one recipient worked out again from the flow and confirmed by the email's attempt.
     * Nothing is sent when they cannot be known: never a guessed recipient.
     */
    public function resend(SubmissionAccessScope $scope, int $submissionId, string $attemptId): ResentEmail
    {
        $record = $this->accessible($scope, $submissionId);
        [$event, $binding] = $this->related($record, $attemptId);
        [$recipients, $basis] = $this->recipientsOf($submissionId, $event, $binding)
            ?? throw new DomainException(__('Who this email was sent to is not known, so it is not sent again: the recipients were not recorded and cannot be confirmed.', 'corex'));
        $result = $this->send($attemptId, $recipients, [
            'submission' => (array) ($record['values'] ?? []),
            'flow' => ['id' => (int) ($record['flow_id'] ?? 0), 'name' => (string) ($record['flow'] ?? '')],
        ]);
        $this->recordOutcome($scope, $submissionId, 'resend', $result, null, $recipients);

        return new ResentEmail($result, $recipients, $basis);
    }

    /**
     * The submission's emails as the drawer lists them: each named by what it is, and whether it
     * can be sent again or why not (spec 112, FR-033).
     *
     * @param array<string,mixed> $record The submission, as read for this person.
     *
     * @return array<string,array<string,mixed>> By binding.
     */
    public function describe(array $record): array
    {
        $described = [];
        $bindings = $record['related_emails']['bindings'] ?? [];
        foreach (is_array($bindings) ? $bindings : [] as $event => $binding) {
            if (! is_array($binding)) {
                continue;
            }
            $reason = match (true) {
                ! ($binding['retryable'] ?? false) => 'not_retryable',
                $this->recipientsOf((int) ($record['id'] ?? 0), (string) $event, $binding) === null => 'recipients_unknown',
                default => null,
            };
            $described[$event] = $binding + [
                'kind' => self::KINDS[$event] ?? 'team_notification',
                'resendable' => $reason === null,
                'not_resendable_reason' => $reason,
            ];
        }

        return $described;
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
        $this->related($record, $attemptId);
    }

    /**
     * The binding this attempt is the email of.
     *
     * @param array<string,mixed> $record
     *
     * @return array{0:string,1:array<string,mixed>} Its event and what was recorded of it.
     */
    private function related(array $record, string $attemptId): array
    {
        $bindings = $record['related_emails']['bindings'] ?? [];
        foreach (is_array($bindings) ? $bindings : [] as $event => $binding) {
            if (is_array($binding) && ($binding['attempt_id'] ?? null) === $attemptId) {
                return [(string) $event, $binding];
            }
        }

        throw new DomainException('The email attempt is not related to this submission.');
    }

    /**
     * Who this email went to, and how CoreX knows: recorded when it was sent, or, for an older
     * email, its one recipient worked out again and confirmed by its attempt. Null when neither.
     *
     * Only one, for an older email: the attempt confirms the address it was made for, and the
     * other recipients of the same email are attempts of their own that it does not name.
     *
     * @param array<string,mixed> $binding
     *
     * @return array{0:list<string>,1:string}|null
     */
    private function recipientsOf(int $submissionId, string $event, array $binding): ?array
    {
        $recorded = array_values(array_filter(
            (array) ($binding['recipients'] ?? []),
            static fn (mixed $address): bool => is_string($address) && filter_var($address, FILTER_VALIDATE_EMAIL) !== false,
        ));
        if ($recorded !== []) {
            return [$recorded, self::BASIS_RECORDED];
        }

        $attemptId = (string) ($binding['attempt_id'] ?? '');
        $resolved  = $this->recipients?->resolve($submissionId, $event) ?? [];
        $confirmed = count($resolved) === 1
            && $this->email instanceof RecipientAwareSubmissionEmailGateway
            && $this->email->wasSentTo($attemptId, $resolved[0]);

        return $confirmed ? [$resolved, self::BASIS_RECHECKED] : null;
    }

    /**
     * One email to all of them where the gateway can; otherwise one each, answering with the
     * first that failed, or the last.
     *
     * @param list<string>        $recipients
     * @param array<string,mixed> $context
     */
    private function send(string $attemptId, array $recipients, array $context): MailResult
    {
        if ($this->email instanceof RecipientAwareSubmissionEmailGateway) {
            return $this->email->resendTo($attemptId, $recipients, $context);
        }

        $answer = null;
        foreach ($recipients as $recipient) {
            $result = $this->email->resend($attemptId, $recipient, $context);
            if ($answer === null || $answer->successful()) {
                $answer = $result;
            }
        }

        return $answer;
    }

    /** @param list<string> $recipients Who a resend went to (spec 112, FR-034). */
    private function recordOutcome(
        SubmissionAccessScope $scope,
        int $submissionId,
        string $action,
        MailResult $result,
        ?string $replyId = null,
        array $recipients = [],
    ): void {
        // The history says that a reply was sent and which one. What it said is with the reply.
        $this->timeline->append($submissionId, 'email', $result->successful() ? 'success' : 'failure', [
            'action' => $action,
            'attempt_id' => $result->attemptId,
            'state' => $result->state,
            'actor_id' => $scope->actorId,
        ] + ($replyId === null ? [] : ['reply_id' => $replyId])
          + ($recipients === [] ? [] : ['recipients' => $recipients]));
    }
}
