<?php

/**
 * Unit tests for the envelope → HTTP status mapping (spec 043: data-model status table).
 * The thin `toRest()` WordPress wrapping is exercised by the integration suite.
 *
 * @package Corex\Tests\Unit\Http
 */

declare(strict_types=1);

use Corex\Http\EnvelopeResponder;
use Corex\Http\ResponseEnvelope;

beforeEach(function () {
    $this->responder = new EnvelopeResponder();
});

it('maps a success to 200', function () {
    expect($this->responder->status(ResponseEnvelope::success()))->toBe(200);
});

it('maps a validation error to 422', function () {
    expect($this->responder->status(ResponseEnvelope::validation(['a' => 'b'])))->toBe(422);
});

it('maps a forbidden error to 403', function () {
    expect($this->responder->status(ResponseEnvelope::error('forbidden', 'No.')))->toBe(403);
});

it('maps any other error to 400', function () {
    expect($this->responder->status(ResponseEnvelope::error('captcha_failed', 'No.')))->toBe(400);
});
