<?php

/**
 * Who the Submissions inbox lets take personal data out in an export (spec 109, D3).
 *
 * It was an administrator and nobody else, short of a filter in a site's own code. It is now
 * the one right the Data screen asks for too, which Access & Abilities can give.
 *
 * @package Corex\Tests\Unit\Submissions
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Access\CorexAbility;
use Corex\Config\Submissions\WpSubmissionAccessPolicy;

/**
 * The scope of a signed-in person who holds these capabilities.
 *
 * @param list<string>             $capabilities
 * @param array<string,mixed>|null $filtered     What a site's filters answer, by filter name;
 *                                               a filter not named answers its default.
 */
function inboxScopeOf(array $capabilities, ?array $filtered = null): ?\Corex\Config\Submissions\SubmissionAccessScope
{
    Functions\when('get_current_user_id')->justReturn(7);
    Functions\when('sanitize_key')->returnArg();
    Functions\when('wp_get_current_user')->justReturn((object) ['roles' => ['editor']]);
    Functions\when('current_user_can')->alias(
        static fn (string $capability): bool => in_array($capability, $capabilities, true),
    );
    Functions\when('apply_filters')->alias(
        static fn (string $filter, mixed $default) => array_key_exists($filter, $filtered ?? []) ? $filtered[$filter] : $default,
    );

    return (new WpSubmissionAccessPolicy())->scopeFor(7);
}

it('withholds personal data from a person who only manages submissions', function () {
    expect(inboxScopeOf([CorexAbility::MANAGE_SUBMISSIONS])->canExportPersonalData)->toBeFalse();
});

it('lets a person given the right take personal data out', function () {
    $scope = inboxScopeOf([CorexAbility::MANAGE_SUBMISSIONS, CorexAbility::EXPORT_PERSONAL_DATA]);

    expect($scope->canExportPersonalData)->toBeTrue();
});

it('still lets an administrator', function () {
    expect(inboxScopeOf(['manage_options'])->canExportPersonalData)->toBeTrue();
});

it('leaves the last word to a site that filters it', function () {
    $refused = inboxScopeOf(['manage_options'], ['corex_submission_export_personal_data' => false]);
    $allowed = inboxScopeOf([CorexAbility::MANAGE_SUBMISSIONS], ['corex_submission_export_personal_data' => true]);

    expect($refused->canExportPersonalData)->toBeFalse()
        ->and($allowed->canExportPersonalData)->toBeTrue();
});
