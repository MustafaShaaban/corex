<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Listeners;

defined('ABSPATH') || exit;

use Corex\Forms\Submission\FormSubmittedEvent;
use Corex\Forms\Submission\SubmissionStore;

/**
 * Persists a submission through the {@see SubmissionStore} seam (spec 045) — so the storage
 * driver can change without touching this path. If the write throws, the dispatcher isolates
 * and logs it (best-effort), so this listener stays a thin delegate.
 */
final class StoreSubmissionListener
{
    public function __construct(private readonly SubmissionStore $submissions)
    {
    }

    public function __invoke(FormSubmittedEvent $event): void
    {
        $this->submissions->save($event->formSlug, $event->values);
    }
}
