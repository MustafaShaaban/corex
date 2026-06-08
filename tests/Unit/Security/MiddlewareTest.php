<?php

/**
 * Unit tests for the four core middleware (spec US2: FR-007–FR-010, SC-002).
 * WordPress security functions are stubbed at the boundary.
 *
 * @package Corex\Tests\Unit\Security
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Http\Middleware\CapabilityMiddleware;
use Corex\Http\Middleware\NonceMiddleware;
use Corex\Http\Middleware\Request;
use Corex\Http\Middleware\Response;
use Corex\Http\Middleware\SanitizeMiddleware;
use Corex\Http\Middleware\ThrottleMiddleware;

function passNext(): callable
{
    return fn (Request $r): Response => Response::ok('handler');
}

it('nonce: passes GET, rejects a POST without a valid nonce, passes with a valid one', function () {
    Functions\when('wp_verify_nonce')->alias(fn ($nonce, $action) => $nonce === 'good' ? 1 : false);
    $mw = new NonceMiddleware();

    expect($mw->process(new Request('GET'), passNext())->isOk())->toBeTrue()
        ->and($mw->process(new Request('POST', [], 'bad', 'act'), passNext())->isOk())->toBeFalse()
        ->and($mw->process(new Request('POST', [], 'good', 'act'), passNext())->isOk())->toBeTrue();
});

it('capability: rejects without the capability, passes with it', function () {
    Functions\when('current_user_can')->alias(fn ($cap) => $cap === 'edit_posts');

    expect((new CapabilityMiddleware('edit_posts'))->process(new Request('GET'), passNext())->isOk())->toBeTrue()
        ->and((new CapabilityMiddleware('manage_options'))->process(new Request('GET'), passNext())->isOk())->toBeFalse();
});

it('throttle: passes within the limit, rejects over it', function () {
    $count = 0;
    Functions\when('get_transient')->alias(function ($k) use (&$count) {
        return $count;
    });
    Functions\when('set_transient')->alias(function ($k, $v, $w) use (&$count) {
        $count = $v;

        return true;
    });
    $mw = new ThrottleMiddleware(limit: 2, window: 60);

    expect($mw->process(new Request('POST', [], '', '', 'k'), passNext())->isOk())->toBeTrue()   // 0 < 2
        ->and($mw->process(new Request('POST', [], '', '', 'k'), passNext())->isOk())->toBeTrue() // 1 < 2
        ->and($mw->process(new Request('POST', [], '', '', 'k'), passNext())->isOk())->toBeFalse(); // 2 >= 2
});

it('sanitize: passes the handler only the cleaned, expected shape', function () {
    Functions\when('sanitize_text_field')->returnArg();
    $captured = new ArrayObject();
    $next = function (Request $r) use ($captured): Response {
        $captured->exchangeArray($r->input);

        return Response::ok();
    };

    (new SanitizeMiddleware(['title' => 'sanitize_text_field']))
        ->process(new Request('POST', ['title' => 'Hi', 'evil' => '<script>']), $next);

    expect($captured->getArrayCopy())->toBe(['title' => 'Hi']); // 'evil' dropped
});
