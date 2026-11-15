<?php

/**
 * A setting that is not what its field takes is refused, and the screen says so (spec 106,
 * story 5, scenario 3).
 *
 * It was dropped in silence: an address typed wrongly was not saved, the screen said "Settings
 * saved", and the field came back empty or as it was before.
 *
 * On real WordPress, through the screen's own save and its own page.
 *
 * @package Corex\Tests\Integration\Config
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Settings\SettingsRegistry;
use Corex\Config\Settings\SettingsStore;
use Corex\Tests\Support\SettingsScreen;

beforeEach(function () {
    $this->signedInAs = get_current_user_id();
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));
    $this->settings = Boot::app()->container()->make(SettingsStore::class);
    // A save writes every checkbox, posted or not: everything is put back as it was.
    $this->asItWas = [];
    foreach (Boot::app()->container()->make(SettingsRegistry::class)->keys() as $key) {
        $this->asItWas[$key] = get_option($this->settings->optionName($key), null);
    }
});

afterEach(function () {
    foreach ($this->asItWas as $key => $value) {
        if ($value === null) {
            delete_option($this->settings->optionName($key));
        } else {
            update_option($this->settings->optionName($key), $value);
        }
    }
    wp_set_current_user($this->signedInAs);
});

/**
 * Saves what was posted on the Email page, and answers what the page then says: the toast it
 * draws after the redirect, as markup, or '' where it draws none.
 */
function saidAfterSaving(array $posted): string
{
    $result = SettingsScreen::save('email', $posted);
    $page   = SettingsScreen::draw('email', $result);
    // The toast and nothing else: the page has other regions with a role of their own.
    preg_match('/<div class="corex-toast\b.*?<\/button><\/div>/s', $page, $toast);

    return $toast[0] ?? '';
}

it('refuses a value its field does not take, says which field and why, and leaves what was there', function (string $key, string $before, string $typed, string $label, string $why) {
    $this->settings->save($key, $before);
    $name = str_replace('.', '_', $key);

    $said = saidAfterSaving([$name => $typed, 'mail_reply_from_name' => 'Acme Sales']);

    expect($this->settings->get($key))->toBe($before)
        // Said as something that went wrong, by the field's own name, with what was typed.
        ->and($said)->toContain('role="alert"')
        ->and($said)->toContain($label . ' was not changed: ')
        ->and($said)->toContain('<bdi>' . esc_html($typed) . '</bdi>')
        ->and($said)->toContain($why)
        // The line says which field it is about, for the page's script to mark it.
        ->and($said)->toContain('data-corex-refused="' . $name . '"')
        ->and($said)->not->toContain('Settings saved.')
        // And the rest of what was posted was saved.
        ->and($said)->toContain('Everything else was saved.')
        ->and($this->settings->get('mail.reply.from_name'))->toBe('Acme Sales');
})->with([
    'an address with nothing after the @' => ['mail.reply.from_address', 'before@example.com', 'answers@', 'Reply sender address', 'is not an email address.'],
    // WordPress could "repair" this one into another address, and it was saved as that.
    'an address in letters an address cannot hold' => ['mail.reply.reply_to', 'before@example.com', 'sales@münchen.de', 'Replies are answered to', 'is not an email address.'],
    'a colour that is not six-digit hex' => ['mail.brand.accent', '#0b1f3b', 'navy', 'Email accent colour', 'is not a six-digit colour such as #0B1F3B.'],
    'a web address that is not one' => ['mail.brand.logo', '', 'javascript:alert(1)', 'Email logo', 'is not a web address.'],
    'a choice that is not offered' => ['mail.provider', '', 'postmark', 'Delivery provider', 'is not one of its choices.'],
]);

it('says only that the settings were saved when everything was', function () {
    $said = saidAfterSaving([
        'mail_reply_from_name' => 'Acme Sales',
        'mail_reply_from_address' => 'answers@example.com',
    ]);

    expect($this->settings->get('mail.reply.from_address'))->toBe('answers@example.com')
        ->and($said)->toContain('Settings saved.')
        ->and($said)->toContain('role="status"')
        ->and($said)->not->toContain('role="alert"')
        ->and($said)->not->toContain('was not changed');
});

// What was typed is drawn on a page: as text, whatever it was, and no more of it than can be
// read. A picture pasted into a web address field as `data:` is thousands of characters.
it('quotes what was typed as text, and only its beginning where it is long', function () {
    $said = saidAfterSaving([
        'mail_reply_reply_to' => '<script>alert(1)</script>@',
        'mail_brand_logo' => 'data:image/png;base64,' . str_repeat('A', 5000),
    ]);

    expect($said)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;@')
        ->and($said)->not->toContain('<script>')
        ->and($said)->toContain('<bdi>data:image/png;base64,' . str_repeat('A', 58) . '…</bdi>')
        ->and(strlen($said))->toBeLessThan(1500);
});

it('offers the three settings of a reply\'s sender on the Email page, each named', function () {
    $page = SettingsScreen::draw('email');

    expect($page)->toContain('name="mail_reply_from_name"')
        ->and($page)->toContain('name="mail_reply_from_address"')
        ->and($page)->toContain('name="mail_reply_reply_to"')
        ->and($page)->toContain('Reply sender name')
        ->and($page)->toContain('Reply sender address')
        ->and($page)->toContain('Replies are answered to');
});

it('draws a refused value in its field after the save, so the page opens with it unsaved', function () {
    $this->settings->save('mail.reply.from_address', 'before@example.com');

    $page = SettingsScreen::draw('email', SettingsScreen::save('email', ['mail_reply_from_address' => 'answers@']));

    expect($page)->toContain('name="mail_reply_from_address" type="email" value="answers@" class="regular-text" data-corex-saved="before@example.com"');
});
