<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * A reply to the person who sent a submission: a subject, and a message in the formatting a
 * reply has.
 */
final readonly class SubmissionReply
{
    public string $subject;
    public string $htmlBody;

    /**
     * @param string $htmlBody The message as {@see ReplyBody::reduce()} leaves it. The reply
     *                         route reduces what is posted before it gets here.
     */
    public function __construct(string $subject, string $htmlBody)
    {
        $this->subject = trim($subject);
        $this->htmlBody = trim($htmlBody);
        if ($this->subject === '') {
            throw new InvalidArgumentException(__('Give the reply a subject before sending.', 'corex'));
        }
        if ($this->htmlBody === '') {
            throw new InvalidArgumentException(__('Write a message before sending.', 'corex'));
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $this->subject) === 1) {
            throw new InvalidArgumentException(__('The subject holds characters an email cannot carry.', 'corex'));
        }
    }
}
