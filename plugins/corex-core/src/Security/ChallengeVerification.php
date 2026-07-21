<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Security;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * The typed verdict of an anti-automation challenge.
 *
 * A boolean cannot say *why* a submission failed, which is why a score threshold and an
 * action label could be stored but never enforced — there was nowhere to carry the score.
 * This value object is that place. It is deliberately secret-free: it never holds the raw
 * token or the raw provider payload, so it is safe to persist as submission evidence and to
 * surface (redacted) to an administrator.
 */
final class ChallengeVerification
{
    public const OUTCOME_PASSED                = 'passed';
    public const OUTCOME_TOKEN_MISSING         = 'token_missing';
    public const OUTCOME_PROVIDER_ERROR        = 'provider_error';
    public const OUTCOME_MALFORMED_RESPONSE    = 'malformed_response';
    public const OUTCOME_PROVIDER_REJECTED     = 'provider_rejected';
    public const OUTCOME_HOSTNAME_MISMATCH     = 'hostname_mismatch';
    public const OUTCOME_ACTION_MISMATCH       = 'action_mismatch';
    public const OUTCOME_TOKEN_EXPIRED         = 'token_expired';
    public const OUTCOME_TOKEN_REPLAYED        = 'token_replayed';
    public const OUTCOME_SCORE_BELOW_THRESHOLD = 'score_below_threshold';
    public const OUTCOME_NOT_CONFIGURED        = 'not_configured';

    private const OUTCOMES = [
        self::OUTCOME_PASSED,
        self::OUTCOME_TOKEN_MISSING,
        self::OUTCOME_PROVIDER_ERROR,
        self::OUTCOME_MALFORMED_RESPONSE,
        self::OUTCOME_PROVIDER_REJECTED,
        self::OUTCOME_HOSTNAME_MISMATCH,
        self::OUTCOME_ACTION_MISMATCH,
        self::OUTCOME_TOKEN_EXPIRED,
        self::OUTCOME_TOKEN_REPLAYED,
        self::OUTCOME_SCORE_BELOW_THRESHOLD,
        self::OUTCOME_NOT_CONFIGURED,
    ];

    /**
     * @param float|null $score              Provider confidence (0.0–1.0) when the provider scores; null otherwise.
     * @param float      $effectiveThreshold The threshold actually applied — recorded so the admin sees which value judged the request.
     * @param string     $expectedAction     The server-derived action the token was checked against.
     * @param string|null $hostname          The hostname the provider reported (safe to store).
     * @param string     $safeReason         An admin-facing, credential-free explanation.
     */
    private function __construct(
        public readonly string $outcome,
        public readonly ?float $score,
        public readonly float $effectiveThreshold,
        public readonly string $expectedAction,
        public readonly ?string $hostname,
        public readonly string $safeReason,
    ) {
        if (! in_array($this->outcome, self::OUTCOMES, true)) {
            throw new InvalidArgumentException('Unknown challenge verification outcome: ' . $this->outcome);
        }
    }

    public static function pass(
        float $score,
        float $effectiveThreshold,
        string $expectedAction,
        ?string $hostname,
    ): self {
        return new self(
            self::OUTCOME_PASSED,
            $score,
            $effectiveThreshold,
            $expectedAction,
            $hostname,
            'Passed automated verification.',
        );
    }

    public static function fail(
        string $outcome,
        string $expectedAction,
        float $effectiveThreshold,
        string $safeReason,
        ?float $score = null,
        ?string $hostname = null,
    ): self {
        if ($outcome === self::OUTCOME_PASSED) {
            throw new InvalidArgumentException('A failed verification cannot carry the passed outcome.');
        }

        return new self($outcome, $score, $effectiveThreshold, $expectedAction, $hostname, $safeReason);
    }

    public function passed(): bool
    {
        return $this->outcome === self::OUTCOME_PASSED;
    }

    /**
     * The secret-free wire projection. This is the only representation that leaves the
     * object; it exposes no token and no raw provider response by construction.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'outcome'             => $this->outcome,
            'score'               => $this->score,
            'effective_threshold' => $this->effectiveThreshold,
            'expected_action'     => $this->expectedAction,
            'hostname'            => $this->hostname,
            'safe_reason'         => $this->safeReason,
        ];
    }
}
