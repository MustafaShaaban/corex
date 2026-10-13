<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Corex\Config\Forms\FlowFilterOptions;
use Corex\Mail\SubmissionEmailGateway;
use DateTimeImmutable;

/**
 * What the pane shows about replying to one submission: what was already answered, and what
 * would happen to an answer sent now (spec 106, FR-021 and FR-028).
 */
final readonly class SubmissionReplyView
{
    public function __construct(
        private SubmissionReplyStore $replies,
        private SubmissionEmailGateway $email,
        private SubmissionOwnerNames $people,
        private ?SubmissionReplyDrafts $drafts = null,
        private ?FlowFilterOptions $forms = null,
        private ?SubmissionConversation $conversation = null,
    ) {
    }

    /**
     * @param array<string,mixed> $record  The submission, as the inbox reads it.
     * @param int                 $actorId The person looking: the reply they began is theirs alone to see.
     *
     * @return array<string,mixed> `replies`, `reply_delivery`, `reply_draft`, `reply_conversation` and `reply_subject`.
     */
    public function of(array $record, int $actorId): array
    {
        $submissionId = (int) ($record['id'] ?? 0);
        $conversation = $this->conversation?->of($record);

        return [
            'replies' => array_map($this->described(...), $this->replies->forSubmission($submissionId)),
            'reply_delivery' => $this->email->readiness()->toArray(),
            'reply_draft' => $this->drafts?->of($actorId, $submissionId),
            // Whether a reply can be sent under the emails the person already has, and the
            // subject it then starts as: the one that answers the latest of them (FR-039).
            'reply_conversation' => [
                'available' => $conversation !== null && ! $conversation->isEmpty(),
                'subject' => $conversation === null ? '' : ReplySubject::answering($conversation->subject),
            ],
            // What a reply's subject starts as, for whoever writes it to change (FR-035).
            'reply_subject' => ReplySubject::startingFor($record, $this->knownAs((string) ($record['form'] ?? ''))),
        ];
    }

    /**
     * What the site's forms call a form now: the name the inbox's own filter lists it under,
     * which is where a form defined in code says what it is called. '' where nothing names it.
     */
    private function knownAs(string $slug): string
    {
        return $this->forms?->nameOf($slug) ?? '';
    }

    /** @return array<string,mixed> */
    private function described(SubmissionReplyRecord $reply): array
    {
        return $reply->toArray() + [
            // '' when the person who sent it is no longer on the site.
            'actor_name' => $this->people->nameOf('user', (string) $reply->actorId),
            'state' => $reply->state(),
            'message' => $reply->message(),
            // Decided here and read by the pane: one rule for which replies did not go.
            'can_send_again' => $reply->canBeSentAgain(new DateTimeImmutable('now')),
        ];
    }
}
