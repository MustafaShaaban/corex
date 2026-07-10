<?php

/**
 * Unit tests for the nine-step setup wizard progress state machine (spec 068: T192, FR-134).
 * Contract: real completion drives status/percentage/resume; blockers make the site unsafe to
 * launch; optional steps can be skipped and do not lower the percentage. No WordPress.
 *
 * @package Corex\Tests\Unit\Kit
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Kit\Setup\ConflictResolver;
use Corex\Kit\Setup\LaunchChecklist;
use Corex\Kit\Setup\SetupProgress;
use Corex\Provisioning\PageDisposition;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('_n')->alias(static fn (string $s, string $p, int $n): string => $n === 1 ? $s : $p);
});

/**
 * @return array<string,mixed>
 */
function launchFacts(array $overrides = []): array
{
    return array_merge([
        'searchIndexable'   => true,
        'debugDisplayOff'   => true,
        'isProduction'      => true,
        'mailConfigured'    => true,
        'captchaConfigured' => true,
        'hardeningWarnings' => 0,
        'legalPagesPresent' => true,
        'formsTested'       => true,
        'readinessRun'      => true,
    ], $overrides);
}

function conflictPage(string $slug): PageDisposition
{
    return new PageDisposition($slug, ucfirst($slug), PageDisposition::SKIP, 'user_content');
}

it('projects the nine approved steps with the first incomplete one current', function () {
    $state = (new SetupProgress())->state(['welcome', 'brand']);

    expect($state['steps'])->toHaveCount(9)
        ->and(array_column($state['steps'], 'id'))->toBe([
            'welcome', 'brand', 'kit', 'demo', 'plan', 'backup', 'apply', 'launch', 'done',
        ])
        ->and($state['steps'][0]['status'])->toBe('done')
        ->and($state['steps'][1]['status'])->toBe('done')
        ->and($state['steps'][2]['status'])->toBe('current')
        ->and($state['current'])->toBe('kit')
        ->and($state['steps'][3]['status'])->toBe('upcoming');
});

it('computes percentage over required steps only and lets optional steps be skipped', function () {
    // welcome+brand+kit done of 8 required steps → 3/8 = 38%.
    $state = (new SetupProgress())->state(['welcome', 'brand', 'kit'], [], ['demo']);

    expect($state['percentage'])->toBe(38)
        ->and($state['steps'][3])->toMatchArray(['id' => 'demo', 'status' => 'skipped'])
        // The skipped optional step does not become current; plan is next.
        ->and($state['current'])->toBe('plan');
});

it('marks a blocked step and keeps the site unsafe to launch', function () {
    $state = (new SetupProgress())->state(
        ['welcome', 'brand', 'kit', 'plan', 'backup'],
        ['apply' => 'A successful backup is required before apply.'],
    );

    $apply = array_values(array_filter($state['steps'], static fn (array $s): bool => $s['id'] === 'apply'))[0];

    expect($apply['status'])->toBe('blocked')
        ->and($apply['blocker'])->toContain('backup')
        ->and($state['blocked'])->toBeTrue()
        ->and($state['canLaunch'])->toBeFalse();
});

it('is safe to launch only once apply is complete with no blockers', function () {
    $incomplete = (new SetupProgress())->state(['welcome', 'brand', 'kit', 'plan', 'backup']);
    $applied    = (new SetupProgress())->state(['welcome', 'brand', 'kit', 'plan', 'backup', 'apply']);

    expect($incomplete['canLaunch'])->toBeFalse()
        ->and($applied['canLaunch'])->toBeTrue()
        ->and($applied['percentage'])->toBe(75);
});

it('keeps existing content by default — no choice never overwrites silently (FR-143)', function () {
    $resolved = (new ConflictResolver())->resolve([conflictPage('about')], []);

    expect($resolved[0]->action)->toBe(PageDisposition::SKIP)
        ->and($resolved[0]->reason)->toBe('user_content');
});

it('applies replace and suffix only from an explicit operator choice (FR-139)', function () {
    $resolver = new ConflictResolver();
    $pages    = [conflictPage('about'), conflictPage('contact')];

    $resolved = $resolver->resolve(
        $pages,
        ['about' => 'replace', 'contact' => 'suffix'],
        ['contact-2' => true ],
    );

    expect($resolved[0]->action)->toBe(PageDisposition::REPLACE)
        ->and($resolved[0]->persistSlug())->toBe('about')
        ->and($resolved[1]->action)->toBe(PageDisposition::SUFFIX)
        // contact-2 is taken, so the suffixed slug avoids the collision.
        ->and($resolved[1]->persistSlug())->toBe('contact-3');
});

it('never rewrites non-conflict dispositions and lists only real conflicts', function () {
    $resolver = new ConflictResolver();
    $create   = new PageDisposition('home', 'Home', PageDisposition::CREATE, 'slug_absent');
    $dispositions = [$create, conflictPage('about')];

    // A create page is passed through untouched even if a stray choice names it.
    $resolved = $resolver->resolve($dispositions, ['home' => 'replace']);
    expect($resolved[0]->action)->toBe(PageDisposition::CREATE);

    expect($resolver->conflicts($dispositions))->toBe([['slug' => 'about', 'title' => 'About']]);
});

it('reports an all-clear launch checklist as ready with no blockers (FR-142)', function () {
    $checklist = (new LaunchChecklist())->build(launchFacts());

    expect($checklist['items'])->toHaveCount(8)
        ->and(array_column($checklist['items'], 'key'))->toBe([
            'indexing', 'debug', 'environment', 'email', 'security', 'legal', 'forms', 'performance',
        ])
        ->and($checklist['blockers'])->toBe(0)
        ->and($checklist['ready'])->toBeTrue()
        ->and(array_column($checklist['items'], 'status'))->each->toBe('pass');
});

it('marks discouraged indexing and visible debug as launch blockers (FR-134/142)', function () {
    $checklist = (new LaunchChecklist())->build(launchFacts([
        'searchIndexable' => false,
        'debugDisplayOff' => false,
    ]));

    $byKey = array_column($checklist['items'], null, 'key');

    expect($byKey['indexing']['status'])->toBe(LaunchChecklist::BLOCKER)
        ->and($byKey['debug']['status'])->toBe(LaunchChecklist::BLOCKER)
        ->and($checklist['blockers'])->toBe(2)
        ->and($checklist['ready'])->toBeFalse();
});

it('treats missing email, captcha, legal, forms, and readiness as warnings, not blockers', function () {
    $checklist = (new LaunchChecklist())->build(launchFacts([
        'mailConfigured'    => false,
        'captchaConfigured' => false,
        'legalPagesPresent' => false,
        'formsTested'       => false,
        'readinessRun'      => false,
    ]));

    expect($checklist['blockers'])->toBe(0)
        ->and($checklist['ready'])->toBeTrue()
        ->and($checklist['warnings'])->toBe(5);
});
