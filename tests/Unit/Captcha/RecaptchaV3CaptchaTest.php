<?php

/**
 * Unit tests for reCAPTCHA v3 verification (spec 071 US1: FR-003, FR-004, FR-007, FR-010, FR-011).
 *
 * The full verdict matrix from research.md R3, in the order the driver checks them. Each row
 * proves the driver fails closed for exactly one reason and reports it as a typed outcome —
 * which is what makes the score threshold and action settings finally mean something.
 *
 * @package Corex\Tests\Unit\Captcha
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Captcha\CaptchaHostnamePolicy;
use Corex\Captcha\RecaptchaV3Captcha;
use Corex\Captcha\TokenReplayGuard;
use Corex\Security\ChallengeContext;
use Corex\Security\ChallengeVerification;

/** A replay guard that always reports a token as fresh, so replay never interferes. */
function freshReplayGuard(): TokenReplayGuard
{
    return new class extends TokenReplayGuard {
        public function consume(string $token): bool
        {
            return true;
        }
    };
}

/** Build the driver with a stub replay guard and a fixed allowlist. */
function v3Driver(?TokenReplayGuard $replay = null): RecaptchaV3Captcha
{
    return new RecaptchaV3Captcha(
        'https://www.google.com/recaptcha/api/siteverify',
        'a-secret',
        $replay ?? freshReplayGuard(),
    );
}

function v3Context(): ChallengeContext
{
    return new ChallengeContext(
        expectedAction: 'corex_form_contact',
        threshold: 0.3,
        allowedHostnames: ['corex.local'],
        remoteIp: '203.0.113.9',
    );
}

/** Make wp_remote_post return this decoded body as JSON. */
function stubProvider(array $body): void
{
    Functions\when('is_wp_error')->justReturn(false);
    Functions\when('wp_remote_post')->justReturn(['body' => wp_json_encode($body)]);
    Functions\when('wp_remote_retrieve_body')->justReturn(wp_json_encode($body));
}

beforeEach(function () {
    Functions\when('wp_json_encode')->alias(static fn (mixed $d): string => (string) json_encode($d));
    Functions\when('__')->returnArg();
    // A recent timestamp so the expiry check passes unless a test overrides it.
    Functions\when('current_time')->justReturn(time());
});

// ---- pass -----------------------------------------------------------------

it('passes a valid, well-scored token', function () {
    stubProvider([
        'success' => true, 'score' => 0.9, 'action' => 'corex_form_contact',
        'hostname' => 'corex.local', 'challenge_ts' => gmdate('c'),
    ]);

    $v = v3Driver()->challenge('token', v3Context());

    expect($v->passed())->toBeTrue()
        ->and($v->outcome)->toBe(ChallengeVerification::OUTCOME_PASSED)
        ->and($v->score)->toBe(0.9);
});

it('accepts a score exactly on the threshold', function () {
    stubProvider([
        'success' => true, 'score' => 0.3, 'action' => 'corex_form_contact',
        'hostname' => 'corex.local', 'challenge_ts' => gmdate('c'),
    ]);

    expect(v3Driver()->challenge('token', v3Context())->passed())->toBeTrue();
});

// ---- fail closed, one reason each -----------------------------------------

it('rejects a missing token before calling the provider', function () {
    // No provider stub — if the driver calls out, this test errors instead of asserting.
    $v = v3Driver()->challenge('', v3Context());
    expect($v->outcome)->toBe(ChallengeVerification::OUTCOME_TOKEN_MISSING);
});

it('reports a provider transport error', function () {
    Functions\when('is_wp_error')->justReturn(true);
    Functions\when('wp_remote_post')->justReturn((object) ['error' => 'boom']); // WP_Error stand-in

    $v = v3Driver()->challenge('token', v3Context());
    expect($v->outcome)->toBe(ChallengeVerification::OUTCOME_PROVIDER_ERROR);
});

