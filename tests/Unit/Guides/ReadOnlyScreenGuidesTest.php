<?php

/**
 * The guides of the screens that open read-only open for whoever can read those screens (spec 117,
 * FR-013): a guide that asked for "Access CoreX admin" would hide the Overview and Insights guides
 * from the holder of "See CoreX screens without changing them", who can open both screens.
 *
 * @package Corex\Tests\Unit\Guides
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Access\CorexAbility;
use Corex\Guides\Corex\CorexGuides;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

it('asks the Overview and Insights guides for the view ability, which "Access CoreX admin" implies (FR-013)', function () {
    $asked = [];
    foreach (CorexGuides::all() as $guide) {
        $asked[$guide->id] = $guide->capability;
    }

    expect(array_intersect_key($asked, array_flip(['corex-overview', 'corex-performance', 'corex-insights'])))->toBe([
        'corex-overview'    => CorexAbility::VIEW_SCREENS,
        'corex-insights'    => CorexAbility::VIEW_SCREENS,
        'corex-performance' => CorexAbility::VIEW_SCREENS,
    ]);
});
