<?php

/**
 * The Settings guide documents every settings field (spec 094).
 *
 * A hand-written guide and a registry that changes are guaranteed to drift, and the drift is silent:
 * the guide still renders, the new field still saves, and nothing anywhere says the two disagree.
 * The owner's requirement was that *every* input be described, which is a promise that decays the
 * first time somebody adds a field — so it is asserted rather than intended.
 *
 * Matching is on the field's **label**, not its key. A reader looking for a setting reads
 * "Company name" off the screen; `brand.company_name` appears nowhere they can see, so a guide that
 * satisfied a key check could still be useless to them.
 *
 * @package Corex\Tests\Unit\Guides
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Settings\SettingsRegistry;
use Corex\Guides\Corex\Guides\SettingsGuide;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

/**
 * Every word of the guide — summary, topic titles and intros, and every step's instruction, result
 * and warning. A field named in any of those is documented.
 */
function settingsGuideText(): string
{
    $guide = SettingsGuide::guide();
    $text = $guide->title . ' ' . $guide->summary;

    foreach ($guide->topics as $topic) {
        $text .= ' ' . $topic->title . ' ' . $topic->intro;

        foreach ($topic->steps as $step) {
            $text .= ' ' . $step->instruction . ' ' . $step->result . ' ' . $step->warning;
        }
    }

    return $text;
}

it('names every settings field a reader can see on the screen', function () {
    $text = strtolower(settingsGuideText());
    $undocumented = [];

    foreach ((new SettingsRegistry())->sections() as $section) {
        foreach ($section['fields'] as $key => $field) {
            // Compared on the label, because that is the string on the screen. Case-insensitive so
            // the guide may start a sentence with it.
            if (! str_contains($text, strtolower((string) $field['label']))) {
                $undocumented[] = sprintf('%s ("%s")', $key, $field['label']);
            }
        }
    }

    // Reported as one list. A message naming the first would send somebody to document one field
    // and re-run into the next.
    expect(implode("\n", $undocumented))->toBe('');
});

/**
 * The three behaviours the screen cannot explain about itself, and the reason this is a guide rather
 * than more help text. Losing any of them would leave the guide comprehensive and still unhelpful.
 */
it('explains the behaviours the screen itself cannot', function () {
    $text = strtolower(settingsGuideText());

    expect($text)
        // Write-only secrets: a blank-looking password field may already be set.
        ->toContain('blank')
        // Conditional captcha fields: a field you cannot find is one that does not apply.
        ->toContain('driver')
        // The Advanced section stores nothing.
        ->toContain('stores no settings');
});

it('is gated on the ability the settings screen actually enforces', function () {
    expect(SettingsGuide::guide()->capability)->toBe('corex_manage_settings');
});
