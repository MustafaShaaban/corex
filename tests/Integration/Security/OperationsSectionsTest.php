<?php

/**
 * The screen's sections, and the environment/mode conflict (spec 077, T006/T020 / FR-001/014).
 *
 * @package Corex\Tests\Integration\Security
 */

declare(strict_types=1);

use Corex\Config\Security\OperationsSecurityScreen;

/**
 * Snapshot and restore, never delete: the integration suite runs against a real developer install
 * and this option is the site's declared operating state.
 */
beforeEach(function () {
    $this->savedMode = get_option('corex_operations_mode', null);
    $this->screen    = \Corex\Boot::app()->container()->make(OperationsSecurityScreen::class);
});

afterEach(function () {
    if ($this->savedMode === null) {
        delete_option('corex_operations_mode');

        return;
    }

    update_option('corex_operations_mode', $this->savedMode, false);
});

/** Reach a private renderer without widening the production API to suit a test. */
function invokeScreen(object $screen, string $method, mixed ...$args): string
{
    $reflected = new ReflectionMethod($screen, $method);
    $reflected->setAccessible(true);

    return (string) $reflected->invoke($screen, ...$args);
}

it('offers every section and no others', function () {
    $sections = new ReflectionMethod($this->screen, 'sections');
    $sections->setAccessible(true);

    // `cache` joins the list in spec 078. It was deliberately absent from 077 so it could arrive
    // with something in it rather than as a heading promising cache management.
    expect(array_keys($sections->invoke($this->screen)))->toBe([
        'overview',
        'environment',
        'login',
        'hardening',
        'activity',
        'cache',
    ]);
});

it('falls back to the overview for a section that does not exist', function () {
    $active = new ReflectionMethod($this->screen, 'activeSection');
    $active->setAccessible(true);

    $_GET['tab'] = 'not-a-section';
    expect($active->invoke($this->screen))->toBe('overview');

    $_GET['tab'] = 'login';
    expect($active->invoke($this->screen))->toBe('login');

    unset($_GET['tab']);
    expect($active->invoke($this->screen))->toBe('overview');
});

it('says so when the declared mode and the environment disagree', function () {
    // `wp_get_environment_type()` reports 'production' on this install unless WP_ENVIRONMENT_TYPE
    // says otherwise, so declaring development is a real conflict.
    update_option('corex_operations_mode', 'development', false);

    $notice = invokeScreen($this->screen, 'environmentConflictNotice');
    $environment = wp_get_environment_type();

    if ($environment === 'development') {
        // The host declares development too, so there is nothing to warn about — and the test
        // must not pretend otherwise.
        expect($notice)->toBe('');

        return;
    }

    expect($notice)->not->toBe('')
        ->and($notice)->toContain($environment)
        ->and($notice)->toContain('development')
        // It must never suggest CoreX can change the environment.
        ->and($notice)->toContain('does not change the environment');
});

it('says nothing when they agree', function () {
    update_option('corex_operations_mode', wp_get_environment_type(), false);

    expect(invokeScreen($this->screen, 'environmentConflictNotice'))->toBe('');
});

it('says nothing when the mode was only inherited', function () {
    // An undeclared site follows `wp_get_environment_type()` by definition, so it cannot conflict
    // with it. Warning here would put a warning on every fresh install.
    delete_option('corex_operations_mode');

    expect(invokeScreen($this->screen, 'environmentConflictNotice'))->toBe('');
});

// Spec 101 — Coming soon on the mode form (T025, FR-001 to FR-003).

it('offers Coming soon on the mode form, after Maintenance', function () {
    $form = invokeScreen($this->screen, 'modeCard');

    preg_match_all('/<option value="([a-z-]+)"/', $form, $options);

    expect($options[1])->toBe(['development', 'staging', 'production', 'maintenance', 'coming-soon'])
        ->and($form)->toContain('>Coming soon</option>');
});

it('draws a block for Coming soon that says what the mode does and asks for its own acknowledgement', function () {
    $_GET['mode'] = 'coming-soon';
    $form         = invokeScreen($this->screen, 'modeCard');
    unset($_GET['mode']);

    preg_match('/<div class="corex-opsec__mode-block" data-mode="coming-soon"(.*?)<\/div>/s', $form, $block);

    expect($block)->not->toBe([])
        // Proposed by the address, so it is the visible block and its checkbox is submittable.
        ->and($block[1])->not->toContain(' hidden')
        ->and($block[1])->not->toContain(' disabled')
        ->and($block[1])->toContain('name="corex_confirm"')
        ->and($block[1])->toContain('200 status')
        ->and($block[1])->toContain('edit posts')
        // The acknowledgement names this mode's consequence. Maintenance's sentence under a
        // Coming soon heading would be the two modes treated as one again.
        ->and($block[1])->toContain('coming-soon page')
        ->and($block[1])->not->toContain('maintenance');
});

it('keeps Maintenance asking for the acknowledgement it always asked for', function () {
    $_GET['mode'] = 'maintenance';
    $form         = invokeScreen($this->screen, 'modeCard');
    unset($_GET['mode']);

    preg_match('/<div class="corex-opsec__mode-block" data-mode="maintenance"(.*?)<\/div>/s', $form, $block);

    expect($block[1])->toContain('I understand maintenance affects real visitors.');
});

