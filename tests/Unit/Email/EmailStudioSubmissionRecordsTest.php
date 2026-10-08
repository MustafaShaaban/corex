<?php

/**
 * What Email Studio lets go of when a submission is deleted (spec 105, FR-011).
 *
 * @package Corex\Tests\Unit\Email
 */

declare(strict_types=1);

use Corex\Email\Studio\EmailAttemptRemoval;
use Corex\Email\Studio\EmailStudioSubmissionRecords;

it('forgets each attempt once, and passes over an id Email Studio did not make', function () {
    $removal = new class() implements EmailAttemptRemoval {
        /** @var list<string> */
        public array $asked = [];

        public function forgetAttempt(string $attemptId): int
        {
            $this->asked[] = $attemptId;

            return 1;
        }
    };
    $first = '11111111-1111-4111-8111-111111111111';
    $second = '22222222-2222-4222-8222-222222222222';

    // A submission's history is written by more than one stage, and one of them could hold
    // anything: an id that is not an attempt's is not looked for among Email Studio's records.
    (new EmailStudioSubmissionRecords($removal))->forget([$first, $second, $first, '', 'not-an-attempt', '%']);

    expect($removal->asked)->toBe([$first, $second]);
});
