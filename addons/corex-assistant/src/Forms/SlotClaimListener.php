<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Forms;

defined('ABSPATH') || exit;

use Corex\Assistant\Booking\SlotClaims;
use Corex\Assistant\Booking\SlotMoment;
use Corex\Forms\Submission\FormSubmittedEvent;

/**
 * The last listener of the callback form (spec 129, FR-021): the time the request claimed is named after the
 * submission it became, so binning the submission frees it. When nothing stored the request (storage
 * failed, or a site's `corex_assistant_callback_listeners` filter stores nothing), the claim is released:
 * a time nobody can find in the inbox must not stay hidden.
 */
final class SlotClaimListener
{
    public function __construct(private readonly SlotClaims $claims)
    {
    }

    public function __invoke(FormSubmittedEvent $event): void
    {
        $slot = $event->formSlug === AssistantCallbackForm::SLUG ? SlotMoment::read($event->values['slot'] ?? null) : null;
        if ($slot === null) {
            return;
        }
        $submissionId = $event->submissionId();
        if ($submissionId === null) {
            $this->claims->release($slot);

            return;
        }
        $this->claims->confirm($slot, $submissionId);
    }
}
