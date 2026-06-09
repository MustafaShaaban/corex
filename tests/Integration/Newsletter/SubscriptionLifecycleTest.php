<?php

/**
 * Integration test: the subscriber data path on real ./wp (spec 013 US1: FR-001, FR-002, SC-001).
 * The provider creates the corex_subscribers table on init; this exercises subscribe → confirm
 * through the real custom-table store. The mail send is intercepted.
 *
 * @package Corex\Tests\Integration\Newsletter
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Newsletter\Subscriber\SubscriberRepository;
use Corex\Newsletter\Subscriber\SubscriberStore;
use Corex\Newsletter\Subscription\SubscriptionService;
use Corex\Newsletter\TokenSigner;

it('subscribes (pending) then confirms a subscriber through the real custom table', function () {
    add_filter('pre_wp_mail', '__return_true');

    $container = Boot::app()->container();
    $service   = $container->make(SubscriptionService::class);
    $store     = $container->make(SubscriberStore::class);
    $email     = 'nl-' . uniqid() . '@example.com';

    expect($service->subscribe($email, ['news'], consent: true))->toBeTrue();

    $subscriber = $store->findByEmail($email);
    expect($subscriber)->not->toBeNull()
        ->and($subscriber['status'])->toBe('pending')
        ->and($subscriber['topics'])->toBe(['news']);

    $token = $container->make(TokenSigner::class)->sign('confirm:' . $email);
    expect($service->confirm($token))->toBeTrue()
        ->and($store->findByEmail($email)['status'])->toBe('confirmed');

    // Confirmed subscriber is targeted by a matching topic publish.
    $recipients = $store->confirmedForTopics(['news']);
    $emails     = array_column($recipients, 'email');
    expect($emails)->toContain($email);

    $container->make(SubscriberRepository::class)->delete($subscriber['id']);
    remove_all_filters('pre_wp_mail');
});
