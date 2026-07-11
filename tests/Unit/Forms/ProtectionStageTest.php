<?php

/**
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Forms\Flow\Flow;
use Corex\Forms\Flow\FlowConfiguration;
use Corex\Forms\Flow\FlowVersion;
use Corex\Forms\Submission\Stages\ProtectionStage;
use Corex\Forms\Submission\SubmissionPipelineContext;
use Corex\Security\ChallengeVerifier;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

function protectionContext(array $values): SubmissionPipelineContext
{
    $now = new DateTimeImmutable('2026-07-04T12:00:00+00:00');
    $flow = new Flow(7, '5e08b4c1-7c00-49fb-b854-17bf309755bb', 'protected', 'Protected', '', 'published', 1, 'none', null, 1, 1, false, 1, 1, $now, $now);
    $version = new FlowVersion(9, 7, 1, new FlowConfiguration([], [], [], [], [], []), 1, $now);

    return new SubmissionPipelineContext($flow, $version, $values, false);
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
