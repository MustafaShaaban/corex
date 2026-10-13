<?php

/**
 * The account block printed twice on one page, by WordPress's own block renderer.
 *
 * A field's label is tied to it by an id, and an id belongs to one element of a page. A page
 * can hold the block more than once (a sign-in panel in the header and the account page under
 * it), and nothing in the block limits it to one (DECISIONS #337).
 *
 * @package Corex\Tests\Integration\Profile
 */

declare(strict_types=1);

beforeEach(function () {
    // A visitor: the signed-out panel is the one with two forms that ask for the same thing.
    $this->signedIn = get_current_user_id();
    wp_set_current_user(0);
});

afterEach(function () {
    wp_set_current_user($this->signedIn);
});

it('gives the fields of two account blocks on one page ids of their own', function () {
    $page = do_blocks('<!-- wp:corex/account /--><!-- wp:corex/account /-->');

    preg_match_all('/\sid="([^"]+)"/', $page, $printed);
    $ids = $printed[1];

    expect(substr_count($page, 'data-corex-account-form="login"'))->toBe(2)
        ->and($ids)->not->toBeEmpty()
        ->and(array_values(array_diff_assoc($ids, array_unique($ids))))->toBe([]);
});
