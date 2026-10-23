<?php

/**
 * Unit tests for the translated names of CoreX abilities, areas and risks (spec 117, US-ACC-05).
 *
 * @package Corex\Tests\Unit\Access
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Access\AbilityNames;
use Corex\Access\CorexAbility;
use Corex\Access\CorexAbilityCatalog;

beforeEach(function () {
    // A translation that marks what it translated, so a name that skipped the translation
    // functions shows up as unmarked.
    Functions\when('__')->alias(static fn (string $text): string => '[ar] ' . $text);
});

it('gives every shipped ability a translated name and description', function () {
    foreach (CorexAbilityCatalog::defaults()->all() as $ability) {
        expect(AbilityNames::labelOf($ability))->toBe('[ar] ' . $ability->label)
            ->and(AbilityNames::descriptionOf($ability))->toBe('[ar] ' . $ability->description);
    }
});

it('gives every area a translated name and never its key', function () {
    $areas = array_keys(CorexAbilityCatalog::defaults()->grouped());

    foreach ($areas as $area) {
        expect(AbilityNames::area($area))->toStartWith('[ar] ')
            ->and(AbilityNames::area($area))->not->toContain($area);
    }
});

it('names each risk in words', function (string $risk, string $word) {
    expect(AbilityNames::risk($risk))->toBe('[ar] ' . $word);
})->with([
    [CorexAbility::RISK_SENSITIVE, 'Sensitive'],
    [CorexAbility::RISK_DANGEROUS, 'Dangerous'],
    [CorexAbility::RISK_CRITICAL, 'Critical'],
    [CorexAbility::RISK_NORMAL, 'Normal'],
]);

it('keeps the registered text of an ability CoreX does not ship', function () {
    $ability = new CorexAbility(
        'corex_manage_bookings_desk',
        'Run the bookings desk',
        'Answer booking requests.',
        CorexAbility::GROUP_SETTINGS,
        CorexAbility::RISK_SENSITIVE,
        false,
        [],
        [],
        [],
    );

    expect(AbilityNames::labelOf($ability))->toBe('Run the bookings desk')
        ->and(AbilityNames::descriptionOf($ability))->toBe('Answer booking requests.')
        ->and(AbilityNames::label('corex_unknown'))->toBe('corex_unknown');
});
