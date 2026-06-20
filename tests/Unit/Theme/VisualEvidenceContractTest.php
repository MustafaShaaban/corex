<?php

declare(strict_types=1);

use Corex\Tests\Support\ThemeContract;

it('requires every accessible visual evidence scenario', function () {
    $path = ThemeContract::root()
        . '/specs/057-brand-tokens-logo-system/inventories/accessibility-evidence.md';

    expect($path)->toBeFile();

    if (! is_file($path)) {
        return;
    }

    $evidence = (string) file_get_contents($path);
    $required = [
        'focus-surface', 'forced-colors', 'high-contrast', 'zoom-200',
        'text-resize', 'reduced-motion', 'light', 'dark', 'ltr', 'rtl',
    ];

    foreach ($required as $scenario) {
        expect($evidence)->toContain($scenario);
    }
});
