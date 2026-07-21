<?php

/**
 * @package Corex\Captcha
 */

declare(strict_types=1);

namespace Corex\Captcha;

defined('ABSPATH') || exit;

use Corex\Security\ChallengeContext;
use Corex\Security\ChallengeVerification;
use Corex\Security\VerifyingChallenge;

/**
 * Google reCAPTCHA v3 driver.
 *
 * Where {@see RemoteCaptcha} reads only `success` — which is why the score threshold and
 * action settings had no effect — this driver reads the whole v3 response and returns a typed
 * verdict. It fails closed: every failure path yields a non-passed {@see ChallengeVerification},
 * never an exception to the caller and never a silent pass.
 *
 * The checks run in a deliberate order (research.md R3). Replay is checked **last**, so a token
 * rejected for any earlier reason is never recorded — a forged token cannot poison the replay
 * store, and a token rejected only on score stays replayable if the threshold is later lowered.
 */
final class RecaptchaV3Captcha implements Captcha, VerifyingChallenge
{
    /** Google v3 tokens are valid for two minutes; match that rather than being stricter. */
    private const MAX_AGE_SECONDS = 120;

    public function __construct(
        private readonly string $verifyUrl,
        private readonly string $secret,
        private readonly TokenReplayGuard $replay,
    ) {
    }

    /**
     * Legacy boolean contract. It cannot judge a v3 token meaningfully — with no server-side
     * expectations it fails closed on the hostname check — so it exists only to satisfy the
     * {@see Captcha} interface. Real verification goes through {@see challenge()}, which
     * {@see \Corex\Forms\Submission\Stages\ProtectionStage} selects via `instanceof VerifyingChallenge`.
     *
     * @param array<string,mixed> $context
     */
    public function verify(string $token, array $context = []): bool
    {
        return $this->challenge($token, new ChallengeContext('', 0.0, []))->passed();
    }

    public function challenge(string $token, ChallengeContext $context): ChallengeVerification
    {
        $threshold = $context->threshold;
        $action = $context->expectedAction;

        if ($this->secret === '' || $token === '') {
            return ChallengeVerification::fail(
                ChallengeVerification::OUTCOME_TOKEN_MISSING,
                $action,
                $threshold,
                __('No verification token was supplied.', 'corex'),
            );
        }

        $response = wp_remote_post($this->verifyUrl, [
            'timeout' => 10,
            'body'    => array_filter([
                'secret'   => $this->secret,
                'response' => $token,
                'remoteip' => $context->remoteIp,
            ], static fn (?string $v): bool => $v !== null && $v !== ''),
        ]);

        if (is_wp_error($response)) {
            return ChallengeVerification::fail(
                ChallengeVerification::OUTCOME_PROVIDER_ERROR,
                $action,
                $threshold,
                __('The verification provider could not be reached.', 'corex'),
            );
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if (! is_array($body)) {
            return ChallengeVerification::fail(
                ChallengeVerification::OUTCOME_MALFORMED_RESPONSE,
                $action,
                $threshold,
                __('The verification provider returned an unreadable response.', 'corex'),
            );
        }

        if (($body['success'] ?? false) !== true) {
            return ChallengeVerification::fail(
                ChallengeVerification::OUTCOME_PROVIDER_REJECTED,
                $action,
                $threshold,
                __('The verification provider rejected the request.', 'corex'),
            );
        }

        $hostname = isset($body['hostname']) ? (string) $body['hostname'] : null;
        $allowed = new CaptchaHostnamePolicy($context->allowedHostnames);
        if ($hostname === null || ! $allowed->allows($hostname)) {
            return ChallengeVerification::fail(
                ChallengeVerification::OUTCOME_HOSTNAME_MISMATCH,
                $action,
                $threshold,
                __('The verification came from an unexpected site address.', 'corex'),
                hostname: $hostname,
            );
        }

        if ((string) ($body['action'] ?? '') !== $action) {
            return ChallengeVerification::fail(
                ChallengeVerification::OUTCOME_ACTION_MISMATCH,
                $action,
                $threshold,
                __('The verification was issued for a different form action.', 'corex'),
                hostname: $hostname,
            );
        }

        if ($this->isExpired($body['challenge_ts'] ?? null)) {
            return ChallengeVerification::fail(
                ChallengeVerification::OUTCOME_TOKEN_EXPIRED,
                $action,
                $threshold,
                __('The verification token had expired.', 'corex'),
                hostname: $hostname,
            );
        }

        $score = isset($body['score']) ? (float) $body['score'] : 0.0;
        if ($score < $threshold) {
            return ChallengeVerification::fail(
                ChallengeVerification::OUTCOME_SCORE_BELOW_THRESHOLD,
                $action,
                $threshold,
                sprintf(
                    /* translators: 1: measured score, 2: configured threshold. */
                    __('Confidence score %1$.2f is below the threshold of %2$.2f.', 'corex'),
                    $score,
                    $threshold,
                ),
                score: $score,
                hostname: $hostname,
            );
        }

        // Replay is the final gate: only a token that has passed every other check is recorded,
        // so nothing rejected above is ever consumed.
        if (! $this->replay->consume($token)) {
            return ChallengeVerification::fail(
                ChallengeVerification::OUTCOME_TOKEN_REPLAYED,
                $action,
                $threshold,
                __('This verification token has already been used.', 'corex'),
                score: $score,
                hostname: $hostname,
            );
        }

        return ChallengeVerification::pass($score, $threshold, $action, $hostname);
    }

    private function isExpired(mixed $challengeTs): bool
    {
        if (! is_string($challengeTs) || $challengeTs === '') {
            return true; // no timestamp we can trust ⇒ treat as expired (fail closed)
        }

        $issued = strtotime($challengeTs);
        if ($issued === false) {
            return true;
        }

        return ((int) current_time('timestamp', true) - $issued) > self::MAX_AGE_SECONDS;
    }
}
