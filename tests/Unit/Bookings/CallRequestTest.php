<?php

/**
 * Unit tests for the call-request service + leader directory (spec 015 US1: FR-001..FR-004, SC-001/2).
 *
 * @package Corex\Tests\Unit\Bookings
 */

declare(strict_types=1);

use Corex\Bookings\CallRequestService;
use Corex\Bookings\CallRequestStore;
use Corex\Bookings\LeaderDirectory;
use Corex\Mail\Mailer;
use Corex\Mail\MailRequest;

final class ArrayCallStore implements CallRequestStore
{
    /** @var array<int,array<string,mixed>> */
    public array $rows = [];
    private int $auto = 0;

    public function create(array $data): int
    {
        $id = ++$this->auto;
        $this->rows[$id] = $data;

        return $id;
    }
}

final class FakeBookingsMailer implements Mailer
{
    /** @var list<MailRequest> */
    public array $sent = [];

    public function send(MailRequest $request): void
    {
        $this->sent[] = $request;
    }
}

function callService(ArrayCallStore $store, FakeBookingsMailer $mailer): CallRequestService
{
    $leaders = new LeaderDirectory([['id' => 'ceo', 'name' => 'The CEO', 'email' => 'ceo@example.com']]);

    return new CallRequestService($store, $leaders, $mailer);
}

it('finds a leader and lists leaders without exposing emails', function () {
    $directory = new LeaderDirectory([['id' => 'ceo', 'name' => 'CEO', 'email' => 'ceo@x.com']]);

    expect($directory->find('ceo')['email'])->toBe('ceo@x.com')
        ->and($directory->find('nobody'))->toBeNull()
        ->and($directory->all())->toBe([['id' => 'ceo', 'name' => 'CEO']]);
});

it('stores a valid request and notifies the leader + the visitor', function () {
    $store  = new ArrayCallStore();
    $mailer = new FakeBookingsMailer();

    $result = callService($store, $mailer)->request('ceo', [
        'name' => 'Visitor', 'email' => 'v@example.com', 'phone' => '123', 'preferred_time' => 'Mon 10am',
    ]);

    expect($result->stored)->toBeTrue()
        ->and($store->rows)->toHaveCount(1)
        ->and($store->rows[$result->id]['status'])->toBe('requested')
        ->and($mailer->sent)->toHaveCount(2)
        ->and($mailer->sent[0]->to)->toBe(['ceo@example.com'])
        ->and($mailer->sent[1]->to)->toBe(['v@example.com']);
});

it('rejects an unknown leader or invalid fields with zero side effects', function () {
    $store   = new ArrayCallStore();
    $mailer  = new FakeBookingsMailer();
    $service = callService($store, $mailer);

    expect($service->request('ghost', ['name' => 'A', 'email' => 'a@b.com'])->reason)->toBe('unknown_leader')
        ->and($service->request('ceo', ['name' => '', 'email' => 'a@b.com'])->reason)->toBe('invalid_fields')
        ->and($service->request('ceo', ['name' => 'A', 'email' => 'bad'])->reason)->toBe('invalid_fields')
        ->and($store->rows)->toBe([])
        ->and($mailer->sent)->toBe([]);
});
