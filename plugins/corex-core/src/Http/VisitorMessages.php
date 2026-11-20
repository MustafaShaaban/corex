<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Http;

defined('ABSPATH') || exit;

/**
 * The messages for visitors of each mode that closes public submissions (spec 124, FR-007), as
 * {@see PublicSubmissionPause} reads them.
 *
 * The "Messages for visitors" card that stores them is spec 124's slice 9, which fills this from
 * what the owner saved. Until then none is set: every mode says CoreX's own words, with no back-by
 * time.
 */
final readonly class VisitorMessages
{
    /** @param array<string,VisitorMessage> $messages mode => what its visitors are told */
    public function __construct(private array $messages = [])
    {
    }

    public function for(string $mode): VisitorMessage
    {
        return $this->messages[$mode] ?? new VisitorMessage();
    }
}
