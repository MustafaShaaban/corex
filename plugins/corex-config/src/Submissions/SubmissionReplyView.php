<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

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
    ) {
    }

    /**
     * @return array{replies:list<array<string,mixed>>,reply_delivery:array{mode:string,code:string,reason:string}}
     */
    public function of(int $submissionId): array
    {
        return [
            'replies' => array_map($this->described(...), $this->replies->forSubmission($submissionId)),
            'reply_delivery' => $this->email->readiness()->toArray(),
        ];
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
