<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Listeners;

defined('ABSPATH') || exit;

use Corex\Forms\Submission\FormSubmittedEvent;
use Corex\Forms\Submission\SubmissionRepository;

/**
 * Persists a submission via the data layer. If the write throws, the dispatcher
 * isolates and logs it (best-effort), so this listener stays a thin delegate.
 */
final class StoreSubmissionListener
{
    public function __construct(private readonly SubmissionRepository $submissions)
    {
    }

    public function __invoke(FormSubmittedEvent $event): void
    {
        $this->submissions->store($event->formSlug, $event->values);
    }
}
