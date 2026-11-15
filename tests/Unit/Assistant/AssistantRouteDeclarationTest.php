<?php

/**
 * What the Assistant's chat route declares to the REST server (spec 124, FR-004a, DECISIONS #522).
 *
 * The chat is not a public submission, so the gate that closes forms in Maintenance and Forms
 * paused lets it through; its hand-off posts to the forms route, which is one, so the gate refuses
 * it. The integration tests that prove this on the REST server skip wherever the add-on is not
 * active, which includes CI; these read the declaration and the wiring themselves, everywhere.
 *
 * @package Corex\Tests\Unit\Assistant
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Assistant\AssistantServiceProvider;
use Corex\Assistant\AssistantSettings;
use Corex\Assistant\Forms\AssistantCallbackForm;
use Corex\Assistant\Front\AssistantButton;
use Corex\Assistant\Front\AssistantStrings;
use Corex\Assistant\Http\AssistantController;
use Corex\Container\Container;
use Corex\Container\ContainerInterface;
use Corex\Forms\FormRegistry;
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

it('sends the hand-off to the forms route of its callback form, which the gate closes (FR-004a)', function () {
    Functions\when('rest_url')->alias(static fn (string $path): string => 'https://site.test/wp-json/' . $path);
    $container = new Container();
    $container->instance(ContainerInterface::class, $container);
    $container->instance(FormRegistry::class, (new ReflectionClass(FormRegistry::class))->newInstanceWithoutConstructor());

    (new AssistantServiceProvider($container))->register();
    // The button's own reads are not under test: only the endpoint its hand-off posts to.
    foreach ([AssistantSettings::class, AssistantStrings::class] as $read) {
        $container->instance($read, (new ReflectionClass($read))->newInstanceWithoutConstructor());
    }

    $form = (new ReflectionProperty(AssistantButton::class, 'form'))->getValue($container->make(AssistantButton::class));

    expect($form())->toBe('https://site.test/wp-json/corex/v1/forms/' . AssistantCallbackForm::SLUG);
});
