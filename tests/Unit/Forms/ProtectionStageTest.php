<?php

/**
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Forms\Flow\Flow;
use Corex\Forms\Flow\FlowConfiguration;
use Corex\Forms\Flow\FlowVersion;
use Corex\Forms\Submission\FormChallengeContextFactory;
use Corex\Forms\Submission\Stages\ProtectionStage;
use Corex\Forms\Submission\SubmissionPipelineContext;
use Corex\Security\ChallengeContext;
use Corex\Security\ChallengeVerification;
use Corex\Security\ChallengeVerifier;
use Corex\Security\VerifyingChallenge;
use Corex\Support\Config\ConfigInterface;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('home_url')->justReturn('https://corex.local');
    Functions\when('wp_parse_url')->alias(static fn (string $url, int $c = -1) => parse_url($url, $c));
    Functions\when('wp_unslash')->returnArg();
    Functions\when('sanitize_text_field')->alias(static fn (string $v): string => trim($v));
    Functions\when('sanitize_key')->alias(static fn (string $k): string => (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower($k)));
});

/** @param array<string,mixed> $protection */
function protectionContext(array $values, array $protection = []): SubmissionPipelineContext
{
    $now = new DateTimeImmutable('2026-07-04T12:00:00+00:00');
    $flow = new Flow(7, '5e08b4c1-7c00-49fb-b854-17bf309755bb', 'protected', 'Protected', '', 'published', 1, 'none', null, 1, 1, false, 1, 1, $now, $now);
    $version = new FlowVersion(9, 7, 1, new FlowConfiguration([], [], [], [], [], [], $protection), 1, $now);

    return new SubmissionPipelineContext($flow, $version, $values, false);
}

/** @param array<string,mixed> $config */
function challengeContextFactory(array $config = []): FormChallengeContextFactory
{
    $reader = new class($config) implements ConfigInterface {
        /** @param array<string,mixed> $values */
        public function __construct(private array $values)
        {
        }

        public function get(string $key, mixed $default = null): mixed
        {
            return $this->values[$key] ?? $default;
        }

        public function has(string $key): bool
        {
            return array_key_exists($key, $this->values);
        }
    };

    return new FormChallengeContextFactory($reader);
}

it('verifies and removes a configured captcha token before storage', function () {
    $verifier = new class implements ChallengeVerifier {
        public function verify(string $token): bool
        {
            return $token === 'valid-token';
        }
    };
    $result = (new ProtectionStage($verifier))->execute(protectionContext([
        'email' => 'visitor@example.com',
        'captcha_token' => 'valid-token',
    ]));

    expect($result->failed())->toBeFalse()
        ->and($result->context->metadata['spam']['captcha'])->toBe('passed')
        ->and($result->context->values)->not->toHaveKey('captcha_token');
});

it('fails closed when the configured captcha rejects or receives no token', function (string $token) {
    $verifier = new class implements ChallengeVerifier {
        public function verify(string $token): bool
        {
            return false;
        }
    };
    $result = (new ProtectionStage($verifier))->execute(protectionContext(['captcha_token' => $token]));

    expect($result->failed())->toBeTrue()
        ->and($result->context->metadata['spam']['captcha'])->toBe('failed');
})->with(['invalid-token', '']);

it('records captcha as not configured when the optional verifier is absent', function () {
    $result = (new ProtectionStage())->execute(protectionContext(['email' => 'visitor@example.com']));

    expect($result->failed())->toBeFalse()
        ->and($result->context->metadata['spam']['captcha'])->toBe('not_configured');
});

// ---- typed (scored) path --------------------------------------------------

/**
 * A scored verifier that records the context it was judged against and returns whatever verdict
 * the test asked for — so we can assert the stage judges the token against the *server's*
 * expectations, not the request.
 */
function scoredVerifier(ChallengeVerification $verdict, ?ChallengeContext &$seen = null): VerifyingChallenge
{
    return new class($verdict, $seen) implements VerifyingChallenge {
        public function __construct(private ChallengeVerification $verdict, private ?ChallengeContext &$seen)
        {
        }

        public function verify(string $token): bool
        {
            return $this->verdict->passed();
        }

        public function challenge(string $token, ChallengeContext $context): ChallengeVerification
        {
            $this->seen = $context;
            return $this->verdict;
        }
    };
}

