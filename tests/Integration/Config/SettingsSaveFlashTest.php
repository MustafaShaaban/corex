<?php

/**
 * What a Settings save did waits for its own redirect (spec 119, FR-006; DECISIONS #646).
 *
 * The result used to wait in one place per person, so a second save by the same person, in
 * another tab or another browser, overwrote the first before its page was drawn, and that page
 * said nothing about what it had just done. Now each save waits under its own id. On real
 * WordPress, with real transients.
 *
 * @package Corex\Tests\Integration\Config
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Settings\SettingsSaveFlash;
use Corex\Config\Settings\SettingsSaveResult;

beforeEach(function () {
    $this->flash  = Boot::app()->container()->make(SettingsSaveFlash::class);
    $this->person = 7001;
});

it('keeps each save of one person for its own page, when a second save comes before the first page is drawn', function () {
    $insights = $this->flash->newSave();
    $email    = $this->flash->newSave();
    $this->flash->keep($this->person, $insights, new SettingsSaveResult('insights', [], ['insights.cloudflare.token']));
    $this->flash->keep($this->person, $email, new SettingsSaveResult('email'));

    expect($this->flash->take($this->person, 'insights', $insights)?->removed)->toBe(['insights.cloudflare.token'])
        ->and($this->flash->take($this->person, 'email', $email)?->page)->toBe('email');
});

it('says what a save did once, only to the person who saved, and only on the page it was for', function () {
    $saved = $this->flash->newSave();
    $this->flash->keep($this->person, $saved, new SettingsSaveResult('insights'));

    expect($this->flash->take($this->person + 1, 'insights', $saved))->toBeNull()
        ->and($this->flash->take($this->person, 'email', $saved))->toBeNull()
        ->and($this->flash->take($this->person, 'insights', $this->flash->newSave()))->toBeNull()
        ->and($this->flash->take($this->person, 'insights', $saved)?->page)->toBe('insights')
        ->and($this->flash->take($this->person, 'insights', $saved))->toBeNull();
});
