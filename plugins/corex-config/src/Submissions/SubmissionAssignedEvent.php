<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Corex\Events\Event;

/**
 * Dispatched once a submission's assignment changes, so the Notification Center can tell the assignee
 * without watching the inbox tables. Immutable: carries the submission, the new owner (type + key,
 * mirroring {@see SubmissionAssignment}), and who made the change.
 */
final class SubmissionAssignedEvent implements Event
{
    public function __construct(
        public readonly int $submissionId,
        public readonly string $assigneeType,
        public readonly string $assigneeKey,
        public readonly int $actorId,
    ) {
    }
}