it('says on the overview what visitors are getting while the site is in Coming soon', function () {
    update_option('corex_operations_mode', 'coming-soon', false);

    $overview = invokeScreen($this->screen, 'overviewCard', [], 0);

    expect($overview)->toContain('Coming soon')
        ->and($overview)->toContain('Visitors see the coming-soon page');
});

it('selects the proposed mode in the form itself, so what is submitted is what is on screen', function () {
    // Found by the browser suite (spec 101). The form came back from a missing acknowledgement
    // showing Coming soon and its checkbox, with the <select> underneath still on the mode the
    // site was in; a script moved it on load. Ticked and submitted before that script ran — or
    // with no script at all, the path the screen documents as working — it applied the wrong mode.
    update_option('corex_operations_mode', 'development', false);
    $_GET['mode'] = 'coming-soon';
    $form         = invokeScreen($this->screen, 'modeCard');
    unset($_GET['mode']);

    preg_match_all('/<option value="([a-z-]+)"([^>]*)>/', $form, $options, PREG_SET_ORDER);
    $selected = array_values(array_filter($options, static fn (array $option): bool => str_contains($option[2], 'selected')));

    expect($selected)->toHaveCount(1)
        ->and($selected[0][1])->toBe('coming-soon')
        // What the site is in is still stated, separately, for the script that greys out a no-op.
        ->and($form)->toContain('data-current-mode="development"');
});

it('selects the current mode when nothing is proposed', function () {
    update_option('corex_operations_mode', 'staging', false);
    $form = invokeScreen($this->screen, 'modeCard');

    preg_match('/<option value="([a-z-]+)"[^>]*selected/', $form, $selected);

    expect($selected[1])->toBe('staging');
});

/*
 * The mode panel, reorganised (2026-10-07). It said the state of the site three or four times, drew
 * two unmarked lists that read as one ragged paragraph, and asked for a confirmation of the mode
 * the site was already in, beside a disabled button.
 */

it('says what the current mode does once, and under it only what that does not already say', function () {
    update_option('corex_operations_mode', 'coming-soon', false);

    $card = invokeScreen($this->screen, 'modeCard');

    expect(substr_count($card, 'Visitors see the coming-soon page; anyone signed in who can edit posts sees the real site.'))->toBe(1)
        // The warning that restated it is not drawn here.
        ->and($card)->not->toContain('every other address is redirected to it. Switch to production')
        // The one caution the mode has is, and it is marked up as a caution.
        ->and($card)->toMatch('/<ul class="corex-opsec__cautions">.*Published content can still be read through the REST API\..*<\/ul>/s');
});

it('asks for nothing while the mode selected is the one the site is in', function () {
    update_option('corex_operations_mode', 'coming-soon', false);

    $card = invokeScreen($this->screen, 'modeCard');

    preg_match('/<div class="corex-opsec__mode-block" data-mode="coming-soon"(.*?)<\/div>/s', $card, $block);

    expect($block[1])->toContain(' hidden')
        // Its acknowledgement cannot be submitted: there is no change to acknowledge.
        ->and($block[1])->toContain('name="corex_confirm" value="1" disabled')
        // And the panel says why nothing is being asked.
        ->and($card)->toMatch('/<p class="[^"]*corex-opsec__mode-same[^"]*" data-corex-mode-same>/');
});

it('draws a proposed change as a heading, what it changes, and the rest behind a disclosure', function () {
    update_option('corex_operations_mode', 'development', false);

    $_GET['mode'] = 'coming-soon';
    $card         = invokeScreen($this->screen, 'modeCard');
    unset($_GET['mode']);

    preg_match('/<div class="corex-opsec__mode-block" data-mode="coming-soon"(.*?)<\/div>/s', $card, $block);
    [$changes, $more] = explode('<details', $block[1] . '<details', 2);

    expect($block[1])->toContain('Switching to Coming soon')
        ->and($changes)->toContain('200 status')
        ->and($changes)->toContain('edit posts')
        // How the mode works and how to leave it are a click away, not in the way.
        ->and($changes)->not->toContain('To leave')
        ->and($more)->toContain('To leave')
        ->and($more)->toContain('preview link')
        // Something is being proposed, so the "nothing to change" line is not shown.
        ->and($card)->toMatch('/corex-opsec__mode-same[^>]* hidden/');
});

it('offers a mode the site only inherits as a declaration, because declaring it is a change', function () {
    // Undeclared, the site follows the WordPress environment type, and the screen tells the
    // operator to declare a mode. Choosing the one it already follows does that, and the store
    // records it — so that block is a proposal like any other, with its own heading.
    delete_option('corex_operations_mode');

    $card = invokeScreen($this->screen, 'modeCard');

    preg_match('/data-current-mode="([a-z-]+)" data-mode-declared="0"/', $card, $current);
    preg_match('/<div class="corex-opsec__mode-block" data-mode="' . $current[1] . '"(.*?)<\/div>/s', $card, $block);

    expect($block[1])->not->toContain(' hidden')
        ->and($block[1])->toContain('Declaring ')
        ->and($block[1])->not->toContain('Switching to ')
        ->and($card)->toMatch('/corex-opsec__mode-same[^>]* hidden/');
});
