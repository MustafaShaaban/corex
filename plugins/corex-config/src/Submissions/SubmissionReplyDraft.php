<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

/**
 * A reply somebody has begun and not sent (spec 106, FR-006).
 *
 * Unlike a reply it may be half of one: a subject with no message yet, or the other way round.
 * Its message is in the formatting a reply has, reduced by {@see ReplyBody} before it gets here.
 */
final readonly class SubmissionReplyDraft
{
    /** A subject is a line. Beyond this it is something pasted by mistake, and is cut. */
    private const LONGEST_SUBJECT = 300;

    public string $subject;
    public string $htmlBody;

    public string $savedAt;

    /**
     * @param string $savedAt When it was last written down, as an ISO 8601 date; '' where
     *                        what was given is not a date.
     */
    public function __construct(string $subject, string $htmlBody, string $savedAt)
    {
        $this->savedAt = strtotime($savedAt) === false ? '' : $savedAt;
        $line = trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $subject));
        $this->subject = mb_substr($line, 0, self::LONGEST_SUBJECT);
        $this->htmlBody = trim($htmlBody);
    }

    /** A draft of nothing is no draft: it is discarded, not kept. */
    public function isEmpty(): bool
    {
        return $this->subject === '' && $this->htmlBody === '';
    }

    /** @return array{subject:string,body:string,saved_at:string} */
    public function toArray(): array
    {
        return ['subject' => $this->subject, 'body' => $this->htmlBody, 'saved_at' => $this->savedAt];
    }

    /**
     * @param mixed $stored What the store holds under a draft's key.
     *
     * @return self|null Null for what is not a draft, or is a draft of nothing.
     */
    public static function fromStored(mixed $stored): ?self
    {
        if (! is_array($stored)) {
            return null;
        }
        $draft = new self(
            is_string($stored['subject'] ?? null) ? $stored['subject'] : '',
            // Reduced again on the way out: what the store holds was written by this code, and
            // is put on a page all the same.
            ReplyBody::reduce($stored['body'] ?? ''),
            is_string($stored['saved_at'] ?? null) ? $stored['saved_at'] : '',
        );

        return $draft->isEmpty() ? null : $draft;
    }
}
