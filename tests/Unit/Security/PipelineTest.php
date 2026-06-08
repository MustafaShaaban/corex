<?php

/**
 * Unit tests for the middleware pipeline (spec US1: FR-002–FR-004, FR-006, SC-001, SC-004).
 *
 * @package Corex\Tests\Unit\Security
 */

declare(strict_types=1);

use Corex\Http\Middleware\Middleware;
use Corex\Http\Middleware\Pipeline;
use Corex\Http\Middleware\Request;
use Corex\Http\Middleware\Response;
use Corex\Support\BootLogger;

/** A middleware that records its name then passes through. */
function recordingMiddleware(ArrayObject $log, string $name): Middleware
{
    return new class($log, $name) implements Middleware {
        public function __construct(private readonly ArrayObject $log, private readonly string $name)
        {
        }

        public function process(Request $request, callable $next): Response
        {
            $this->log->append($this->name);

            return $next($request);
        }
    };
}

it('runs middleware outer-to-inner, then the handler', function () {
    $log = new ArrayObject();
    $handler = function (Request $r) use ($log): Response {
        $log->append('handler');

        return Response::ok('done');
    };

    $response = (new Pipeline(new BootLogger(debug: false)))->run(
        new Request('POST'),
        $handler,
        recordingMiddleware($log, 'a'),
        recordingMiddleware($log, 'b'),
    );

    expect($response->isOk())->toBeTrue()
        ->and($response->value)->toBe('done')
        ->and($log->getArrayCopy())->toBe(['a', 'b', 'handler']);
});

it('short-circuits: a rejecting middleware stops inner middleware and the handler', function () {
    $log = new ArrayObject();
    $rejecting = new class implements Middleware {
        public function process(Request $request, callable $next): Response
        {
            return Response::reject('nope');
        }
    };
    $handler = function (Request $r) use ($log): Response {
        $log->append('handler');

        return Response::ok();
    };

    $response = (new Pipeline(new BootLogger(debug: false)))->run(
        new Request('POST'),
        $handler,
        $rejecting,
        recordingMiddleware($log, 'inner'),
    );

    expect($response->isOk())->toBeFalse()
        ->and($log->getArrayCopy())->toBe([]); // neither inner nor handler ran
});

it('runs the handler directly when the middleware list is empty', function () {
    $response = (new Pipeline(new BootLogger(debug: false)))->run(
        new Request('GET'),
        fn (Request $r): Response => Response::ok('h'),
    );

    expect($response->value)->toBe('h');
});

it('fails closed and logs when a middleware throws (handler never runs)', function () {
    $logger = new BootLogger(debug: false);
    $ran = new ArrayObject();
    $throwing = new class implements Middleware {
        public function process(Request $request, callable $next): Response
        {
            throw new RuntimeException('boom');
        }
    };

    $response = (new Pipeline($logger))->run(
        new Request('POST'),
        function (Request $r) use ($ran): Response {
            $ran->append('handler');

            return Response::ok();
        },
        $throwing,
    );

    expect($response->isOk())->toBeFalse()
        ->and($logger->messages())->not->toBeEmpty()
        ->and($ran->getArrayCopy())->toBe([]);
});
