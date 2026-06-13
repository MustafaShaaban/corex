<?php

/**
 * Unit tests for the canonical response envelope (spec 043: FR-001..FR-004).
 *
 * @package Corex\Tests\Unit\Http
 */

declare(strict_types=1);

use Corex\Http\ResponseEnvelope;

it('builds a success envelope with only ok/message/data', function () {
    $e = ResponseEnvelope::success(['id' => 5], 'Saved.');

    expect($e->ok)->toBeTrue()
        ->and($e->toArray())->toBe([
            'ok'      => true,
            'message' => 'Saved.',
            'data'    => ['id' => 5],
        ]);
});

it('defaults a success to an empty message and empty data', function () {
    expect(ResponseEnvelope::success()->toArray())->toBe([
        'ok'      => true,
        'message' => '',
        'data'    => [],
    ]);
});

it('builds a validation envelope with the fixed code and a field-error map', function () {
    $e = ResponseEnvelope::validation(['email' => 'Enter a valid email.'], 'Check the fields.');

    expect($e->ok)->toBeFalse()
        ->and($e->toArray())->toBe([
            'ok'      => false,
            'code'    => 'validation_failed',
            'message' => 'Check the fields.',
            'errors'  => ['email' => 'Enter a valid email.'],
            'details' => [],
        ]);
});

it('builds a general error envelope without an errors key', function () {
    $e = ResponseEnvelope::error('captcha_failed', 'Verification failed.', ['retry' => true]);

    expect($e->toArray())->toBe([
        'ok'      => false,
        'code'    => 'captcha_failed',
        'message' => 'Verification failed.',
        'details' => ['retry' => true],
    ]);
});

it('never carries data on an error or code/errors on a success', function () {
    expect(array_keys(ResponseEnvelope::success(['a' => 1])->toArray()))->not->toContain('code', 'errors')
        ->and(array_keys(ResponseEnvelope::error('x', 'y')->toArray()))->not->toContain('data');
});

it('exposes only contract keys for a validation error (no leaked fields)', function () {
    expect(array_keys(ResponseEnvelope::validation(['a' => 'b'])->toArray()))
        ->toBe(['ok', 'code', 'message', 'errors', 'details']);
});

it('is an immutable readonly value object', function () {
    $e = ResponseEnvelope::success();

    expect(fn () => $e->ok = false)->toThrow(Error::class);
});
