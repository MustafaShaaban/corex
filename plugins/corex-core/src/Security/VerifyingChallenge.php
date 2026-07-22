<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Security;

defined('ABSPATH') || exit;

/**
 * Optional extension of the boolean {@see ChallengeVerifier} seam for providers that return a
 * scored, reasoned verdict (reCAPTCHA v3 and the like).
 *
 * Mirrors the Mailer → AttemptingMailer precedent: the legacy `verify(): bool` contract is
 * untouched, so honeypot and simple remote drivers keep working; a caller that needs a score
 * checks `instanceof VerifyingChallenge` and calls `challenge()` instead.
 *
 * Implementations MUST fail closed — a provider or transport failure returns a non-passed
 * verdict, never an exception to the caller — and MUST determine the expected action from the
 * context, never from the token.
 */
interface VerifyingChallenge extends ChallengeVerifier
{
    public function challenge(string $token, ChallengeContext $context): ChallengeVerification;
}
