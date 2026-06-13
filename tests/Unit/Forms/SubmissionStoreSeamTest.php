<?php

/**
 * Unit tests for the submission store seam (spec 045: US4, FR-010/FR-011).
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Corex\Forms\Listeners\StoreSubmissionListener;
use Corex\Forms\Submission\FormSubmittedEvent;
use Corex\Forms\Submission\SubmissionStore;

it('persists a submission through the store seam, not a concrete repository', function () {
    $store = new class implements SubmissionStore {
        public string $slug = '';
        /** @var array<string,mixed> */
        public array $values = [];

        public function save(string $slug, array $values): int
        {
            $this->slug   = $slug;
            $this->values = $values;

            return 42;
        }
    };

    (new StoreSubmissionListener($store))(new FormSubmittedEvent('contact', ['email' => 'a@b.com']));

    expect($store->slug)->toBe('contact')
        ->and($store->values)->toBe(['email' => 'a@b.com']);
});
