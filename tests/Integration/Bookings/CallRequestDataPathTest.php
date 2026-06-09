<?php

/**
 * Integration test: the call-request data path on real ./wp (spec 015 US1: FR-003, SC-001).
 * The provider creates the corex_call_requests table on init; this stores a request through the
 * real custom-table store with a configured leader. Mail is intercepted.
 *
 * @package Corex\Tests\Integration\Bookings
 */

declare(strict_types=1);

use Corex\Bookings\CallRequestRepository;
use Corex\Bookings\CallRequestService;
use Corex\Bookings\LeaderDirectory;
use Corex\Bookings\WpCallRequestStore;
use Corex\Boot;

it('stores a valid call request through the real custom table', function () {
    add_filter('pre_wp_mail', '__return_true');

    $container = Boot::app()->container();
    $store     = new WpCallRequestStore($container->make(CallRequestRepository::class));
    $leaders   = new LeaderDirectory([['id' => 'ceo', 'name' => 'CEO', 'email' => 'ceo@example.com']]);
    $service   = new CallRequestService($store, $leaders, $container->make(\Corex\Mail\Mailer::class));

    $result = $service->request('ceo', ['name' => 'Visitor', 'email' => 'cr-' . uniqid() . '@example.com', 'phone' => '123']);

    expect($result->stored)->toBeTrue();

    $row = $container->make(CallRequestRepository::class)->find((int) $result->id);
    expect($row)->not->toBeNull()
        ->and($row['status'])->toBe('requested')
        ->and($row['leader_id'])->toBe('ceo');

    $container->make(CallRequestRepository::class)->delete((int) $result->id);
    remove_all_filters('pre_wp_mail');
});
