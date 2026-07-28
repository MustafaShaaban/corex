<?php

/**
 * Unit tests for the guide registry (spec 084). No WordPress beyond stubbed helpers.
 *
 * Contract: any plugin can contribute guides at any point up to first read, a malformed
 * contribution cannot break the screen, and a guide nobody can act on is never offered.
 *
 * @package Corex\Tests\Unit\Guides
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Guides\Guide;
use Corex\Guides\GuideRegistry;
use Corex\Guides\GuideStep;
use Corex\Guides\GuideTopic;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('apply_filters')->alias(
        static fn (string $hook, mixed $value): mixed => $value,
    );
    Functions\when('current_user_can')->justReturn(true);

    $this->registry = new GuideRegistry();
});

it('registers a guide and finds it by id', function () {
    $this->registry->register(Guide::for('a', 'Alpha'));

    expect($this->registry->find('a')?->title)->toBe('Alpha')
        ->and($this->registry->find('nope'))->toBeNull();
});

/**
 * The override case. A site that disagrees with a Corex guide replaces it by registering its own
 * under the same id — which only works if ids key the collection rather than appending to a list.
 */
it('replaces rather than duplicates when an id is registered twice', function () {
    $this->registry->register(Guide::for('a', 'Corex version'));
    $this->registry->register(Guide::for('a', 'The site’s version'));

    expect($this->registry->all())->toHaveCount(1)
        ->and($this->registry->find('a')?->title)->toBe('The site’s version');
});

/**
 * The reason this class exists. Corex boots on `plugins_loaded` and so does a site plugin, so
 * nothing may be resolved until something reads — which is an admin request, long after both.
 */
it('resolves a deferred factory only when the registry is first read', function () {
    $built = 0;

    $this->registry->registerDeferred(function () use (&$built): array {
        $built++;

        return [Guide::for('late', 'Registered after boot')];
    });

    expect($built)->toBe(0);

    $this->registry->all();
    $this->registry->all();

    expect($built)->toBe(1)
        ->and($this->registry->find('late'))->not->toBeNull();
});

/**
 * A plugin that registers after somebody has already read the registry must still appear. Without
 * this, the guide's visibility would depend on whether anything happened to read first — which is
 * the same coin flip `registerDeferred` exists to remove.
 */
it('picks up a factory registered after an earlier read', function () {
    $this->registry->register(Guide::for('first', 'First'));
    expect($this->registry->all())->toHaveCount(1);

    $this->registry->registerDeferred(static fn (): array => [Guide::for('second', 'Second')]);

    expect($this->registry->all())->toHaveCount(2);
});

/**
 * A factory that reads the registry back — a site guide that links to a Corex one — must terminate.
 * Without the guard this recurses until the request dies, on the screen somebody opened for help.
 */
it('does not recurse when a factory reads the registry back', function () {
    $this->registry->registerDeferred(function (): array {
        // Reading from inside resolution is the trap. It must return what is there, not re-enter.
        $this->registry->all();

        return [Guide::for('self-referential', 'Reads itself')];
    });

    expect($this->registry->all())->toHaveCount(1);
});

it('sorts by section, then order, then title', function () {
    $this->registry->register(Guide::for('b', 'Bravo')->inSection('content')->ordered(20));
    $this->registry->register(Guide::for('a', 'Alpha')->inSection('content')->ordered(10));
    $this->registry->register(Guide::for('c', 'Charlie')->inSection('admin'));

    expect(array_map(static fn (Guide $g): string => $g->id, $this->registry->all()))
        ->toBe(['c', 'a', 'b']);
});

it('groups available guides by section', function () {
    $this->registry->register(Guide::for('a', 'Alpha')->inSection('content'));
    $this->registry->register(Guide::for('b', 'Bravo')->inSection('content'));
    $this->registry->register(Guide::for('c', 'Charlie')->inSection('operations'));

    $grouped = $this->registry->bySection();

    // Sections come out in key order, which is alphabetical — `content` before `operations`.
    // Documented on the registry, because it is the only lever a site has over where its own
    // section lands and it is not guessable from the API.
    expect(array_keys($grouped))->toBe(['content', 'operations'])
        ->and($grouped['content'])->toHaveCount(2);
});

it('offers no guide whose capability the reader lacks', function () {
    Functions\when('current_user_can')->alias(
        static fn (string $capability): bool => $capability === 'read',
    );

    $this->registry->register(Guide::for('open', 'No capability needed'));
    $this->registry->register(Guide::for('reader', 'Readable')->requiring('read'));
    $this->registry->register(Guide::for('admin', 'Admins only')->requiring('manage_options'));

    expect(array_map(static fn (Guide $g): string => $g->id, $this->registry->available()))
        ->toBe(['open', 'reader']);
});

it('finds the guides describing one admin screen, and none for another', function () {
    $this->registry->register(Guide::for('jobs', 'Jobs')->onScreen('edit.php?post_type=corex_job'));
    $this->registry->register(Guide::for('loose', 'Belongs to no screen'));

    expect($this->registry->forScreen('edit.php?post_type=corex_job'))->toHaveCount(1)
        ->and($this->registry->forScreen('upload.php'))->toBeEmpty()
        // A guide with no declared screen must not match the empty string, or every screen
        // without a guide would suddenly grow a help tab for it.
        ->and($this->registry->forScreen(''))->toBeEmpty();
});

/**
 * The filter is the seam for callers with no container, which means it is also the seam through
 * which somebody returns a string. This screen is what a stuck person opens; a fatal here is the
 * worst possible moment for one.
 */
it('discards anything from the filter that is not a guide', function () {
    Functions\when('apply_filters')->alias(static function (string $hook, mixed $value): mixed {
        if ($hook !== 'corex_guides') {
            return $value;
        }

        return [...$value, 'not a guide', 42, null, Guide::for('valid', 'Added by filter')];
    });

    $this->registry->register(Guide::for('original', 'Original'));

    // Both are in the default section at the default order, so they come out title-sorted:
    // "Added by filter" before "Original". The point here is that two survived and three did not.
    expect(array_map(static fn (Guide $g): string => $g->id, $this->registry->all()))
        ->toBe(['valid', 'original']);
});

it('survives a filter that returns something other than an array', function () {
    Functions\when('apply_filters')->alias(
        static fn (string $hook, mixed $value): mixed => $hook === 'corex_guides' ? 'broken' : $value,
    );

    $this->registry->register(Guide::for('original', 'Original'));

    expect($this->registry->all())->toHaveCount(1);
});

it('searches a guide by the words in its steps, not just its title', function () {
    $guide = Guide::for('a', 'Publishing')
        ->withTopic(GuideTopic::for('t', 'Write and publish', '', [
            new GuideStep('Choose Posts, then Add Post.', 'The editor opens.'),
        ]));

    expect(strtolower($guide->searchText()))->toContain('add post')
        ->and(strtolower($guide->searchText()))->toContain('the editor opens');
});
