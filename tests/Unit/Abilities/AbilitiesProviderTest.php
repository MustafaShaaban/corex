<?php

/**
 * What AbilitiesProvider hands to the Abilities API (spec 123, US1, FR-001, FR-002).
 *
 * The MCP Adapter lists an ability only when its `meta.mcp.public` is true, or its `meta.public` is
 * true while `meta.mcp.public` is unset (adapter v0.7.1, `McpAbilityExposure::is_public`). Before spec
 * 123 neither key was set, so no agent could see the two CoreX abilities. The integration test checks
 * the same abilities on a real WordPress; this one runs headless and also pins that `meta.public`
 * stays unset, which would open them to every client channel from WordPress 7.1.
 *
 * Brain Monkey cannot undefine a function once a test has stubbed it, so the "Abilities API absent"
 * path (`boot()` returning early) is not tested here; it is unchanged by spec 123.
 *
 * @package Corex\Tests\Unit\Abilities
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Abilities\AbilitiesProvider;
use Corex\Container\Container;

/**
 * Register the abilities and return what each was registered with, by name.
 *
 * @return array<string, array<string, mixed>>
 */
function registeredCorexAbilities(): array
{
    $registered = [];
    Functions\stubTranslationFunctions();
    Functions\when('wp_register_ability')->alias(
        static function (string $name, array $args) use (&$registered): void {
            $registered[$name] = $args;
        }
    );

    $provider = new AbilitiesProvider(new Container());
    $provider->register();
    $provider->registerAbilities();

    return $registered;
}

it('offers both abilities to MCP agents only, read-only, in the corex category', function () {
    $registered = registeredCorexAbilities();

    expect(array_keys($registered))->toBe(['corex/list-blocks', 'corex/site-info']);
    foreach ($registered as $name => $args) {
        expect($args['meta']['mcp']['public'] ?? null)->toBeTrue("{$name} is not public to MCP");
        expect(array_key_exists('public', $args['meta']))->toBeFalse("{$name} opts into every client channel");
        expect($args['meta']['show_in_rest'] ?? null)->toBeTrue("{$name} left REST");
        expect($args['meta']['annotations'])->toBe(['readonly' => true, 'idempotent' => true]);
        expect($args['category'])->toBe('corex');
    }
});

it('lets someone who can edit posts run them', function () {
    Functions\when('current_user_can')->alias(static fn (string $capability): bool => $capability === 'edit_posts');

    foreach (registeredCorexAbilities() as $args) {
        expect(($args['permission_callback'])())->toBeTrue();
    }
});

it('keeps the CoreX version from someone who can only read the site (spec 111)', function () {
    Functions\when('current_user_can')->alias(static fn (string $capability): bool => $capability === 'read');

    foreach (registeredCorexAbilities() as $args) {
        expect(($args['permission_callback'])())->toBeFalse();
    }
});
