<?php

/**
 * The template part area that holds mega menu panels (spec 137 slice 3; FR-010; DECISIONS #691).
 *
 * The add-on registers the area `mega-menu`, "Mega menu panels", so panels are listed apart from
 * headers and footers in the Site Editor. With the add-on off nothing registers it.
 *
 * @package Corex\Tests\Unit\Ui
 */

declare(strict_types=1);

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Corex\Ui\Blocks\MegaMenuArea;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('_x')->returnArg();
});

it('FR-010: hooks the area into WordPress\'s list of template part areas', function () {
    $area = new MegaMenuArea();

    $area->register();

    expect(Filters\has('default_wp_template_part_areas', [$area, 'add']))->not->toBeFalse();
});

it('FR-010: adds the mega-menu area, named "Mega menu panels", after the areas WordPress has', function () {
    $areas = (new MegaMenuArea())->add([['area' => 'header', 'label' => 'Header']]);

    expect($areas)->toHaveCount(2)
        ->and($areas[0]['area'])->toBe('header')
        ->and($areas[1])->toMatchArray([
            'area' => 'mega-menu',
            'label' => 'Mega menu panels',
            'area_tag' => 'div',
        ])
        ->and($areas[1]['description'])->toBeString()->not->toBe('')
        ->and($areas[1]['icon'])->toBeString()->not->toBe('');
});

it('FR-010: adds the area once, when something registered it already', function () {
    $once = (new MegaMenuArea())->add([['area' => 'mega-menu', 'label' => 'Panels']]);

    expect($once)->toBe([['area' => 'mega-menu', 'label' => 'Panels']]);
});

it('FR-010: keeps a list it cannot read as it was', function () {
    expect((new MegaMenuArea())->add('not a list'))->toBe('not a list');
});
