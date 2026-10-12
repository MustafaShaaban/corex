<?php

/**
 * @package Corex\Core
 */

declare(strict_types=1);

namespace Corex\Mail;

defined('ABSPATH') || exit;

/**
 * The emails a site has already sent one person about one submission, which a reply can be
 * sent under: the confirmation they were sent, and each reply since (spec 106, FR-038).
 *
 * An email says which one it answers by that one's id, and names the ones before it. This is
 * those ids, oldest first, and the subject of the latest.
 */
final readonly class ReplyConversation
{
    /** @var list<string> */
    public array $messageIds;

    public string $subject;

    /**
     * @param list<string> $messageIds Each as it was sent, in its angle brackets, oldest first.
     *                                 Anything that is not an id is passed over: these are
     *                                 written into an email's headers.
     * @param string       $subject    The subject of the latest of them.
     */
    public function __construct(array $messageIds = [], string $subject = '')
    {
        // One rule for what an id is, which is also the driver's: an id this let through and
        // the driver did not write would be a reply recorded as joining what it never named.
        $this->messageIds = array_values(array_filter($messageIds, [MessageId::class, 'isOne']));
        $this->subject = $this->messageIds === [] ? '' : trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $subject));
    }

    /** No email to continue from: a reply can only be a new one. */
    public function isEmpty(): bool
    {
        return $this->messageIds === [];
    }

    /** The id of the email a reply answers, or '' where there is none. */
    public function latest(): string
    {
        return $this->messageIds === [] ? '' : $this->messageIds[count($this->messageIds) - 1];
    }
}
