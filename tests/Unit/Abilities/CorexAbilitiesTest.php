<?php

/**
 * Unit tests for the (pure) Corex abilities data — block discovery filters to corex/*,
 * and the site-info summary has the expected shape.
 *
 * @package Corex\Tests\Unit\Abilities
 */

declare(strict_types=1);

use Corex\Abilities\CorexAbilities;

it('lists only corex/* blocks with their titles', function () {
    $registered = [
        'corex/posts'  => (object) ['title' => 'Corex Posts'],
        'core/paragraph' => (object) ['title' => 'Paragraph'],
        'corex/form'   => (object) ['title' => 'Corex Form'],
    ];

    $blocks = (new CorexAbilities())->blocks($registered);

    expect($blocks)->toBe([
        ['name' => 'corex/posts', 'title' => 'Corex Posts'],
        ['name' => 'corex/form', 'title' => 'Corex Form'],
    ]);
});

it('falls back to the block name when a title is missing', function () {
    $blocks = (new CorexAbilities())->blocks(['corex/x' => (object) []]);

    expect($blocks[0])->toBe(['name' => 'corex/x', 'title' => 'corex/x']);
});

it('summarises the site/framework', function () {
    $info = (new CorexAbilities())->siteInfo('My Site', '1.2.3', 6);

    expect($info)->toBe([
        'name'         => 'My Site',
        'framework'    => 'Corex',
        'version'      => '1.2.3',
        'corex_blocks' => 6,
    ]);
});
