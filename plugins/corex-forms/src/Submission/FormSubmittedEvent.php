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
 * Two things are learned while the listeners run. Which stored submission this became: whatever
 * stores it says so, and a listener that runs after it can point at the submission, as a
 * notification email does (spec 081, US3). And how the form's notification email went: whatever
 * sent it says so, and the notification centre is told of one that did not go. A form's listeners
 * run in the order it lists them, and both are read once they have all run.
 */
final class FormSubmittedEvent implements Event
{
    private ?int $submissionId = null;

    private ?NotificationDelivery $delivery = null;

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

    /**
     * Said by whatever sent the form's notification email. A delivery that failed is kept: of
     * two emails where one did not go, the one somebody has to look at is that one.
     */
    public function notifiedAs(NotificationDelivery $delivery): void
    {
        if ($this->delivery === null || ! $this->delivery->failed()) {
            $this->delivery = $delivery;
        }
    }

    /** How the notification email went, or null when no listener said. */
    public function delivery(): ?NotificationDelivery
    {
        return $this->delivery;
    }
}
