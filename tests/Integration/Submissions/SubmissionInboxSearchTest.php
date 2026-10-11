<?php

/**
 * The inbox's search finds a submission by the sender its row shows (spec 068, FR-046; spec 103,
 * T110 to T113).
 *
 * Found on 2026-10-11: a submission of a form defined in code showed its sender's email in the
 * row, and a search for that email answered "0 accessible submissions". Such a submission is
 * stored with its answers and nothing else, and the search read two keys only a flow's
 * submission has. Every submission here is stored by the repository a form defined in code
 * stores through, so the test meets what a client site's database holds.
 *
 * @package Corex\Tests\Integration\Submissions
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Data\DataQuery;
use Corex\Config\Data\WpSubmissionsReader;
use Corex\Config\Submissions\SubmissionAccessScope;
use Corex\Config\Submissions\SubmissionInboxQuery;
use Corex\Forms\Submission\SubmissionRepository;

beforeEach(function () {
    $this->administrator = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
    $this->everything = new SubmissionAccessScope($this->administrator, true);
    $this->reader = new WpSubmissionsReader();
    $this->repository = Boot::app()->container()->make(SubmissionRepository::class);
    // No other submission on the install holds it, so a search for it lists what the test stored.
    $this->marker = 'cxsearch' . strtolower(wp_generate_password(10, false));
    $this->stored = [];

    $this->store = function (array $answers): int {
        $id = $this->repository->store('corex-search-test-form', $answers)->id();
        $this->stored[] = $id;

        return $id;
    };
    $this->found = fn (string $search, ?SubmissionAccessScope $scope = null): array => array_column(
        $this->reader->queryInbox(SubmissionInboxQuery::from(['search' => $search]), $scope ?? $this->everything)['items'],
        'id',
    );
});

afterEach(function () {
    foreach ($this->stored as $id) {
        wp_delete_post($id, true);
    }
});

it('finds a submission of a form defined in code by the email its row shows', function () {
    $email = $this->marker . '@example.com';
    $id = ($this->store)(['name' => 'Salma Fixture', 'email' => $email, 'message' => 'Hello']);

    $page = $this->reader->queryInbox(SubmissionInboxQuery::from(['search' => $email]), $this->everything);

    expect($page['total'])->toBe(1)
        ->and($page['items'][0])->toMatchArray(['id' => $id, 'submitter_email' => $email]);
});

it('finds it by a part of the name its row shows', function () {
    $id = ($this->store)(['name' => 'Salma ' . $this->marker, 'email' => 'salma@example.com']);

    expect(($this->found)($this->marker))->toBe([$id]);
});

it('finds it by whichever answer the row reads the sender from', function (string $question, string $shownAs) {
    $id = ($this->store)([$question => $this->marker . '@example.com']);

    $page = $this->reader->queryInbox(SubmissionInboxQuery::from(['search' => $this->marker]), $this->everything);

    expect(array_column($page['items'], 'id'))->toBe([$id])
        ->and($page['items'][0][$shownAs])->toBe($this->marker . '@example.com');
})->with([
    'a full name' => ['full_name', 'submitter_name'],
    'a first name' => ['first_name', 'submitter_name'],
    'an email address' => ['email_address', 'submitter_email'],
]);

it('does not look in an answer the row does not show as the sender', function () {
    ($this->store)(['name' => 'Salma Fixture', 'email' => 'salma@example.com', 'message' => $this->marker]);

    expect(($this->found)($this->marker))->toBe([]);
});

it('shows a person nothing of a submission that is not theirs, whatever they search for', function () {
    $email = $this->marker . '@example.com';
    ($this->store)(['name' => 'Salma Fixture', 'email' => $email]);
    $theirOwnOnly = new SubmissionAccessScope($this->administrator, false);

    expect(($this->found)($email, $theirOwnOnly))->toBe([]);
});

it('finds it from the Data screen by the same email', function () {
    $email = $this->marker . '@example.com';
    $id = ($this->store)(['name' => 'Salma Fixture', 'email' => $email]);
    $query = DataQuery::from(['search' => $email]);

    expect($this->reader->count($query))->toBe(1)
        ->and(array_column($this->reader->query($query), 'id'))->toBe([$id]);
});
