<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Booking;

defined('ABSPATH') || exit;

use Corex\Assistant\Forms\AssistantCallbackForm;

/**
 * The time a stored callback request asked for, read back from the submission (spec 129, FR-021), so a
 * request restored from the trash can take its time again.
 *
 * The keys are those `Corex\Forms\Submission\SubmissionRepository::store()` writes: the form's slug under
 * `corex_form_slug` and each answer under `corex_field_<name>`. They are named here rather than asked of the
 * repository because this runs from post hooks the add-on registers whether or not Corex Forms is active
 * (Principle IX). A rename there must be made here too; `AssistantSlotsTest` restores a request through the
 * inbox's own trash store and fails if it is not.
 */
final class StoredCallbackSlot
{
    private const FORM = 'corex_form_slug';

    private const SLOT = 'corex_field_slot';

    /** The requested time in UTC seconds, or null for another form's submission or a request with no time. */
    public function __invoke(int $submissionId): ?int
    {
        if (get_post_meta($submissionId, self::FORM, true) !== AssistantCallbackForm::SLUG) {
            return null;
        }

        return SlotMoment::read(get_post_meta($submissionId, self::SLOT, true));
    }
}
