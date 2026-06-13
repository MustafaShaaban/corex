<?php

/**
 * Unit tests for the pure kit-activation view models (spec 042). No WordPress.
 *
 * @package Corex\Tests\Unit\Addons
 */

declare(strict_types=1);

use Corex\Config\Addons\KitActivationView;
use Corex\Provisioning\ApplyOutcome;
use Corex\Provisioning\ApplyPreview;
use Corex\Provisioning\PageDisposition;

it('reshapes a preview into prompt rows with the front target and modules', function () {
    $preview = new ApplyPreview(
        'company',
        [
            new PageDisposition('home', 'Home', PageDisposition::ADOPT, 'existing_empty'),
            new PageDisposition('about', 'About', PageDisposition::CREATE, 'slug_absent'),
        ],
        'home',
        ['corex-ui'],
        [],
    );

    $prompt = (new KitActivationView())->prompt($preview);

    expect($prompt['kit'])->toBe('company')
        ->and($prompt['front'])->toBe('home')
        ->and($prompt['modules'])->toBe(['corex-ui'])
        ->and($prompt['rows'][0])->toMatchArray(['slug' => 'home', 'action' => 'adopt', 'reason' => 'existing_empty'])
        ->and($prompt['rows'][1]['action'])->toBe('create');
});

it('reshapes an outcome into a summary with counts and rows', function () {
    $outcome = new ApplyOutcome(
        [
            ['disposition' => new PageDisposition('home', 'Home', PageDisposition::ADOPT, 'existing_empty'), 'pageId' => 2511, 'isFront' => true, 'persistedAs' => 'adopted'],
            ['disposition' => new PageDisposition('about', 'About', PageDisposition::CREATE, 'slug_absent'), 'pageId' => 2527, 'isFront' => false, 'persistedAs' => 'created'],
            ['disposition' => new PageDisposition('contact', 'Contact', PageDisposition::SKIP, 'user_content'), 'pageId' => null, 'isFront' => false, 'persistedAs' => null],
        ],
        ['corex-ui'],
        [],
        2511,
    );

    $summary = (new KitActivationView())->summary($outcome);

    expect($summary['created'])->toBe(1)
        ->and($summary['populated'])->toBe(1)
        ->and($summary['skipped'])->toBe(1)
        ->and($summary['frontPageId'])->toBe(2511)
        ->and($summary['rows'][0])->toMatchArray(['slug' => 'home', 'isFront' => true]);
});