it('reports a malformed provider body', function () {
    Functions\when('is_wp_error')->justReturn(false);
    Functions\when('wp_remote_post')->justReturn(['body' => 'not json']);
    Functions\when('wp_remote_retrieve_body')->justReturn('not json');

    $v = v3Driver()->challenge('token', v3Context());
    expect($v->outcome)->toBe(ChallengeVerification::OUTCOME_MALFORMED_RESPONSE);
});

it('reports a provider rejection when success is false', function () {
    stubProvider(['success' => false, 'error-codes' => ['invalid-input-response']]);

    $v = v3Driver()->challenge('token', v3Context());
    expect($v->outcome)->toBe(ChallengeVerification::OUTCOME_PROVIDER_REJECTED);
});

it('rejects a hostname that is not exactly allowed', function () {
    stubProvider([
        'success' => true, 'score' => 0.9, 'action' => 'corex_form_contact',
        'hostname' => 'corex.local.evil.com', 'challenge_ts' => gmdate('c'),
    ]);

    $v = v3Driver()->challenge('token', v3Context());
    expect($v->outcome)->toBe(ChallengeVerification::OUTCOME_HOSTNAME_MISMATCH);
});

it('rejects an action that does not match the server expectation', function () {
    stubProvider([
        'success' => true, 'score' => 0.9, 'action' => 'some_other_action',
        'hostname' => 'corex.local', 'challenge_ts' => gmdate('c'),
    ]);

    $v = v3Driver()->challenge('token', v3Context());
    expect($v->outcome)->toBe(ChallengeVerification::OUTCOME_ACTION_MISMATCH);
});

it('rejects a stale token', function () {
    stubProvider([
        'success' => true, 'score' => 0.9, 'action' => 'corex_form_contact',
        'hostname' => 'corex.local', 'challenge_ts' => gmdate('c', time() - 3600),
    ]);

    $v = v3Driver()->challenge('token', v3Context());
    expect($v->outcome)->toBe(ChallengeVerification::OUTCOME_TOKEN_EXPIRED);
});

it('rejects a replayed token — replay is the last check, so only valid tokens reach it', function () {
    stubProvider([
        'success' => true, 'score' => 0.9, 'action' => 'corex_form_contact',
        'hostname' => 'corex.local', 'challenge_ts' => gmdate('c'),
    ]);
    $usedGuard = new class extends TokenReplayGuard {
        public function consume(string $token): bool
        {
            return false; // already seen
        }
    };

    $v = v3Driver($usedGuard)->challenge('token', v3Context());
    expect($v->outcome)->toBe(ChallengeVerification::OUTCOME_TOKEN_REPLAYED);
});

it('rejects a below-threshold score and names the numbers safely', function () {
    stubProvider([
        'success' => true, 'score' => 0.1, 'action' => 'corex_form_contact',
        'hostname' => 'corex.local', 'challenge_ts' => gmdate('c'),
    ]);

    $v = v3Driver()->challenge('token', v3Context());
    expect($v->outcome)->toBe(ChallengeVerification::OUTCOME_SCORE_BELOW_THRESHOLD)
        ->and($v->score)->toBe(0.1)
        ->and($v->effectiveThreshold)->toBe(0.3);
});

it('does not consume the replay slot for a token rejected on score', function () {
    // A token rejected for score must stay replayable so a threshold change lets it retry.
    // Replay is the final check, so a score failure never reaches it.
    stubProvider([
        'success' => true, 'score' => 0.1, 'action' => 'corex_form_contact',
        'hostname' => 'corex.local', 'challenge_ts' => gmdate('c'),
    ]);
    $consumed = false;
    $guard = new class($consumed) extends TokenReplayGuard {
        public function __construct(private bool &$flag)
        {
        }

        public function consume(string $token): bool
        {
            $this->flag = true;
            return true;
        }
    };

    v3Driver($guard)->challenge('token', v3Context());
    expect($consumed)->toBeFalse(); // score (step 8) rejected it before replay (step 9)
});
