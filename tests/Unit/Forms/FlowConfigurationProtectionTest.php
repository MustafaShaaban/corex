<?php

/**
 * Unit tests for per-form protection config and checksum stability (spec 071 US1/US4: FR-023, FR-025).
 *
 * The load-bearing guarantee: adding the `protection` array must NOT change the checksum of a
 * flow version that does not use it, or every already-published version on every live site would
 * read as drifted. These tests pin that.
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Corex\Forms\Flow\FlowConfiguration;

/** A configuration built the old way — six arrays, no protection. */
function sixArrayConfig(): FlowConfiguration
{
    return new FlowConfiguration(
        schema: [['name' => 'email', 'type' => 'email']],
        validation: ['email' => ['required' => true]],
        routing: ['owner' => 'admin'],
        emailRoutes: [['event' => 'submitted', 'enabled' => true]],
        success: ['message' => 'Thanks'],
        placementSnapshot: ['type' => 'page'],
    );
}

it('defaults protection to an empty array', function () {
    expect(sixArrayConfig()->protection)->toBe([]);
});

it('hashes identically whether protection is defaulted or passed empty', function () {
    // A pre-feature version deserialises with no protection argument; a round-tripped one may
    // carry `protection: []`. Both must produce the same checksum a live site already stored —
    // i.e. an empty protection array is invisible to the hash.
    $defaulted = new FlowConfiguration(
        schema: [['name' => 'email', 'type' => 'email']],
        validation: ['email' => ['required' => true]],
        routing: ['owner' => 'admin'],
        emailRoutes: [['event' => 'submitted', 'enabled' => true]],
        success: ['message' => 'Thanks'],
        placementSnapshot: ['type' => 'page'],
    );
    $explicitEmpty = new FlowConfiguration(
        schema: [['name' => 'email', 'type' => 'email']],
        validation: ['email' => ['required' => true]],
        routing: ['owner' => 'admin'],
        emailRoutes: [['event' => 'submitted', 'enabled' => true]],
        success: ['message' => 'Thanks'],
        placementSnapshot: ['type' => 'page'],
        protection: [],
    );

    expect($explicitEmpty->checksum())->toBe($defaulted->checksum());
});

it('changes the checksum once a form actually declares protection', function () {
    // This also proves the block is stored and fed into the hash — a declared protection both
    // survives construction and changes the checksum, so a separate "constructor stores it" test
    // would add nothing (Rule 4).
    $plain = sixArrayConfig();
    $protected = new FlowConfiguration(
        schema: [['name' => 'email', 'type' => 'email']],
        validation: ['email' => ['required' => true]],
        routing: ['owner' => 'admin'],
        emailRoutes: [['event' => 'submitted', 'enabled' => true]],
        success: ['message' => 'Thanks'],
        placementSnapshot: ['type' => 'page'],
        protection: ['captcha' => 'on', 'threshold' => 0.7],
    );

    expect($protected->checksum())->not->toBe($plain->checksum())
        ->and($protected->protection)->toBe(['captcha' => 'on', 'threshold' => 0.7]);
});
