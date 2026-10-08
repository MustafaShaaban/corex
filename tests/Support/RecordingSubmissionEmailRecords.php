<?php

/**
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

use Corex\Mail\SubmissionEmailRecords;

/**
 * Email records that keep what they were told to forget, so a test can read it.
 */
final class RecordingSubmissionEmailRecords implements SubmissionEmailRecords
{
    /** @var list<list<string>> Each call's attempt ids. */
    public array $forgotten = [];

    public function forget(array $attemptIds): void
    {
        $this->forgotten[] = $attemptIds;
    }
}
