<?php

/**
 * What the Assistant's chat route declares to the REST server (spec 124, FR-004a, DECISIONS #522).
 *
 * The chat is not a public submission, so the gate that closes forms in Maintenance and Forms
 * paused lets it through. The integration tests that prove this on the REST server skip wherever
 * the add-on is not active, which includes CI; this one reads the declaration itself, everywhere.
 *
 * @package Corex\Tests\Unit\Assistant
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Assistant\Http\AssistantController;
use Corex\Http\PublicSubmission;

it('declares the chat route without the public submission marker, and keeps it public (FR-004a)', function () {
    $declared = [];
    Functions\when('register_rest_route')->alias(
        static function (string $namespace, string $route, array $args) use (&$declared): bool {
            $declared[$namespace . $route] = $args;

            return true;
        },
    );

    // register() reads nothing it was given, so the controller is made without its services.
    (new ReflectionClass(AssistantController::class))->newInstanceWithoutConstructor()->register();

    $args = $declared[AssistantController::ROUTE_NAMESPACE . AssistantController::ROUTE] ?? [];

    expect($args)->not->toBe([])
        ->and(PublicSubmission::isDeclaredBy($args))->toBeFalse()
        ->and($args['permission_callback'] ?? null)->toBe('__return_true');
});
