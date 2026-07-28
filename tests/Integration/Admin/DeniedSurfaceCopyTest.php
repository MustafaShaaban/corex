<?php

/**
 * What a refusal actually says (spec 083, FR-006).
 *
 * The rendered surface, not a predicate about it. Until spec 083 the explanation named
 * `manage_options` on every screen — a statement that is simply false on `corex-notifications`
 * (`corex_manage_notifications`), `corex-submissions` (`corex_manage_submissions`) and
 * `corex-data-models`, made at HTTP 403 to somebody trying to understand why they were stopped.
 *
 * `AccessDeniedGateTest` covers the branch point through a private predicate because `intercept()`
 * exits either way. This file covers the other half — what each branch renders — which is where the
 * false sentence lived and where the predicate could never have found it.
 *
 * @package Corex\Tests\Integration\Admin
 */

declare(strict_types=1);

use Corex\Access\CorexAbility;
use Corex\Admin\AdminPage;
use Corex\Boot;

/**
 * @return array<string,string> section => the ability a request from it must name
 */
function deniedSections(): array
{
    return [
        'corex-forms' => CorexAbility::MANAGE_FORMS,
        'corex-notifications' => CorexAbility::MANAGE_NOTIFICATIONS,
        'corex-submissions' => CorexAbility::MANAGE_SUBMISSIONS,
        'corex-data-models' => CorexAbility::MANAGE_DATA,
        'corex-access' => CorexAbility::MANAGE_ACCESS,
    ];
}

it('names the ability a request from this screen actually asks for', function () {
    $page = Boot::app()->container()->make(AdminPage::class);

    foreach (deniedSections() as $section => $ability) {
        expect($page->deniedSurface($section))->toContain($ability);
    }
});

/**
 * The regression itself. `manage_options` was named on every screen, including three that do not
 * require it — so its absence is the assertion, and it is the one that fails against `main`.
 */
it('no longer claims manage_options on screens that never required it', function () {
    $page = Boot::app()->container()->make(AdminPage::class);

    foreach (['corex-notifications', 'corex-submissions', 'corex-data-models'] as $section) {
        expect($page->deniedSurface($section))->not->toContain('manage_options');
    }
});

/**
 * The ability the copy names and the ability the submission handler resolves must be the same one.
 * Two lookups would let the page promise one permission while the queue records a request for
 * another — which nobody would notice until an approval failed to open the screen.
 */
it('names the same ability the submission handler resolves', function () {
    $page = Boot::app()->container()->make(AdminPage::class);

    foreach (array_keys(deniedSections()) as $section) {
        expect($page->deniedSurface($section))
            ->toContain(AdminPage::requestAbilityFor($section));
    }
});
