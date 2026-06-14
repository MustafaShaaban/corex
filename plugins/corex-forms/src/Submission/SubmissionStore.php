<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

/**
 * The persistence seam for form submissions (spec 045, US4). The forms submit path depends
 * on this abstraction, not on a concrete store, so where submissions live is a driver swap —
 * not a rewrite (Principle IX applied to our own storage / DIP).
 *
 * The **default driver** is {@see SubmissionRepository} — the `corex_submission` post +
 * `corex_field_*` postmeta storage (unchanged). A **custom-table driver** (a `TableRepository`
 * implementation, for volume/reporting) is the documented future option and is **out of scope**
 * here; reads for the admin Data screen go through the spec-030 `SubmissionsReader`.
 */
interface SubmissionStore
{
    /**
     * Persist a submission and return its id.
     *
     * @param array<string,mixed> $values validated values, keyed by canonical field name
     */
    public function save(string $slug, array $values): int;
}
