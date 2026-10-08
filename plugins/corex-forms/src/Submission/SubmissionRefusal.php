<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

/**
 * Why a submission was refused, when the reason is not one of its answers.
 *
 * A refusal for an answer carries the fields and their rules. This carries a code a script can
 * tell from that, so a visitor who failed the challenge is not told to review their answers.
 */
final readonly class SubmissionRefusal
{
    public const CHALLENGE_FAILED = 'challenge_failed';

    public function __construct(public string $code)
    {
    }
}
