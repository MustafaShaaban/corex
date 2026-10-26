<?php

/**
 * The two CoreX abilities on a real WordPress, as an MCP agent meets them (spec 123, US1).
 *
 * Until spec 123 nothing on a real install checked these abilities at all: DECISIONS #53 said they
 * were registered, and it was true, but the MCP Adapter on mu-va.com still could not see them. This
 * asks the questions an agent's connection asks: are they registered, would the adapter list them,
 * and who may run them.
 *
 * The adapter's own check runs only where the adapter is installed; CI's install does not have it,
 * and the meta assertion above it is the same rule written out.
 *
 * @package Corex\Tests\Integration\Abilities
 */

declare(strict_types=1);

const COREX_ABILITIES_UNDER_TEST = ['corex/site-info', 'corex/list-blocks'];

function abilitiesTestUser(string $role): int
{
    $id = wp_insert_user([
        'user_login' => 'corex-abilities-' . $role . '-' . wp_generate_password(8, false),
        'user_pass'  => wp_generate_password(),
        'user_email' => uniqid('corex-abilities-', true) . '@example.test',
        'role'       => $role,
    ]);
    expect($id)->toBeInt();

    return $id;
}

beforeEach(function () {
    if (! function_exists('wp_get_ability')) {
        $this->markTestSkipped('This WordPress has no Abilities API.');
    }
    $this->userIds = [];
});

afterEach(function () {
    wp_set_current_user(0);
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($this->userIds as $id) {
        wp_delete_user($id);
    }
});

it('registers both, public to MCP, still on REST, and read-only', function () {
    foreach (COREX_ABILITIES_UNDER_TEST as $name) {
        $ability = wp_get_ability($name);
        expect($ability)->toBeInstanceOf(WP_Ability::class);
        $meta = $ability->get_meta();

        expect($meta['mcp']['public'] ?? null)->toBeTrue("{$name} is not public to MCP");
        expect($meta['show_in_rest'] ?? null)->toBeTrue("{$name} left REST");
        expect($meta['annotations']['readonly'] ?? null)->toBeTrue("{$name} is not marked read-only");
    }
});

it('is listed by the MCP Adapter, where the adapter is installed', function () {
    if (! class_exists(\WP\MCP\Abilities\McpAbilityExposure::class)) {
        $this->markTestSkipped('The MCP Adapter is not installed on this WordPress.');
    }

    foreach (COREX_ABILITIES_UNDER_TEST as $name) {
        expect(\WP\MCP\Abilities\McpAbilityExposure::is_public(wp_get_ability($name)))->toBeTrue();
    }
});

it('answers an author with the site summary', function () {
    $this->userIds[] = $id = abilitiesTestUser('author');
    wp_set_current_user($id);

    $info = wp_get_ability('corex/site-info')->execute();

    expect($info)->toBeArray()
        ->and($info['framework'])->toBe('Corex')
        ->and($info['name'])->toBe((string) get_bloginfo('name'))
        ->and($info['corex_blocks'])->toBeInt();
});

it('keeps the CoreX version from a subscriber (spec 111)', function () {
    $this->userIds[] = $id = abilitiesTestUser('subscriber');
    wp_set_current_user($id);

    expect(wp_get_ability('corex/site-info')->execute())->toBeInstanceOf(WP_Error::class);
});

it('refuses someone who is not signed in', function () {
    wp_set_current_user(0);

    foreach (COREX_ABILITIES_UNDER_TEST as $name) {
        expect(wp_get_ability($name)->execute())->toBeInstanceOf(WP_Error::class);
    }
});
