<?php

declare(strict_types=1);

use Corex\Tests\Support\ThemeContract;

it('defines complete ltr rtl and mixed-script fixtures', function () {
    $matrix = ThemeContract::json('tests/Fixtures/Theme/direction-matrix.json');

    expect($matrix['modes'])->toEqualCanonicalizing(['light', 'dark'])
        ->and($matrix['document_directions'])->toEqualCanonicalizing(['ltr', 'rtl'])
        ->and($matrix['checks'])->toContain(
            'shaping',
            'bidi-isolation',
            'logical-alignment',
            'keyboard-focus-order',
            'horizontal-overflow',
            'zoom-200',
        );

    $ids = array_column($matrix['fixtures'], 'id');
    expect($ids)->toContain(
        'arabic-prose',
        'english-prose',
        'mixed-product-name',
        'mixed-command',
        'arabic-western-numerals',
        'nested-direction',
    );

    foreach ($matrix['fixtures'] as $fixture) {
        expect($fixture)->toHaveKeys(['id', 'language', 'direction', 'content', 'context'])
            ->and($fixture['direction'])->toBeIn(['ltr', 'rtl']);

        if ( str_starts_with($fixture['id'], 'mixed-') || $fixture['id'] === 'nested-direction' ) {
            expect($fixture)->toHaveKey('isolate');
        }
    }
});
