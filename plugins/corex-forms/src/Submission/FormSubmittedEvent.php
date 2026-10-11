<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

use Corex\Events\Event;

/**
 * Dispatched once a submission passes the security gate and validation. Carries the form slug
 * and the validated/normalized values for the listeners, and neither changes.
 *
 * One thing is learned while the listeners run: which stored submission this became. Whatever
 * stores it says so, and a listener that runs after it can point at the submission, as a
 * notification email does (spec 081, US3). A form's listeners run in the order it lists them.
 */
final class FormSubmittedEvent implements Event
{
    private ?int $submissionId = null;

    /**
     * @param array<string,mixed> $values
     */
    public function __construct(
        public readonly string $formSlug,
        public readonly array $values,
    ) {
    }

    /**
     * Said by whatever stored the submission. The first id is kept: a submission stored twice
     * is found where it was first put.
     */
    public function storedAs(int $submissionId): void
    {
        $this->submissionId ??= $submissionId > 0 ? $submissionId : null;
    }

    /**
     * The stored submission's id, or null when nothing has stored it: the form does not store,
     * or this listener runs before the one that does.
     */
    public function submissionId(): ?int
    {
        return $this->submissionId;
    }
}
