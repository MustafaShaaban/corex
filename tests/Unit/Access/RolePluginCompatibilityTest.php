<?php

/**
 * Tests for external role-plugin coexistence detection.
 *
 * @package Corex\Tests\Unit\Access
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Access\RolePluginCompatibility;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

it('detects known external role plugins by plugin basename prefix', function () {
    $compatibility = new RolePluginCompatibility();

    expect($compatibility->detect([
        'members/members.php',
        'akismet/akismet.php',
        'user-role-editor/user-role-editor.php',
        'members/alternate.php',
    ]))->toBe(['Members', 'User Role Editor']);
});

it('keeps native capabilities read-only while CoreX abilities remain editable when conflicts exist', function () {
    $summary = (new RolePluginCompatibility())->coexistence(['advanced-access-manager/aam.php']);

    expect($summary['plugins'])->toBe(['Advanced Access Manager'])
        ->and($summary['nativeCapabilitiesEditable'])->toBeFalse()
        ->and($summary['corexAbilitiesEditable'])->toBeTrue()
        ->and($summary['message'])->toContain('CoreX-owned abilities');
});
