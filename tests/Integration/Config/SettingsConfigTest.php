<?php

/**
 * Integration test: a saved setting is read back through the Config engine (spec 017: FR-004, SC-003).
 * Proves the settings store persists to the same options the Config option layer reads.
 *
 * @package Corex\Tests\Integration\Config
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Settings\SettingsStore;
use Corex\Support\Facades\Config;

it('persists a setting that the Config engine then reads', function () {
    $store = Boot::app()->container()->make(SettingsStore::class);

    $store->save('brand.footer_text', 'Powered by Acme');

    expect(Config::get('brand.footer_text'))->toBe('Powered by Acme');

    delete_option('corex_brand_footer_text');
});
