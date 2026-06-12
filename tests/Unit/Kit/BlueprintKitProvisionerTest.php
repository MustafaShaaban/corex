<?php

/**
 * Unit tests for the kit-provisioner adapter (spec 042): applicable kits, module mapping, a read-only preview,
 * and apply that delegates to the shared activator and records "applied". WP options stubbed via Brain Monkey.
 *
 * @package Corex\Tests\Unit\Kit
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Kit\Blueprint;
use Corex\Kit\BlueprintActivator;
use Corex\Kit\BlueprintRegistry;
use Corex\Kit\Provisioning\BlueprintKitProvisioner;
use Corex\Kit\SetupWizard;

function fakeCompanyBlueprint(): Blueprint
{
    return new class extends Blueprint {
        public function name(): string
        {
            return 'company';
        }

        /** @return list<string> */
        public function requiredModules(): array
        {
            return ['corex-ui'];
        }

        /** @return list<string> */
        public function templates(): array
        {
            return [];
        }

        /** @return list<string> */
        public function parts(): array
        {
            return [];
        }

        /** @return list<array{title:string,slug:string,content:string,front?:bool}> */
        public function pages(): array
        {
            return [['title' => 'Home', 'slug' => 'home', 'content' => '<!-- wp:corex/hero /-->', 'front' => true]];
        }
    };
}

function provisioner(): BlueprintKitProvisioner
{
    $registry = new BlueprintRegistry();
    $registry->register(fakeCompanyBlueprint());

    return new BlueprintKitProvisioner($registry, new SetupWizard($registry), new BlueprintActivator());
}

beforeEach(function () {
    Functions\when('get_option')->justReturn([]);
    Functions\when('sanitize_key')->returnArg();
});

it('lists only kits that declare pages as applicable', function () {
    $kits = provisioner()->applicableKits();

    expect($kits)->toHaveCount(1)
        ->and($kits[0]->name)->toBe('company')
        ->and($kits[0]->pageCount)->toBe(1)
        ->and($kits[0]->requiredModules)->toBe(['corex-ui'])
        ->and($kits[0]->applied)->toBeFalse();
});

it('maps an add-on slug to its kit name', function () {
    expect(provisioner()->kitForModule('corex-kit-company'))->toBe('company')
        ->and(provisioner()->kitForModule('corex-forms'))->toBeNull();
});

it('builds a read-only preview without writing anything', function () {
    Functions\when('get_page_by_path')->justReturn(null); // all create
    Functions\when('get_post_field')->justReturn('');
    Functions\expect('wp_insert_post')->never();
    Functions\expect('wp_update_post')->never();
    Functions\expect('update_option')->never();

    $preview = provisioner()->preview('company');

    expect($preview->kit)->toBe('company')
        ->and($preview->frontTargetSlug)->toBe('home')
        ->and($preview->pages)->toHaveCount(1)
        ->and($preview->pages[0]->action)->toBe('create');
});

it('apply delegates to the activator and records the kit as applied', function () {
    Functions\when('get_page_by_path')->justReturn(null);
    Functions\when('get_post_field')->justReturn('');
    Functions\when('wp_insert_post')->justReturn(500);
    Functions\when('update_post_meta')->justReturn(true);
    Functions\when('is_plugin_active')->justReturn(true);  // modules already active → no require of wp-admin
    Functions\when('activate_plugin')->justReturn(null);

    $applied = null;
    Functions\when('update_option')->alias(function (string $key, $value) use (&$applied): bool {
        if ($key === BlueprintKitProvisioner::APPLIED_OPTION) {
            $applied = $value;
        }

        return true;
    });

    $outcome = provisioner()->apply('company');

    expect($outcome->created())->toHaveCount(1)
        ->and($applied)->toBe(['company']);
});
