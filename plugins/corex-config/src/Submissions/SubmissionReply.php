<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use InvalidArgumentException;

final readonly class SubmissionReply
{
    public string $subject;
    public string $htmlBody;

    public function __construct(string $subject, string $htmlBody)
    {
        $this->subject = trim($subject);
        // The reply is typed in a plain text box and sent as HTML. Without this a line break
        // was not markup, and three paragraphs reached the recipient as one block.
        $this->htmlBody = trim(wpautop(trim($htmlBody)));
        if ($this->subject === '' || $this->htmlBody === '') {
            throw new InvalidArgumentException('A submission reply requires a subject and body.');
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $this->subject) === 1
            || preg_match('/<\s*(?:script|iframe|object|embed|form)\b|\son[a-z]+\s*=|javascript\s*:/i', $this->htmlBody) === 1
        ) {
            throw new InvalidArgumentException('The submission reply contains unsafe content.');
        }
    }
}
