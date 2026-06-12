<?php

/**
 * Regression test for the spec-039 FieldSections seam (the wiring the unit tests bypass): the
 * container must be able to autowire SettingsForm once FieldSections is bound to SettingsRegistry,
 * the way ConfigServiceProvider does. Without the binding, booting failed at runtime.
 *
 * @package Corex\Tests\Unit\Options
 */

declare(strict_types=1);

use Corex\Config\Settings\FieldSections;
use Corex\Config\Settings\SettingsForm;
use Corex\Config\Settings\SettingsRegistry;
use Corex\Container\Container;
use Corex\Container\Exceptions\BindingResolutionException;

it('autowires SettingsForm when FieldSections is bound to the concrete registry', function () {
    $container = new Container();
    $container->bind(
        FieldSections::class,
        static fn (Container $c): FieldSections => $c->make(SettingsRegistry::class),
    );

    expect($container->make(SettingsForm::class))->toBeInstanceOf(SettingsForm::class);
});

it('fails to autowire SettingsForm without a FieldSections binding (the bug we fixed)', function () {
    (new Container())->make(SettingsForm::class);
})->throws(BindingResolutionException::class);
