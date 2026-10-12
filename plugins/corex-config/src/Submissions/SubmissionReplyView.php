<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Corex\Config\Forms\FlowFilterOptions;
use Corex\Mail\SubmissionEmailGateway;

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
    ) {
    }

    /**
     * @param array<string,mixed> $record  The submission, as the inbox reads it.
     * @param int                 $actorId The person looking: the reply they began is theirs alone to see.
     *
     * @return array{replies:list<array<string,mixed>>,reply_delivery:array{mode:string,code:string,reason:string},reply_draft:array{subject:string,body:string,saved_at:string}|null,reply_subject:string}
     */
    public function of(array $record, int $actorId): array
    {
        $submissionId = (int) ($record['id'] ?? 0);

        return [
            'replies' => array_map($this->described(...), $this->replies->forSubmission($submissionId)),
            'reply_delivery' => $this->email->readiness()->toArray(),
            'reply_draft' => $this->drafts?->of($actorId, $submissionId),
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
        foreach ($slug === '' ? [] : ($this->forms?->all() ?? []) as $form) {
            if ($form['slug'] === $slug) {
                return $form['name'];
            }
        }

        return '';
    }

    /** @return array<string,mixed> */
    private function described(SubmissionReplyRecord $reply): array
    {
        return $reply->toArray() + [
            // '' when the person who sent it is no longer on the site.
            'actor_name' => $this->people->nameOf('user', (string) $reply->actorId),
            'state' => $reply->state(),
            'message' => $reply->message(),
        ];
    }
}
