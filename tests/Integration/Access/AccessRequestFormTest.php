<?php

/**
 * The browser's half of the access request, against real WordPress (spec 079, US1 + US3).
 *
 * Every test here is about a decision the handler makes before or instead of writing a row, so the
 * assertions are on what the table contains afterwards rather than on what was called.
 *
 * @package Corex\Tests\Integration\Access
 */

declare(strict_types=1);

use Corex\Access\AccessRequestStore;
use Corex\Access\AccessRequestSurfaceState;
use Corex\Access\CorexAbility;
use Corex\Admin\AdminPage;
use Corex\Boot;
use Corex\Config\Access\AccessRequestFlash;
use Corex\Config\Access\AccessRequestFormController;
use Corex\Config\Access\AccessTables;
use Corex\Database\Schema\Migrator;

/**
 * Puts a POST body in place and asks the controller what it would do.
 *
 * `decide()` rather than `handle()` on purpose: `handle()` ends every branch in `exit`, which PHP
 * cannot catch, so driving it here would kill the test runner rather than fail a test. The two
 * lines `handle()` adds on top — `wp_safe_redirect` and `exit` — are covered by the browser spec.
 *
 * @param array<string,string> $post
 * @return string|null The destination, or null when the submission is refused outright.
 */
function submitAccessRequest(AccessRequestFormController $controller, array $post): ?string
{
    $_POST = $post;

    try {
        return $controller->decide();
    } finally {
        $_POST = [];
    }
}

beforeEach(function () {
    $this->container = Boot::app()->container();
    foreach ($this->container->make(AccessTables::class)->schemas() as $schema) {
        $this->container->make(Migrator::class)->create($schema);
    }

    // A real subscriber, not an administrator with a capability removed: the whole flow turns on
    // what a low-privilege user can reach, and a doctored administrator is not that.
    $this->requester = (int) wp_insert_user([
        'user_login' => 'corex-079-requester-' . wp_generate_password(8, false),
        'user_pass'  => wp_generate_password(),
        'user_email' => uniqid('corex079-', true) . '@example.test',
        'role'       => 'subscriber',
    ]);
    wp_set_current_user($this->requester);

    $this->requests   = $this->container->make(AccessRequestStore::class);
    $this->controller = $this->container->make(AccessRequestFormController::class);
    $this->flash      = $this->container->make(AccessRequestFlash::class);

    $this->body = fn (array $extra = []): array => $extra + [
        AccessRequestFormController::NONCE => wp_create_nonce(AccessRequestFormController::ACTION),
        'corex_section'                    => 'corex-forms',
        'reason'                           => 'I maintain the contact form and cannot open the screen.',
    ];

    $this->mine = fn (): array => array_values(array_filter(
        $this->requests->pending(),
        fn (array $row): bool => $row['requesterId'] === $this->requester,
    ));
});

it('creates one request and sends the browser back to the screen, not to the REST route', function () {
    $destination = submitAccessRequest($this->controller, ($this->body)());

    expect(($this->mine)())->toHaveCount(1)
        ->and(($this->mine)()[0]['abilityKey'])->toBe(CorexAbility::MANAGE_FORMS)
        ->and($destination)->toContain('page=corex-forms')
        // The defect this spec exists for: the browser must never be sent at the JSON API.
        ->and($destination)->not->toContain('wp-json');
});

it('derives the ability from the section rather than trusting a posted one', function () {
    // A posted ability would let anyone request anything from anywhere, and the result would be
    // indistinguishable from a legitimate request in the approval queue.
    submitAccessRequest($this->controller, ($this->body)(['ability' => CorexAbility::MANAGE_ACCESS]));

    expect(($this->mine)())->toHaveCount(1)
        ->and(($this->mine)()[0]['abilityKey'])->toBe(CorexAbility::MANAGE_FORMS);
});

it('creates only one request when the same form is submitted twice', function () {
    submitAccessRequest($this->controller, ($this->body)());
    $second = submitAccessRequest($this->controller, ($this->body)());

    expect(($this->mine)())->toHaveCount(1)
        ->and($second)->toContain('page=corex-forms');
});

it('creates nothing and keeps the typed reason when the reason is empty', function () {
    $destination = submitAccessRequest($this->controller, ($this->body)(['reason' => '   ']));

    expect(($this->mine)())->toBeEmpty()
        ->and($destination)->toContain('page=corex-forms');

    $problem = $this->flash->take($this->requester);

    expect($problem)->not->toBeNull()
        ->and($problem['problem'])->toBe(AccessRequestSurfaceState::PROBLEM_INVALID);
});

it('creates nothing when the section is not a CoreX screen', function () {
    $destination = submitAccessRequest($this->controller, ($this->body)(['corex_section' => 'corex-nonexistent']));

    expect(($this->mine)())->toBeEmpty()
        ->and($destination)->not->toContain('page=corex-');
});

it('creates nothing without a valid nonce', function () {
    $destination = submitAccessRequest($this->controller, [
        AccessRequestFormController::NONCE => 'not-a-nonce',
        'corex_section'                    => 'corex-forms',
        'reason'                           => 'Please.',
    ]);

    expect(($this->mine)())->toBeEmpty()
        // Refused outright, not redirected: handle() renders the designed 403 for this.
        ->and($destination)->toBeNull();
});

it('creates nothing for a signed-out visitor', function () {
    wp_set_current_user(0);

    $destination = submitAccessRequest($this->controller, ($this->body)());

    expect(($this->mine)())->toBeEmpty()
        ->and($destination)->toBeNull();
});

it('points the form at an admin endpoint, never at the REST route', function () {
    // The defect itself, asserted on the markup rather than on the outcome: the form's action was
    // `rest_url('corex/v1/access/requests')`, so pressing the button navigated the browser to a
    // JSON document. Everything else in this file could pass with that action restored.
    $form = $this->container->make(AdminPage::class)->deniedSurface('corex-forms');

    expect($form)->toContain('admin-post.php')
        ->and($form)->not->toContain('wp-json')
        ->and($form)->not->toContain(rest_url('corex/v1/access/requests'));
});

it('shows the surface the confirmation once a request exists, and stops offering the form', function () {
    $page = $this->container->make(AdminPage::class);

    expect($page->deniedSurface('corex-forms'))->toContain('corex-denied__request');

    submitAccessRequest($this->controller, ($this->body)());
    $sent = $page->deniedSurface('corex-forms');

    expect($sent)->toContain('corex-denied__sent')
        // Offering a second copy of a form they have already submitted is how duplicates are made.
        ->and($sent)->not->toContain('corex-denied__request')
        // Nothing internal reaches the page — the whole complaint about the old JSON document.
        ->and($sent)->not->toContain('operation_id')
        ->and($sent)->not->toContain('affected_ids')
        ->and($sent)->not->toContain('audit_event_id');
});

it('keeps the REST route answering JSON, because the fix must not convert an API into a page', function () {
    $request = new WP_REST_Request('POST', '/corex/v1/access/requests');
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    $request->set_body_params([
        'ability' => CorexAbility::MANAGE_FORMS,
        'reason'  => 'Requested through the API, as an integration would.',
    ]);

    $response = $this->container->make(\Corex\Config\Access\AccessController::class)->createRequest($request);

    expect($response)->toBeInstanceOf(WP_REST_Response::class)
        ->and($response->get_status())->toBe(200)
        ->and($response->get_data()['data']['result'])->toHaveKeys(['operation_id', 'state', 'affected_ids']);
});
