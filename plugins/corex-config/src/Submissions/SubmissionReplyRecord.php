<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Corex\Mail\MailResult;
use Corex\Support\Uuid;
use DateTimeImmutable;

/**
 * A reply as it is kept with its submission: what was said, by whom, when, and what happened
 * each time it was sent (spec 106, FR-021).
 *
 * The message is kept as it was reduced, before any email template: the template can change,
 * and what the team reads is what their colleague wrote.
 */
final readonly class SubmissionReplyRecord
{
    /**
     * @param list<array{attempt_id:string,state:string,message:string,occurred_at:string}> $attempts Oldest first.
     */
    public function __construct(
        public string $id,
        public string $subject,
        public string $bodyHtml,
        public int $actorId,
        public string $createdAt,
        public array $attempts,
        /** Whether it was sent under the emails the person already had, or as a new one. */
        public bool $inConversation = false,
    ) {
    }

    public static function sent(SubmissionReply $reply, int $actorId, MailResult $result, bool $inConversation = false): self
    {
        return new self(
            Uuid::v4(),
            $reply->subject,
            $reply->htmlBody,
            $actorId,
            (new DateTimeImmutable('now'))->format(DATE_ATOM),
            [self::attempt($result)],
            $inConversation,
        );
    }

    /**
     * @param array<string,mixed> $stored
     */
    public static function fromArray(array $stored): ?self
    {
        $id = (string) ($stored['id'] ?? '');
        if ($id === '') {
            return null;
        }
        $attempts = array_values(array_filter((array) ($stored['attempts'] ?? []), 'is_array'));

        return new self(
            $id,
            (string) ($stored['subject'] ?? ''),
            (string) ($stored['body_html'] ?? ''),
            (int) ($stored['actor_id'] ?? 0),
            (string) ($stored['created_at'] ?? ''),
            array_map(static fn (array $attempt): array => [
                'attempt_id' => (string) ($attempt['attempt_id'] ?? ''),
                'state' => (string) ($attempt['state'] ?? ''),
                'message' => (string) ($attempt['message'] ?? ''),
                'occurred_at' => (string) ($attempt['occurred_at'] ?? ''),
            ], $attempts),
            // A reply kept before there was a choice went as a new email.
            ($stored['thread'] ?? '') === 'conversation',
        );
    }

    /**
     * How long a sending that was begun stands in the way of another, in seconds. A request
     * that died between claiming a reply and sending it would otherwise leave the reply
     * waiting for ever: after this long it can be sent again.
     */
    private const A_SENDING_LASTS = 600;

    /** The states a sending ends in with the person not having the reply. */
    private const DID_NOT_GO = [MailResult::STATE_FAILED, MailResult::STATE_REJECTED, MailResult::STATE_BOUNCED];

    /**
     * Whether this reply can be sent another time (spec 106, FR-022): its last sending ended
     * with the person not having it, and nothing is on its way to them.
     *
     * By the states that say so, and no other: a state nothing here knows is not taken to
     * mean the reply did not arrive. A reply kept on a site that keeps its email is not one
     * that failed. A sending somebody began is in the way of another until it ends, or until
     * it has plainly been abandoned.
     */
    public function canBeSentAgain(DateTimeImmutable $now): bool
    {
        $last = $this->lastAttempt();
        if (in_array($last['state'] ?? '', self::DID_NOT_GO, true)) {
            return true;
        }
        $begun = strtotime($last['occurred_at'] ?? '');

        return self::isAClaim($last) && $begun !== false && $now->getTimestamp() - $begun > self::A_SENDING_LASTS;
    }

    /**
     * This reply with a sending begun: written down before the email is handed over, so that
     * a second person pressing the same button finds it already being sent.
     */
    public function beingSentAgain(DateTimeImmutable $now): self
    {
        return $this->with([...$this->endedAttempts(), [
            'attempt_id' => '',
            'state' => MailResult::STATE_SENDING,
            'message' => '',
            'occurred_at' => $now->format(DATE_ATOM),
        ]], $this->inConversation);
    }

    /**
     * This reply with the sending that was begun ended: what happened takes the place of the
     * sending begun. What was written, by whom and when are as they were.
     *
     * @param bool $inConversation Whether it went under emails the person already had this time.
     */
    public function sentAgain(MailResult $result, bool $inConversation): self
    {
        return $this->with([...$this->endedAttempts(), self::attempt($result)], $inConversation);
    }

    /**
     * The sendings that ended. One that was begun and not ended is not a sending of its own:
     * what happens next takes its place, also where it was abandoned.
     *
     * @return list<array{attempt_id:string,state:string,message:string,occurred_at:string}>
     */
    private function endedAttempts(): array
    {
        return self::isAClaim($this->lastAttempt()) ? array_slice($this->attempts, 0, -1) : $this->attempts;
    }

    /**
     * @param list<array{attempt_id:string,state:string,message:string,occurred_at:string}> $attempts
     */
    private function with(array $attempts, bool $inConversation): self
    {
        return new self($this->id, $this->subject, $this->bodyHtml, $this->actorId, $this->createdAt, $attempts, $inConversation);
    }

    /**
     * A sending that was begun and has not ended: no email system has given it an id yet.
     *
     * @param array<string,string> $attempt
     */
    private static function isAClaim(array $attempt): bool
    {
        return ($attempt['state'] ?? '') === MailResult::STATE_SENDING && ($attempt['attempt_id'] ?? '') === '';
    }

    /** What happened the last time this reply was sent. */
    public function state(): string
    {
        return $this->lastAttempt()['state'] ?? '';
    }

    /** The mail system's own words about the last sending. */
    public function message(): string
    {
        return $this->lastAttempt()['message'] ?? '';
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'body_html' => $this->bodyHtml,
            'actor_id' => $this->actorId,
            'created_at' => $this->createdAt,
            'attempts' => $this->attempts,
            'thread' => $this->inConversation ? 'conversation' : 'new',
        ];
    }

    /** @return array{attempt_id:string,state:string,message:string,occurred_at:string}|array{} */
    private function lastAttempt(): array
    {
        return $this->attempts === [] ? [] : $this->attempts[count($this->attempts) - 1];
    }

    /** @return array{attempt_id:string,state:string,message:string,occurred_at:string} */
    private static function attempt(MailResult $result): array
    {
        return [
            'attempt_id' => $result->attemptId,
            'state' => $result->state,
            'message' => $result->message,
            'occurred_at' => $result->occurredAt->format(DATE_ATOM),
        ];
    }
}
