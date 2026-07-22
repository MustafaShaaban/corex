<?php

/**
 * Unit tests for the typed challenge verdict (spec 071 US1: FR-003, FR-005, FR-019).
 *
 * A boolean cannot carry a score, an action, or a reason — which is why the threshold
 * and action settings had no consumer. These tests pin the vocabulary and the redaction
 * guarantee before any driver produces one.
 *
 * @package Corex\Tests\Unit\Security
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Security\ChallengeVerification;

beforeEach(function () {
    Functions\when('wp_json_encode')->alias(static fn (mixed $data): string => (string) json_encode($data));
});

it('a passed verdict reports passed and carries its score and threshold', function () {
    $verification = ChallengeVerification::pass(
        score: 0.9,
        effectiveThreshold: 0.3,
        expectedAction: 'corex_form_contact',
        hostname: 'corex.local',
    );

    expect($verification->passed())->toBeTrue()
        ->and($verification->outcome)->toBe(ChallengeVerification::OUTCOME_PASSED)
        ->and($verification->score)->toBe(0.9)
        ->and($verification->effectiveThreshold)->toBe(0.3)
        ->and($verification->expectedAction)->toBe('corex_form_contact')
        ->and($verification->hostname)->toBe('corex.local');
});

it('every failure outcome reports not-passed', function (string $outcome) {
    $verification = ChallengeVerification::fail(
        outcome: $outcome,
        expectedAction: 'corex_form_contact',
        effectiveThreshold: 0.3,
        safeReason: 'A safe, admin-facing reason.',
    );

    expect($verification->passed())->toBeFalse()
        ->and($verification->outcome)->toBe($outcome)
        ->and($verification->safeReason)->toBe('A safe, admin-facing reason.');
})->with([
    ChallengeVerification::OUTCOME_TOKEN_MISSING,
    ChallengeVerification::OUTCOME_PROVIDER_ERROR,
    ChallengeVerification::OUTCOME_MALFORMED_RESPONSE,
    ChallengeVerification::OUTCOME_PROVIDER_REJECTED,
    ChallengeVerification::OUTCOME_HOSTNAME_MISMATCH,
    ChallengeVerification::OUTCOME_ACTION_MISMATCH,
    ChallengeVerification::OUTCOME_TOKEN_EXPIRED,
    ChallengeVerification::OUTCOME_TOKEN_REPLAYED,
    ChallengeVerification::OUTCOME_SCORE_BELOW_THRESHOLD,
    ChallengeVerification::OUTCOME_NOT_CONFIGURED,
]);

it('rejects an unknown outcome rather than silently accepting it', function () {
    ChallengeVerification::fail(
        outcome: 'not_a_real_outcome',
        expectedAction: 'corex_form_contact',
        effectiveThreshold: 0.3,
        safeReason: 'x',
    );
})->throws(InvalidArgumentException::class);

it('exposes only safe fields — never a raw token or provider payload', function () {
    $verification = ChallengeVerification::fail(
        outcome: ChallengeVerification::OUTCOME_SCORE_BELOW_THRESHOLD,
        expectedAction: 'corex_form_contact',
        effectiveThreshold: 0.3,
        score: 0.1,
        hostname: 'corex.local',
        safeReason: 'Score 0.10 is below the threshold of 0.30.',
    );

    $wire = $verification->toArray();

    // The array projection is the only thing that leaves the object. It must not
    // carry a token or a raw google response under any key.
    $flat = strtolower(wp_json_encode($wire) ?: '');
    expect($flat)->not->toContain('token')
        ->and($flat)->not->toContain('secret')
        ->and($wire)->toHaveKeys(['outcome', 'score', 'effective_threshold', 'expected_action', 'hostname', 'safe_reason'])
        ->and($wire)->not->toHaveKey('raw')
        ->and($wire)->not->toHaveKey('payload');
});

it('the exact-threshold token is a pass, not a rejection', function () {
    // Boundary: score == threshold must pass (FR-003 "greater than or equal to").
    $verification = ChallengeVerification::pass(
        score: 0.3,
        effectiveThreshold: 0.3,
        expectedAction: 'corex_form_contact',
        hostname: 'corex.local',
    );

    expect($verification->passed())->toBeTrue();
});
