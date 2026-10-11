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
    ) {
    }

    public static function sent(SubmissionReply $reply, int $actorId, MailResult $result): self
    {
        return new self(
            Uuid::v4(),
            $reply->subject,
            $reply->htmlBody,
            $actorId,
            (new DateTimeImmutable('now'))->format(DATE_ATOM),
            [self::attempt($result)],
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
        );
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
