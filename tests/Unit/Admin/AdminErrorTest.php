<?php

/**
 * Unit tests for the admin error model (spec 083). No WordPress.
 *
 * Contract: a refusal is classified from its status and from which upstream hook fired, never from
 * the words WordPress used — because WordPress uses several different sentences for the same event
 * and every one of them is translated.
 *
 * @package Corex\Tests\Unit\Admin
 */

declare(strict_types=1);

use Corex\Admin\Errors\AdminError;
use Corex\Admin\Errors\AdminErrorClassifier;
use Corex\Admin\Errors\AdminErrorKind;

beforeEach(function () {
    $this->classifier = new AdminErrorClassifier();
});

it('classifies a refusal from its status when no hook has spoken', function () {
    expect($this->classifier->classify(403))->toBe(AdminErrorKind::Denied)
        ->and($this->classifier->classify(404))->toBe(AdminErrorKind::NotFound)
        ->and($this->classifier->classify(401))->toBe(AdminErrorKind::Session)
        ->and($this->classifier->classify(429))->toBe(AdminErrorKind::RateLimited)
        ->and($this->classifier->classify(503))->toBe(AdminErrorKind::Unavailable);
});

it('falls back to a plain failure for a status it has no reading of', function () {
    expect($this->classifier->classify(500))->toBe(AdminErrorKind::Failed)
        ->and($this->classifier->classify(null))->toBe(AdminErrorKind::Failed)
        ->and($this->classifier->classify(0))->toBe(AdminErrorKind::Failed);
});

/**
 * The distinction the marker exists for. Both arrive as 403, and telling somebody their role lacks a
 * capability when their link simply went stale sends them to ask an administrator for access they
 * already hold.
 */
it('lets a failed nonce outrank the status it shares with a capability denial', function () {
    expect($this->classifier->classify(403, AdminErrorClassifier::MARKER_EXPIRED))
        ->toBe(AdminErrorKind::Expired);
});

it('reads a menu denial as denied even when the caller passed no status', function () {
    expect($this->classifier->classify(null, AdminErrorClassifier::MARKER_DENIED))
        ->toBe(AdminErrorKind::Denied);
});

it('gives every kind a status and a css-safe variant', function () {
    foreach (AdminErrorKind::cases() as $kind) {
        expect($kind->status())->toBeGreaterThanOrEqual(400)
            ->and($kind->variant())->toMatch('/^[a-z0-9-]+$/');
    }
});

/**
 * Every wither carries all seven fields forward, and changes exactly the one it names.
 *
 * This is the test the class was restructured for. Readonly properties mean each wither has to call
 * the seven-argument constructor, and three methods doing that independently is where an argument
 * quietly swaps places with its neighbour — two adjacent strings transpose and nothing complains
 * until a live error page shows the message where the title belongs. `derive()` made it one call
 * site; this checks the mapping that call site performs.
 */
it('changes one field per wither and carries the other six through', function () {
    $error = new AdminError(
        AdminErrorKind::Denied,
        'Title',
        'Message',
        403,
        [['label' => 'Back', 'url' => '/', 'primary' => true]],
    );

    $derived = $error
        ->withBody('<form></form>')
        ->withDetail('what the caller said')
        ->withActions([['label' => 'Sign in', 'url' => '/login', 'primary' => false]]);

    expect($derived->kind)->toBe(AdminErrorKind::Denied)
        ->and($derived->title)->toBe('Title')
        ->and($derived->message)->toBe('Message')
        ->and($derived->status)->toBe(403)
        ->and($derived->bodyHtml)->toBe('<form></form>')
        ->and($derived->hasBody())->toBeTrue()
        ->and($derived->detail)->toBe('what the caller said')
        ->and($derived->actions[0]['label'])->toBe('Sign in');

    // And the original is still the original — three derivations later.
    expect($error->hasBody())->toBeFalse()
        ->and($error->detail)->toBe('')
        ->and($error->actions[0]['label'])->toBe('Back');
});