it('judges a scored token against the server-derived action and threshold, and persists the verdict', function () {
    $seen = null;
    $verdict = ChallengeVerification::pass(0.9, 0.3, 'corex_form_protected', 'corex.local');
    $factory = challengeContextFactory(['captcha.driver' => 'recaptcha', 'captcha.secret' => 's', 'captcha.allowed_hostnames' => 'corex.local']);

    $result = (new ProtectionStage(scoredVerifier($verdict, $seen), $factory))->execute(
        protectionContext(['email' => 'v@example.com', 'captcha_token' => 'tok'])
    );

    expect($result->failed())->toBeFalse()
        ->and($seen->expectedAction)->toBe('corex_form_protected')  // derived from the flow slug, server-side
        ->and($seen->threshold)->toBe(0.3)
        ->and($result->context->metadata['spam']['captcha'])->toBe('passed')
        ->and($result->context->metadata['spam']['captcha_detail']['outcome'])->toBe('passed')
        ->and($result->context->metadata['spam']['captcha_detail']['effective_threshold'])->toBe(0.3);
});

it('fails closed and records the typed reason when the score is below threshold', function () {
    $verdict = ChallengeVerification::fail(
        ChallengeVerification::OUTCOME_SCORE_BELOW_THRESHOLD,
        'corex_form_protected',
        0.3,
        'Confidence score 0.10 is below the threshold of 0.30.',
        score: 0.1,
    );
    $factory = challengeContextFactory(['captcha.driver' => 'recaptcha', 'captcha.secret' => 's']);

    $result = (new ProtectionStage(scoredVerifier($verdict), $factory))->execute(
        protectionContext(['captcha_token' => 'weak'])
    );

    expect($result->failed())->toBeTrue()
        ->and($result->context->metadata['spam']['captcha'])->toBe('failed')
        ->and($result->context->metadata['spam']['captcha_detail']['outcome'])->toBe('score_below_threshold');
});

it('applies a per-form threshold override rather than the global default', function () {
    $seen = null;
    $verdict = ChallengeVerification::pass(0.8, 0.7, 'corex_form_protected', 'corex.local');
    $factory = challengeContextFactory(['captcha.driver' => 'recaptcha', 'captcha.secret' => 's', 'captcha.score_threshold' => 0.3]);

    (new ProtectionStage(scoredVerifier($verdict, $seen), $factory))->execute(
        protectionContext(['captcha_token' => 'tok'], ['captcha' => 'on', 'threshold' => 0.7])
    );

    expect($seen->threshold)->toBe(0.7); // the form's override, not the global 0.3
});

it('skips the provider for a form that opts out, keeping only the honeypot', function () {
    // Even with a scored provider bound, a form set to captcha=off must not be captcha-judged.
    $verdict = ChallengeVerification::fail(ChallengeVerification::OUTCOME_PROVIDER_REJECTED, 'x', 0.3, 'no');
    $factory = challengeContextFactory(['captcha.driver' => 'recaptcha', 'captcha.secret' => 's']);

    $result = (new ProtectionStage(scoredVerifier($verdict), $factory))->execute(
        protectionContext(['email' => 'v@example.com'], ['captcha' => 'off'])
    );

    expect($result->failed())->toBeFalse()
        ->and($result->context->metadata['spam']['captcha'])->toBe('not_configured');
});

it('still fails a filled honeypot even when captcha passes', function () {
    $verdict = ChallengeVerification::pass(0.9, 0.3, 'corex_form_protected', 'corex.local');
    $factory = challengeContextFactory(['captcha.driver' => 'recaptcha', 'captcha.secret' => 's']);

    $result = (new ProtectionStage(scoredVerifier($verdict), $factory))->execute(
        protectionContext(['captcha_token' => 'tok', 'corex_hp' => 'i-am-a-bot'])
    );

    expect($result->failed())->toBeTrue()
        ->and($result->context->metadata['spam']['honeypot'])->toBe('failed');
});
