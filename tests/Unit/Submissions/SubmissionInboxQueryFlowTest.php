<?php

/**
 * Unit tests for how the inbox reads its `flow` filter (GitHub issue #114). No WordPress beyond
 * `sanitize_key`.
 *
 * Builder flows live in the database and their submissions carry `corex_flow_id`. A form
 * registered in code through `FormRegistry` has no row and carries only `corex_form_slug`, so the
 * filter accepts `slug:<form-slug>` as well as a numeric id. The two are mutually exclusive: the
 * inbox matches on a different meta key for each, and setting both would narrow to the
 * intersection — which is always empty.
 *
 * @package Corex\Tests\Unit\Submissions
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Submissions\SubmissionInboxQuery;

beforeEach(function () {
    Functions\when('sanitize_key')->alias(
        static fn (string $key): string => preg_replace('/[^a-z0-9_\-]/', '', strtolower($key)) ?? ''
    );
});

it('reads a numeric flow id and leaves the form slug empty', function () {
    $query = SubmissionInboxQuery::from(['flow' => '42']);

    expect($query->flowId)->toBe(42)
        ->and($query->formSlug)->toBe('');
});

it('reads a slug-prefixed value as a form slug and leaves the flow id at zero', function () {
    // Zero, not 42-style truthiness: the inbox must not also add a corex_flow_id clause, or the
    // two clauses would AND together and match nothing.
    $query = SubmissionInboxQuery::from(['flow' => 'slug:newsletter-signup']);

    expect($query->formSlug)->toBe('newsletter-signup')
        ->and($query->flowId)->toBe(0);
});

it('treats an absent or empty filter as no form filter at all', function () {
    foreach ([[], ['flow' => ''], ['flow' => '0'], ['flow' => 'slug:']] as $input) {
        $query = SubmissionInboxQuery::from($input);

        expect($query->flowId)->toBe(0)
            ->and($query->formSlug)->toBe('');
    }
});

it('never lets a hostile flow value through as either an id or a slug', function () {
    // The old `(int)` cast turned any non-numeric value into 0, which reads as "all forms" — a
    // filter that silently ignores you. Junk must stay junk-free, not become a wildcard by accident.
    $query = SubmissionInboxQuery::from(['flow' => 'slug:../../etc/passwd']);

    expect($query->formSlug)->toBe('etcpasswd')
        ->and($query->flowId)->toBe(0);

    $negative = SubmissionInboxQuery::from(['flow' => '-9']);
    expect($negative->flowId)->toBe(0)
        ->and($negative->formSlug)->toBe('');
});
