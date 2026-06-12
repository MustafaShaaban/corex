<?php

/**
 * Unit tests for the cap-gated form-list source (spec 029 US2: FR-005/006). The route feeds
 * the block's form selector; it exposes only slug + label and requires `edit_posts`.
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Forms\Form;
use Corex\Forms\FormRegistry;
use Corex\Forms\Submission\FormsListController;

function makeForm(string $slug): Form
{
    return new class($slug) extends Form {
        public function __construct(string $slug)
        {
            $this->slug = $slug;
        }
    };
}

it('lists registered forms as slug + label only', function () {
    $registry = new FormRegistry();
    $registry->register(makeForm('contact'));
    $registry->register(makeForm('newsletter-signup'));

    $options = (new FormsListController($registry))->options();

    expect($options)->toBe([
        ['slug' => 'contact', 'label' => 'Contact'],
        ['slug' => 'newsletter-signup', 'label' => 'Newsletter Signup'],
    ]);
});

it('returns an empty list when no forms are registered', function () {
    expect((new FormsListController(new FormRegistry()))->options())->toBe([]);
});

it('permits only users who can edit posts', function () {
    Functions\when('current_user_can')->alias(fn ($cap) => $cap === 'edit_posts');

    expect((new FormsListController(new FormRegistry()))->permission())->toBeTrue();

    Functions\when('current_user_can')->justReturn(false);
    expect((new FormsListController(new FormRegistry()))->permission())->toBeFalse();
});
