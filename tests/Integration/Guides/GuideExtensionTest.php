<?php

/**
 * A site plugin extends the guides, against a real WordPress (spec 084, SC-001 / SC-003).
 *
 * This is the test the whole add-on exists to make pass. Everything else about a guide registry is
 * ordinary; the part that is not ordinary is that the intended contributor is a plugin Corex has
 * never heard of, booting on the same hook at the same priority. `GuideRegistryTest` proves the
 * mechanism in isolation; this proves it against the real container, after the real boot, through
 * the exact call a site developer will write.
 *
 * @package Corex\Tests\Integration\Guides
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Guides\Corex\CorexGuides;
use Corex\Guides\Guide;
use Corex\Guides\GuideRegistry;
use Corex\Guides\GuideStep;
use Corex\Guides\GuideTopic;

/**
 * The registry as a site plugin reaches it: from the container, after boot.
 */
function guideRegistry(): GuideRegistry
{
    return Boot::app()->container()->make(GuideRegistry::class);
}

beforeEach(function () {
    wp_set_current_user(1);
});

afterEach(function () {
    // The registry is a singleton for the process, so anything these tests register or overwrite
    // leaks into the next test and into any later file that counts guides.
    //
    // Both halves run unconditionally, in `afterEach` rather than at the end of the test that made
    // the mess. A restore written as the last line of a test does not run when an assertion above
    // it fails — so the one run where something broke is also the run where every later test is
    // poisoned, and the report names the wrong file.
    //
    // Reflection because there is no public way to unregister, and there should not be: `forget()`
    // would be production API with no production caller, existing only to serve these tests.
    $registry = guideRegistry();
    $property = new ReflectionProperty($registry, 'guides');
    $guides   = $property->getValue($registry);

    foreach (array_keys($guides) as $id) {
        if (str_starts_with((string) $id, 'site-')) {
            unset($guides[$id]);
        }
    }

    $property->setValue($registry, $guides);

    // Put back anything a test replaced by re-registering under a Corex id.
    foreach (CorexGuides::all() as $guide) {
        $registry->register($guide);
    }
});

it('ships the Corex guides through the same public seam a site uses', function () {
    $ids = array_map(static fn (Guide $g): string => $g->id, guideRegistry()->all());

    expect($ids)->toContain('corex-submissions')
        ->and($ids)->toContain('corex-publishing');
});

/**
 * SC-001. A site plugin registers after Corex has booted — which is the ordering that actually
 * happens, and the one an eagerly-resolved registry silently drops.
 */
it('accepts a guide registered after the framework has finished booting', function () {
    guideRegistry()->registerDeferred(static fn (): array => [
        Guide::for('site-projects', 'Managing projects')
            ->withSummary('How this site handles its projects.')
            ->inSection('content')
            ->onScreen('edit.php?post_type=site_project')
            ->requiring('edit_posts')
            ->withTopic(GuideTopic::for('add', 'Add a project', '', [
                new GuideStep('Choose Projects, then Add Project.', 'The editor opens.'),
            ])),
    ]);

    $guide = guideRegistry()->find('site-projects');

    expect($guide)->not->toBeNull()
        ->and($guide->title)->toBe('Managing projects')
        ->and($guide->topics)->toHaveCount(1);
});

it('puts a site guide on the screen it declares, beside nothing of Corex’s', function () {
    guideRegistry()->registerDeferred(static fn (): array => [
        Guide::for('site-projects', 'Managing projects')
            ->onScreen('edit.php?post_type=site_project'),
    ]);

    $found = guideRegistry()->forScreen('edit.php?post_type=site_project');

    expect($found)->toHaveCount(1)
        ->and($found[0]->id)->toBe('site-projects');
});

it('lets a site replace a Corex guide it disagrees with', function () {
    $original = guideRegistry()->find('corex-publishing');
    expect($original)->not->toBeNull();

    guideRegistry()->register(Guide::for('corex-publishing', 'How we publish here'));

    expect(guideRegistry()->find('corex-publishing')?->title)->toBe('How we publish here');
});

/**
 * SC-003, from the other direction. A site's guides are registered by the site's own provider, so
 * a deactivated plugin contributes nothing — there is no list of contributors to prune, which is
 * why nothing here has to be cleaned up when a plugin goes away.
 */
it('offers a site guide only to somebody holding the capability it names', function () {
    guideRegistry()->registerDeferred(static fn (): array => [
        Guide::for('site-restricted', 'Restricted')->requiring('manage_network_options'),
    ]);

    $subscriber = wp_insert_user([
        'user_login' => 'guides-subscriber-' . wp_generate_password(6, false),
        'user_pass'  => wp_generate_password(),
        'user_email' => uniqid('guides', false) . '@example.test',
        'role'       => 'subscriber',
    ]);
    wp_set_current_user((int) $subscriber);

    $ids = array_map(static fn (Guide $g): string => $g->id, guideRegistry()->available());

    expect($ids)->not->toContain('site-restricted');
});
